<?php
session_start();
header("Content-Type: application/json");

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

// --- Step 1: Detect intent via Gemini ---
$systemPrompt = <<<EOT
You are a company HR chatbot.
You help employees with attendance and schedules.

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

// Extract JSON safely
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
$employee = $employeeId ?: ($intentData["employee"] ?? null); // ✅ auto-use session
$date = $intentData["date"] ?? date("Y-m-d");

$finalAnswer = "";
if ($intent === "get_attendance") {
    $attendanceJson = file_get_contents("http://127.0.0.1/public_html/api/TimeAndAttendance.php");
    $attendanceData = json_decode($attendanceJson, true);

    // Attempt to find attendance for the date (and optional employee)
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
        $answers = array_map(function($row) {
            return "{$row['employee_name']} worked {$row['hours_worked']} on {$row['work_date']}. (Clock-in: {$row['clock_in']}, Clock-out: {$row['clock_out']})";
        }, $filtered);

        $finalAnswer = implode("\n", $answers);
    } else {
        // No attendance record found: resolve employee name if we have an ID
        $displayName = $employee; // fallback
        if ($employee) {
            // Lookup name from attendance JSON
            $found = array_filter($attendanceData, function($row) use ($employee) {
                return $row['employee_id'] === $employee;
            });
            if (!empty($found)) {
                $firstMatch = reset($found);
                $displayName = $firstMatch['employee_name'] ?? $employee;
            }
        }
        $finalAnswer = "No attendance record found for {$displayName} on $date.";
    }
}

elseif ($intent === "get_schedule_today") {
    $finalAnswer = "";
    $today = date('Y-m-d');

    // Fetch schedules
    $scheduleJson = file_get_contents("http://127.0.0.1/public_html/api/scheduleApi.php");
    $scheduleData = json_decode($scheduleJson, true);

    // Map employee IDs to names for easier lookup
    $employeeNames = [];
    foreach ($scheduleData as $row) {
        if (!isset($employeeNames[$row['employee_id']])) {
            $employeeNames[$row['employee_id']] = $row['employee_name'] ?? $row['employee_id'];
        }
    }

    // Filter by date and optionally by employee
    $filtered = array_filter($scheduleData, function($row) use ($employee, $today) {
        $matchDate = ($row["work_date"] === $today);
        if (!$employee) return $matchDate;
        return $matchDate && ($row['employee_id'] === $employee || strcasecmp($row['employee_name'] ?? '', $employee) === 0);
    });

    if (!empty($filtered)) {
        $answers = array_map(function($row) {
            $empName = $row['employee_name'] ?? $row['employee_id'];
            $shift = $row['shift_details'] ?? [];
            $shiftName = ($shift['shift_code'] ?? '') . ' - ' . ($shift['shift_name'] ?? '');
            $timeRange = ($shift['start_time'] ?? '') . ' - ' . ($shift['end_time'] ?? '');
            $notes = $row['notes'] ?: 'No notes';
            return "$empName has shift $shiftName ($timeRange) today. Status: {$row['status']}. Notes: $notes";
        }, $filtered);

        $finalAnswer = implode("\n", $answers);
    } else {
        // Show employee name even if no schedule is found
        $displayName = $employeeNames[$employee] ?? $employee ?? "any employee";
        $finalAnswer = "No schedule found for $displayName today.";
    }
}

elseif ($intent === "get_leave_balance") {
    $finalAnswer = "";

    // Fetch leave balances from API
    $leaveJson = file_get_contents("http://127.0.0.1/public_html/api/leaveApi.php");
    $leaveData = json_decode($leaveJson, true);

    if (!$leaveData) {
        $finalAnswer = "Unable to fetch leave balances.";
    } else {
    // Detect requested leave type from user input
$employee_leave_type = null;
if (preg_match('/(?:my\s+)?leave\s+balance(?:\s+in)?\s+(.+)/i', $userMessage, $matches)) {
    $employee_leave_type = trim($matches[1]); // e.g., "sick leave"
}
$leaveTypeFilter = strtolower($employee_leave_type ?? "");

        // Filter by employee if specified
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
                foreach ($row['leave_balances'] as $lb) {
                    // Filter by requested leave type if specified
                    if ($leaveTypeFilter && stripos($lb['leave_name'], $leaveTypeFilter) === false) continue;

                    $msg .= "{$lb['leave_name']} - Remaining: {$lb['remaining_days']}, Total: {$lb['total_days']}, Used: {$lb['used_days']}\n\n";
                    $found = true;
                }

                if (!$found) {
                    if ($leaveTypeFilter) {
                        $msg .= "No records found for '{$employee_leave_type}'.\n";
                    } else {
                        $msg .= "No leave balance records available.\n";
                    }
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



elseif ($intent === "get_claims") {
    $finalAnswer = "Leave balance lookup not yet implemented. (Stub)";
}


else {
    $finalAnswer = "Hello 👋 How can I help you today?";
}

echo json_encode([
    "answer" => $finalAnswer,
    "intent" => $intentData,
    "employee_used" => $employee,
    "date_used" => $date
]);
exit;
?>
