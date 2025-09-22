<?php
session_start();
header("Content-Type: application/json");

// ==========================
// DB Connections
// ==========================
include __DIR__ . '/dbconnection/dbEmployee.php'; // employees table
$empConn = $conn;

include __DIR__ . '/dbconnection/mainDB.php'; // chat_history table
$chatConn = $conn;

// --- Load .env ---
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode("=", $line, 2);
        putenv(trim($name) . "=" . trim($value));
    }
}
loadEnv(__DIR__ . '/.env');

$apiKey = getenv("GEMINI_API_KEY");
if (!$apiKey) {
    echo json_encode(["error" => "API key not found"]);
    exit;
}

// --- Handle input ---
$input = json_decode(file_get_contents("php://input"), true);
$userMessage = $input["message"] ?? "Hello";

// --- Current logged in employee ---
$employeeId = $_SESSION['employee_id'] ?? null;

// ---------------------------
// Function: Save chat history
// ---------------------------
function saveChatHistory($conn, $employee_id, $sender, $message, $intent = null, $date_used = null) {
    $stmt = $conn->prepare("INSERT INTO chat_history (employee_id, sender, message, intent, date_used) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $empIdParam = $employee_id ?: null;
        $stmt->bind_param("sssss", $empIdParam, $sender, $message, $intent, $date_used);
        $stmt->execute();
        $stmt->close();
    }
}

// --- Step 1: Detect intent via Gemini ---
$systemPrompt = <<<EOT
You are a company HR chatbot.
You help employees with attendance, schedules, and leave balances.

Return ONLY valid JSON:
{
  "intent": "get_attendance" | "get_leave_balance" | "get_schedule_today" | "small_talk",
  "employee": "username, id or name if mentioned, otherwise null",
  "date": "YYYY-MM-DD if mentioned, otherwise today"
}
EOT;

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

$data = [
    "contents" => [
        [
            "parts" => [
                ["text" => $systemPrompt],
                ["text" => "User: $userMessage"]
            ]
        ]
    ]
];

$options = [
    "http" => [
        "header"  => "Content-Type: application/json\r\n",
        "method"  => "POST",
        "content" => json_encode($data),
    ]
];

$context  = stream_context_create($options);
$response = file_get_contents($url, false, $context);

if ($response === FALSE) {
    echo json_encode(["error" => "API request failed"]);
    exit;
}

$responseData = json_decode($response, true);
$rawIntent = $responseData["candidates"][0]["content"]["parts"][0]["text"] ?? "{}";

if (preg_match('/\{.*\}/s', $rawIntent, $matches)) {
    $intentData = json_decode($matches[0], true);
} else {
    $intentData = null;
}

if (!$intentData || !isset($intentData["intent"])) {
    echo json_encode([
        "answer" => "Sorry, I couldn’t understand your request.",
        "debug_raw" => $rawIntent,
        "debug_response" => $responseData
    ]);
    exit;
}

$intent = $intentData["intent"];
$employee = $employeeId ?: ($intentData["employee"] ?? null);
$date = $intentData["date"] ?? date("Y-m-d");

// ---------------------------
// Save user message
// ---------------------------
saveChatHistory($chatConn, $employee, 'user', $userMessage, $intent, $date);

// ---------------------------
// Handle intents
// ---------------------------
$finalAnswer = "";

// ---------------------------
// 1. Attendance
// ---------------------------
if ($intent === "get_attendance") {
    $attendanceJson = @file_get_contents("http://127.0.0.1/public_html/api/TimeAndAttendance.php");
    $attendanceData = json_decode($attendanceJson, true) ?: [];

    $filtered = array_filter($attendanceData, function($row) use ($employee, $date) {
        $matchDate = ($row["work_date"] === $date);
        if (!$employee) return $matchDate;
        return (
            strcasecmp($row["username"], $employee) === 0 ||
            strcasecmp($row["employee_name"], $employee) === 0 ||
            strcasecmp($row["employee_id"], $employee) === 0
        ) && $matchDate;
    });

    if (!empty($filtered)) {
        $answers = array_map(fn($row) => "{$row['employee_name']} worked {$row['hours_worked']} on {$row['work_date']}. (Clock-in: {$row['clock_in']}, Clock-out: {$row['clock_out']})", $filtered);
        $finalAnswer = implode("\n", $answers);
    } else {
        $displayName = $employee;
        if ($employee) {
            $found = array_filter($attendanceData, fn($row) => $row['employee_id'] === $employee);
            if (!empty($found)) {
                $firstMatch = reset($found);
                $displayName = $firstMatch['employee_name'] ?? $employee;
            }
        }
        $finalAnswer = "No attendance record found for {$displayName} on $date.";
    }
}

// ---------------------------
// 2. Schedule Today
// ---------------------------
elseif ($intent === "get_schedule_today") {
    $today = date('Y-m-d');

    $scheduleJson = @file_get_contents("http://127.0.0.1/public_html/api/scheduleApi.php");
    if (!$scheduleJson) {
        $finalAnswer = "Error: Could not fetch schedule API.";
    } else {
        $scheduleData = json_decode($scheduleJson, true);
        if (!$scheduleData) {
            $finalAnswer = "Error: Schedule API returned invalid JSON: $scheduleJson";
        } else {
            $employeeNames = [];
            foreach ($scheduleData as $row) {
                $employeeNames[$row['employee_id']] = $row['employee_name'] ?? $row['employee_id'];
            }

            $filtered = array_filter($scheduleData, function($row) use ($employee, $today) {
                $matchDate = ($row["work_date"] === $today);
                if (!$employee) return $matchDate;
                return $matchDate && ($row['employee_id'] === $employee || strcasecmp($row['employee_name'] ?? '', $employee) === 0);
            });

            if (!empty($filtered)) {
                $answers = array_map(function($row) {
                    $empName = $row['employee_name'] ?? $row['employee_id'];
                    $shift = is_array($row['shift_details'] ?? null) ? $row['shift_details'] : [];
                    $shiftName = ($shift['shift_code'] ?? '') . ' - ' . ($shift['shift_name'] ?? '');
                    $timeRange = ($shift['start_time'] ?? '') . ' - ' . ($shift['end_time'] ?? '');
                    $notes = $row['notes'] ?: 'No notes';
                    return "$empName has shift $shiftName ($timeRange) today. Status: {$row['status']}. Notes: $notes";
                }, $filtered);
                $finalAnswer = implode("\n", $answers);
            } else {
                $displayName = $employeeNames[$employee] ?? $employee ?? "any employee";
                $finalAnswer = "No schedule found for $displayName today.";
            }
        }
    }
}

// ---------------------------
// 3. Leave Balance
// ---------------------------

elseif ($intent === "get_leave_balance") {
    $leaveJson = @file_get_contents("http://127.0.0.1/public_html/api/leaveApi.php");
    $leaveData = json_decode($leaveJson, true);

    if (!$leaveData || !is_array($leaveData)) {
        $finalAnswer = "Unable to fetch leave balances.";
    } else {
        // Try to detect requested leave type from user message
        $employee_leave_type = null;
        if (preg_match('/leave\s+balance(?:\s+in)?\s+(.+)/i', $userMessage, $matches)) {
            $employee_leave_type = trim($matches[1]);
        }
        $leaveTypeFilter = strtolower($employee_leave_type ?? "");

        // Filter data for the employee
        $filtered = [];
        foreach ($leaveData as $row) {
            if (!$employee || strcasecmp($row['employee_name'], $employee) === 0 || $row['employee_id'] === $employee) {
                $filtered[] = $row;
            }
        }

        if (!empty($filtered)) {
            $answers = [];
            foreach ($filtered as $row) {
                $empName = $row['employee_name'] ?? $row['employee_id'];
                $msg = "$empName's Leave Balances:\n\n";
                $found = false;

                if (!empty($row['leave_balances']) && is_array($row['leave_balances'])) {
                    foreach ($row['leave_balances'] as $lb) {
                        if ($leaveTypeFilter && stripos($lb['leave_name'], $leaveTypeFilter) === false) continue;
                        $msg .= "{$lb['leave_name']} - Remaining: {$lb['remaining_days']}, Total: {$lb['total_days']}, Used: {$lb['used_days']}\n";
                        $found = true;
                    }
                }

                if (!$found) {
                    $msg .= $leaveTypeFilter 
                        ? "No records found for '{$employee_leave_type}'.\n" 
                        : "No leave balance records available.\n";
                }

                $answers[] = $msg;
            }
            $finalAnswer = implode("\n", $answers);
        } else {
            $displayName = $employee ?? "any employee";
            $finalAnswer = "No leave balance found for $displayName.";
        }
    }
}

// ---------------------------
// 4. Small Talk / Default
// ---------------------------
else {
    $finalAnswer = "Hello 👋 How can I help you today?";
}

// ---------------------------
// Save bot response
// ---------------------------
saveChatHistory($chatConn, $employee, 'bot', $finalAnswer, $intent, $date);

// ---------------------------
// Return JSON response
// ---------------------------
echo json_encode([
    "answer" => $finalAnswer,
    "intent" => $intentData,
    "employee_used" => $employee,
    "date_used" => $date
]);
exit;
?>
