<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * verify.php
 *
 * CLOCK IN/OUT with Face++ verification
 * - Uses today's schedule (or yesterday if overnight)
 * - Validates time window with grace period
 * - Prevents double clock-in
 * - Calculates hours worked correctly (including overnight)
 * - Uses prepared statements to avoid SQL injection
 * - Cleans up temp files
 * - Adds camera/base64 handling + fallback simple attendance insert (from the second snippet)
 */

// ----------------------------------------------------
// CONFIG
// ----------------------------------------------------
date_default_timezone_set('Asia/Manila'); // adjust if needed

// Face++ credentials
// You can replace these values with your preferred keys.
$api_key    = "nlDPUaWDEsAAD81T-zdC3lNhdKOL2zHH";
$api_secret = "nfZ00JGDKhX3izjF0U6H6VwxCWNaoeCn";

// If you prefer the other keys in your earlier verify.php, replace above with:
// $api_key = "nlDPUaWDEsAAD81T-zdC3lNhdKOL2zHH";
// $api_secret = "nfZ00JGDKhX3izjF0U6H6VwxCWNaoeCn";

// Grace minutes for *late* allowance after shift start (e.g., 15)
$GRACE_MINUTES = 12000;

// Directories
$baseUploadDir = __DIR__ . '/../uploads/attenance/';
$clockInDir    = $baseUploadDir . 'clock_in/';
$clockOutDir   = $baseUploadDir . 'clock_out/';
$tempDir       = __DIR__ . '/../uploads/reference_image/'; // re-using reference_image dir for temp

foreach ([$baseUploadDir, $clockInDir, $clockOutDir, $tempDir] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0777, true);
}

// ----------------------------------------------------
// DB CONNECTIONS
// ----------------------------------------------------
include __DIR__ . '/../dbconnection/dbEmployee.php';
$empConn = $conn; // employees DB

include __DIR__ . '/../dbconnection/mainDB.php';
$mainConn = $conn; // main DB (users, schedules, shifts, attendance)

// ----------------------------------------------------
// HELPERS
// ----------------------------------------------------
function flash_and_redirect(string $message): void {
    $_SESSION['flash_message'] = $message;
    header("Location: clockin.php");
    exit;
}

function facepp_compare(string $api_key, string $api_secret, string $path1, string $path2): array {
    $url = "https://api-us.faceplusplus.com/facepp/v3/compare";

    $postData = [
        "api_key"    => $api_key,
        "api_secret" => $api_secret,
        "image_file1"=> new CURLFile($path1),
        "image_file2"=> new CURLFile($path2),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['error' => "Face++ request error: $curlErr"];
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        return ['error' => 'Face++ invalid response'];
    }
    if (isset($json['error_message'])) {
        return ['error' => "Face++ API error: " . $json['error_message']];
    }
    return $json;
}

/**
 * Get schedule for today; if none, check yesterday (overnight).
 */
function get_today_or_overnight_schedule(mysqli $db, string $employee_id): ?array {
    $today = date('Y-m-d');

    // Today
    $sql = "
        SELECT es.schedule_id, es.work_date, s.shift_id, s.name AS shift_name,
               s.start_time, s.end_time, s.is_overnight
        FROM employee_schedules es
        INNER JOIN shifts s ON es.shift_id = s.shift_id
        WHERE es.employee_id = ? AND es.work_date = ? AND es.status = 'scheduled'
        LIMIT 1
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ss', $employee_id, $today);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) return $res->fetch_assoc();

    // Yesterday (only if overnight)
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $sql = "
        SELECT es.schedule_id, es.work_date, s.shift_id, s.name AS shift_name,
               s.start_time, s.end_time, s.is_overnight
        FROM employee_schedules es
        INNER JOIN shifts s ON es.shift_id = s.shift_id
        WHERE es.employee_id = ? AND es.work_date = ? AND es.status = 'scheduled' AND s.is_overnight = 1
        LIMIT 1
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ss', $employee_id, $yesterday);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) return $res->fetch_assoc();

    return null;
}

/**
 * Returns DateTime ranges for a shift (handles overnight)
 */
function get_shift_window(string $workDate, string $start_time, string $end_time, bool $isOvernight): array {
    if ($isOvernight) {
        $start = new DateTime("$workDate $start_time");
        $end   = new DateTime("$workDate $end_time");
        $end->modify('+1 day');
    } else {
        $start = new DateTime("$workDate $start_time");
        $end   = new DateTime("$workDate $end_time");
    }
    return [$start, $end];
}

/**
 * Fetch active/open attendance record for this schedule+user.
 * "Open" means has clock_in but no clock_out yet.
 */
function get_open_attendance(mysqli $db, string $schedule_id, string $user_id): ?array {
    $sql = "SELECT * FROM attendance WHERE schedule_id = ? AND user_id = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ss', $schedule_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) return null;
    $row = $res->fetch_assoc();
    return $row;
}

// ----------------------------------------------------
// RECEIVE IMAGE
// ----------------------------------------------------
// Accepts base64 camera image in POST 'current' (your original flow)
if (!isset($_POST['current']) || empty($_POST['current'])) {
    flash_and_redirect(" No image data received from webcam.");
}

// Save temp image with unique filename
$raw = str_replace(['data:image/jpeg;base64,', 'data:image/png;base64,', ' '], ['', '', '+'], $_POST['current']);
$tempImage = $tempDir . 'temp_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
file_put_contents($tempImage, base64_decode($raw));

// ----------------------------------------------------
// FIND MATCHED USER (Face++)
// ----------------------------------------------------
$usersStmt = $mainConn->prepare("SELECT user_id, username, reference_image FROM users WHERE reference_image IS NOT NULL");
$usersStmt->execute();
$usersRes = $usersStmt->get_result();
if ($usersRes->num_rows == 0) {
    if (file_exists($tempImage)) unlink($tempImage);
    flash_and_redirect(" No registered users with reference images found.");
}

$matched = false;
$matchedUser = null;

while ($user = $usersRes->fetch_assoc()) {
    // Compose absolute path to reference image (stored path likely 'uploads/reference_image/...')
    $referencePath = __DIR__ . '/../' . ltrim($user['reference_image'], '/');

    if (!file_exists($referencePath)) continue;

    $compare = facepp_compare($api_key, $api_secret, $referencePath, $tempImage);
    if (isset($compare['error'])) {
        // Optionally log: error_log($compare['error']);
        continue; // try next user
    }

    $confidence = $compare['confidence'] ?? 0;
    if ($confidence > 85) { // threshold (85) same as your second snippet
        $matched = true;
        $matchedUser = $user;
        break;
    }
}

if (!$matched) {
    if (file_exists($tempImage)) unlink($tempImage);
    flash_and_redirect(" Face not recognized!");
}

// ----------------------------------------------------
// MAP user -> employee
// ----------------------------------------------------
$user_id = $matchedUser['user_id'];
$username = $matchedUser['username'];

$empStmt = $empConn->prepare("SELECT employee_id FROM employees WHERE user_id = ? LIMIT 1");
$empStmt->bind_param('s', $user_id);
$empStmt->execute();
$empRes = $empStmt->get_result();
if ($empRes->num_rows == 0) {
    if (file_exists($tempImage)) unlink($tempImage);
    flash_and_redirect(" No employee record found for {$username}.");
}
$employee_id = $empRes->fetch_assoc()['employee_id'];

// ----------------------------------------------------
// GET TODAY/YESTERDAY SCHEDULE (overnight aware)
// ----------------------------------------------------
$sched = get_today_or_overnight_schedule($mainConn, $employee_id);

// If schedule not found -> we will fallback to a simple insert similar to your second snippet.
// If schedule not found -> show message and do NOT insert attendance
if (!$sched) {
    // Remove temp image to avoid orphan files
    if (file_exists($tempImage)) unlink($tempImage);

    // Show friendly message to employee
    flash_and_redirect("⚠️ {$username} has no schedule or shift assigned for today.");
}

// If schedule exists -> continue with full schedule-aware clock-in/clock-out flow
$schedule_id = $sched['schedule_id'];
$shift_name  = $sched['shift_name'];
$workDate    = $sched['work_date'];
$start_time  = $sched['start_time'];
$end_time    = $sched['end_time'];
$isOvernight = (bool)$sched['is_overnight'];


list($shiftStart, $shiftEnd) = get_shift_window($workDate, $start_time, $end_time, $isOvernight);
$now = new DateTime();

// ----------------------------------------------------
// TIME VALIDATION (with grace)
// ----------------------------------------------------
$graceEnd = (clone $shiftStart)->modify("+{$GRACE_MINUTES} minutes");

if ($now < $shiftStart) {
    if (file_exists($tempImage)) unlink($tempImage);
    flash_and_redirect("⏳ Too early to clock in. Your shift starts at " . $shiftStart->format('H:i:s') . ".");
}
if ($now > $shiftEnd) {
    if (file_exists($tempImage)) unlink($tempImage);
    flash_and_redirect(" Too late to clock in. Your shift was " . $shiftStart->format('H:i:s') . " – " . $shiftEnd->format('H:i:s') . ".");
}
// Optional: enforce late cutoff (comment out if you allow clock-in anytime before shift end)
if ($now > $graceEnd) {
    if (file_exists($tempImage)) unlink($tempImage);
    flash_and_redirect(" You are late. Allowed clock-in until " . $graceEnd->format('H:i:s') . ".");
}

// ----------------------------------------------------
// ATTENDANCE LOGIC
// ----------------------------------------------------
// If there's an attendance row:
//  - If clock_out is null -> user already clocked in; do *clock-out* flow.
//  - If clock_out is not null -> already completed -> block.
$attendance = get_open_attendance($mainConn, $schedule_id, $user_id);

if ($attendance === null) {
    // No row yet → Clock IN
    $finalInPath = $clockInDir . $user_id . '_' . time() . '.jpg';
    // Move temp → final (or copy fallback)
    if (!rename($tempImage, $finalInPath)) {
        copy($tempImage, $finalInPath);
        @unlink($tempImage);
    }

    $ins = $mainConn->prepare("
        INSERT INTO attendance (attendance_id, schedule_id, user_id, clock_in, clock_in_image)
        VALUES (UUID(), ?, ?, NOW(), ?)
    ");
    $ins->bind_param('sss', $schedule_id, $user_id, $finalInPath);
    if ($ins->execute()) {
        flash_and_redirect("{$username} Clocked In! Shift: {$shift_name} ({$start_time}-{$end_time})");
    } else {
        if (file_exists($finalInPath)) unlink($finalInPath);
        flash_and_redirect(" DB Error (Clock In): " . $ins->error);
    }
} else {
    // Attendance row exists
    if (!empty($attendance['clock_out'])) {
        if (file_exists($tempImage)) unlink($tempImage);
        flash_and_redirect(" {$username} already clocked out for this shift.");
    }

    // Clock OUT
    $finalOutPath = $clockOutDir . $user_id . '_' . time() . '.jpg';
    if (!rename($tempImage, $finalOutPath)) {
        copy($tempImage, $finalOutPath);
        @unlink($tempImage);
    }

  // Compute hours and minutes worked
$clockInDT  = new DateTime($attendance['clock_in']);
$clockOutDT = new DateTime(); // now
$interval   = $clockInDT->diff($clockOutDT); // DateInterval object

$hours   = $interval->h + ($interval->days * 24); // include days if overnight
$minutes = $interval->i;

// Optional: include seconds if you want
$seconds = $interval->s;

// Format nicely
$workedTimeStr = "{$hours}h {$minutes}m"; // e.g., "2h 50m"

// Update DB if you still want decimal hours
$workedHours = $hours + ($minutes / 60);

// Update attendance
$upd = $mainConn->prepare("
    UPDATE attendance
       SET clock_out = NOW(),
           clock_out_image = ?,
           hours_worked = ?
     WHERE attendance_id = ?
     LIMIT 1
");
$upd->bind_param('sds', $finalOutPath, $workedHours, $attendance['attendance_id']);
if ($upd->execute()) {
    flash_and_redirect("{$username} Clocked Out! Hours worked: {$workedTimeStr}");
} else {
    if (file_exists($finalOutPath)) unlink($finalOutPath);
    flash_and_redirect("❌ DB Error (Clock Out): " . $upd->error);
}
}
// Final safety (shouldn’t reach here)
if (file_exists($tempImage)) unlink($tempImage);
flash_and_redirect(" No action taken.");

?>
