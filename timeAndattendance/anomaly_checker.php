

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

include __DIR__ . '/../dbconnection/mainDB.php';

// -----------------------------
// Load env for Gemini
// -----------------------------
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode("=", $line, 2);
        putenv(trim($name) . "=" . trim($value));
    }
}
loadEnv(__DIR__ . '/../.env');
$apiKey = getenv("GEMINI_API_KEY");

// -----------------------------
// Fetch attendance data
// -----------------------------
$attendanceJson = @file_get_contents("http://127.0.0.1/public_html/api/timeandattendance.php");
$attendanceData = json_decode($attendanceJson, true) ?: [];

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

// -----------------------------
// Process each record
// -----------------------------
foreach ($attendanceData as $record) {
    $employeeId = $record['employee_id'];
    $workDate   = $record['work_date'];

    // Skip if already exists in attendance_anomalies
    $check = $conn->prepare("SELECT id FROM attendance_anomalies WHERE employee_id = ? AND work_date = ?");
    $check->bind_param("ss", $employeeId, $workDate);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $check->close();
        continue; // already processed
    }
    $check->close();

    // -----------------------------
    // Rule-based anomaly detection
    // -----------------------------
    $anomalies = [];
    if (!empty($record['clock_in']) && !empty($record['clock_out'])) {
        if (strtotime($record['clock_out']) - strtotime($record['clock_in']) < 4 * 3600) {
            $anomalies[] = "Short Shift";
        }
    }
    if ($record['break_in'] === "Not Started" || $record['break_out'] === "Not Ended") {
        $anomalies[] = "Missing Break";
    }
    if (empty($record['clock_out'])) {
        $anomalies[] = "Incomplete Shift";
    }

    if (empty($anomalies)) {
        continue; // ✅ No anomalies, skip Gemini and DB insert
    }

    // -----------------------------
    // Explanation caching
    // -----------------------------
    $signature = md5(json_encode(array_values($anomalies)));

    $cache = $conn->prepare("SELECT explanation FROM anomaly_explanations WHERE anomaly_signature = ?");
    $cache->bind_param("s", $signature);
    $cache->execute();
    $cacheRes = $cache->get_result();

    if ($cacheRes && $cacheRes->num_rows > 0) {
        // ✅ Use cached explanation
        $explanation = $cacheRes->fetch_assoc()['explanation'];
    } else {
        // ❌ Not cached → Ask Gemini
        $payload = [
            "contents" => [[
                "parts" => [[
                    "text" => "Explain these anomalies in plain English:\n" .
                              "Record: " . json_encode($record) . "\n" .
                              "Anomalies: " . json_encode($anomalies) . "\n" .
                              "Return only text."
                ]]
            ]]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $explanation = "Anomalies detected.";
        $result = json_decode($response, true);
        if (isset($result["candidates"][0]["content"]["parts"][0]["text"])) {
            $explanation = $result["candidates"][0]["content"]["parts"][0]["text"];
        }

        // Save explanation in cache
        $saveCache = $conn->prepare("INSERT INTO anomaly_explanations (anomaly_signature, explanation) VALUES (?, ?)");
        $saveCache->bind_param("ss", $signature, $explanation);
        $saveCache->execute();
        $saveCache->close();
    }
    $cache->close();

    // -----------------------------
    // Save anomaly in attendance_anomalies
    // -----------------------------
    $stmt = $conn->prepare("INSERT INTO attendance_anomalies (employee_id, work_date, anomalies, explanation) VALUES (?, ?, ?, ?)");
    $jsonAnomalies = json_encode($anomalies);
    $stmt->bind_param("ssss", $employeeId, $workDate, $jsonAnomalies, $explanation);
    $stmt->execute();
    $stmt->close();
}

// -----------------------------
// Redirect with flash message
// -----------------------------
$_SESSION['flash_message'] = "✅ Anomaly check completed!";
header("Location: timeAnomaly.php");
exit;
