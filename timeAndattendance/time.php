<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Convert full file path to relative URL
function imageUrl($fullPath) {
    if (empty($fullPath)) return '';

    $fullPath = str_replace('\\', '/', $fullPath);

    if (strpos($fullPath, '/public_html/') !== false) {
        $relative = substr($fullPath, strpos($fullPath, '/public_html/') + strlen('/public_html'));
    } else {
        $relative = $fullPath;
    }

    return $relative;
}
?>

<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Manila');

// DB CONNECTIONS
include __DIR__ . '/../dbconnection/dbEmployee.php';
$empConn = $conn;

include __DIR__ . '/../dbconnection/mainDB.php';
$mainConn = $conn;

// Helper: set flash message
function set_flash_message(string $message) {
    $_SESSION['flash_message'] = $message;
}

// Get schedule
function get_schedule(mysqli $db, string $employee_id, string $work_date): ?array {
    $sql = "
        SELECT es.schedule_id, es.work_date, s.shift_id, s.name AS shift_name,
               s.start_time, s.end_time, s.is_overnight
        FROM employee_schedules es
        INNER JOIN shifts s ON es.shift_id = s.shift_id
        WHERE es.employee_id = ? AND es.work_date = ?
        LIMIT 1
    ";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ss', $employee_id, $work_date);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) return $res->fetch_assoc();
    return null;
}

// Fetch attendance
function get_attendance(mysqli $db, string $schedule_id, string $user_id): ?array {
    $sql = "SELECT * FROM attendance WHERE schedule_id = ? AND user_id = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ss', $schedule_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) return null;
    return $res->fetch_assoc();
}

// Handle POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['manual_submit'])) {
    $employee_id = $_POST['employee_id'];
    $work_date   = $_POST['work_date'];
    $clock_in    = !empty($_POST['clock_in']) ? $_POST['clock_in'] : null;
    $clock_out   = !empty($_POST['clock_out']) ? $_POST['clock_out'] : null;
    $remarks     = !empty($_POST['remarks']) ? $_POST['remarks'] : "Manual Entry";

    // Step 1: Find schedule
    $sched = get_schedule($mainConn, $employee_id, $work_date);
    if (!$sched) {
        set_flash_message(" No schedule found for employee on {$work_date}");
    } else {
        $schedule_id = $sched['schedule_id'];

       $empStmt = $empConn->prepare("SELECT user_id FROM employees WHERE employee_id = ? LIMIT 1");
$empStmt->bind_param('s', $employee_id);
$empStmt->execute();
$empRes = $empStmt->get_result();

if ($empRes->num_rows == 0) {
    set_flash_message("❌ No employee record found for employee_id {$employee_id}");
} else {
    $row = $empRes->fetch_assoc();
    $user_id = $row['user_id'];

    if (empty($user_id)) {
        set_flash_message("❌ This employee has no linked account (no user_id). Manual clock-in is not allowed.");
    } else {

            // Step 3: Calculate worked hours
            $workedHours = null;
            $clockInDT = $clock_outDT = null;

            if ($clock_in) $clockInDT = "$work_date $clock_in:00";
            if ($clock_out) $clockOutDT = "$work_date $clock_out:00";

            if ($clock_in && $clock_out) {
                $inDT  = new DateTime($clockInDT);
                $outDT = new DateTime($clockOutDT);
                if ($outDT < $inDT) $outDT->modify('+1 day'); // overnight
                $seconds = $outDT->getTimestamp() - $inDT->getTimestamp();
                $workedHours = max(0, $seconds / 3600);
            }

            // Step 4: Insert / Update attendance
            $attendance = get_attendance($mainConn, $schedule_id, $user_id);

            if ($attendance === null) {
                $ins = $mainConn->prepare("
                    INSERT INTO attendance (attendance_id, schedule_id, user_id, clock_in, clock_out, remarks, hours_worked)
                    VALUES (UUID(), ?, ?, ?, ?, ?, ?)
                ");
                $ins->bind_param('sssssd', $schedule_id, $user_id, $clockInDT, $clockOutDT, $remarks, $workedHours);
                $message = $ins->execute()
                    ? "Attendance added for {$work_date}"
                    : " Insert Error: " . $ins->error;
                set_flash_message($message);
            } else {
                $upd = $mainConn->prepare("
                    UPDATE attendance
                       SET clock_in = ?, clock_out = ?, remarks = ?, hours_worked = ?
                     WHERE attendance_id = ?
                     LIMIT 1
                ");
                $upd->bind_param('sssds', $clockInDT, $clockOutDT, $remarks, $workedHours, $attendance['attendance_id']);
                $message = $upd->execute()
                    ? "Attendance updated for {$work_date}"
                    : " Update Error: " . $upd->error;
                set_flash_message($message);
            }
        }
    }
}
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Time and Attendance</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="icon" type="image/png" href="../picture/logo2.png" />
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      lucide.createIcons();
    });
  </script>
</head>
<body class="h-screen overflow-hidden">
  <div class="flex h-full">
    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-y-auto">
      <main class="p-6 space-y-4">
        <div class="flex items-center justify-between border-b py-6">
          <h2 class="text-xl font-semibold text-gray-800">Time and Attendance</h2>
          <?php include '../profile.php'; ?>
        </div>
        <?php include 'timenavbar.php'; ?>
      </main>

      <!-- Attendance Table -->
      <div class="bg-white shadow-md rounded-2xl p-10 w-full mx-auto mb-2">

<!-- Dashboard Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-6">
  <?php
  // --- DASHBOARD METRICS ---

  // Total employees
  $empCountRes = $empConn->query("SELECT COUNT(*) AS total FROM employees");
  $empCount = $empCountRes->fetch_assoc()['total'] ?? 0;

  // Today's date
  $today = date("Y-m-d");

  // Total scheduled today
  $schedRes = $mainConn->prepare("
    SELECT COUNT(*) AS total_sched
    FROM employee_schedules
    WHERE work_date = ?
  ");
  $schedRes->bind_param("s", $today);
  $schedRes->execute();
  $schedData = $schedRes->get_result()->fetch_assoc();
  $totalSched = $schedData['total_sched'] ?? 0;

  // Attendance today
  $attRes = $mainConn->prepare("
    SELECT COUNT(*) AS total_att
    FROM attendance a
    JOIN employee_schedules es ON a.schedule_id = es.schedule_id
    WHERE es.work_date = ?
  ");
  $attRes->bind_param("s", $today);
  $attRes->execute();
  $attData = $attRes->get_result()->fetch_assoc();
  $totalAtt = $attData['total_att'] ?? 0;

  // Hours worked today
  $hoursRes = $mainConn->prepare("
    SELECT SUM(hours_worked) AS total_hours
    FROM attendance a
    JOIN employee_schedules es ON a.schedule_id = es.schedule_id
    WHERE es.work_date = ?
  ");
  $hoursRes->bind_param("s", $today);
  $hoursRes->execute();
  $hoursData = $hoursRes->get_result()->fetch_assoc();
  $totalHours = round($hoursData['total_hours'] ?? 0, 2);
  ?>

  <!-- Card: Employees -->
  <div class="bg-white shadow-lg rounded-2xl p-6 flex items-center justify-between">
    <div>
      <h3 class="text-gray-500 text-sm">Total Employees</h3>
      <p class="text-2xl font-bold text-gray-800"><?= $empCount ?></p>
    </div>
    <i data-lucide="users" class="w-8 h-8 text-blue-500"></i>
  </div>

  <!-- Card: Scheduled Today -->
  <div class="bg-white shadow-lg rounded-2xl p-6 flex items-center justify-between">
    <div>
      <h3 class="text-gray-500 text-sm">Scheduled Today</h3>
      <p class="text-2xl font-bold text-gray-800"><?= $totalSched ?></p>
    </div>
    <i data-lucide="calendar" class="w-8 h-8 text-green-500"></i>
  </div>

  <!-- Card: Attendance Today -->
  <div class="bg-white shadow-lg rounded-2xl p-6 flex items-center justify-between">
    <div>
      <h3 class="text-gray-500 text-sm">Attendance Today</h3>
      <p class="text-2xl font-bold text-gray-800"><?= $totalAtt ?></p>
    </div>
    <i data-lucide="clock" class="w-8 h-8 text-purple-500"></i>
  </div>

  <!-- Card: Total Hours Worked -->
  <div class="bg-white shadow-lg rounded-2xl p-6 flex items-center justify-between">
    <div>
      <h3 class="text-gray-500 text-sm">Hours Worked Today</h3>
      <p class="text-2xl font-bold text-gray-800"><?= $totalHours ?></p>
    </div>
    <i data-lucide="activity" class="w-8 h-8 text-red-500"></i>
  </div>
</div>

<script>
  // re-render lucide icons for dynamically loaded PHP content
  document.addEventListener("DOMContentLoaded", function () {
    lucide.createIcons();
  });
</script>


<?php
// Assume $roles contains the role of the currently logged-in user
// e.g., $roles = $_SESSION['user_role'];

if (in_array($roles, ['Admin', 'Manager'])): 
?>
<!-- Include jQuery and Select2 CSS/JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Manual Clock In/Out Form -->
<div class="bg-white shadow-md rounded-2xl p-6 w-full mx-auto mt-10">
  <h2 class="text-xl font-bold mb-4">Manual Clock In/Out</h2>
  
  <?php if (!empty($_SESSION['flash_message'])): ?>
  <div class="mb-4 p-3 rounded-lg 
    <?php echo strpos($_SESSION['flash_message'], '✅') !== false ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
    <?php 
      echo $_SESSION['flash_message']; 
      unset($_SESSION['flash_message']);
    ?>
  </div>
  <?php endif; ?>

  <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <!-- Select Employee -->
    <div>
      <label class="block text-sm font-medium text-gray-700">Employee</label>
      <select id="employee-select" name="employee_id" class="w-full border rounded-lg p-2" required>
        <option value="">Select Employee</option>
        <?php
        $empRes = $empConn->query("SELECT employee_id, CONCAT(first_name,' ',last_name) AS name FROM hr3_system.employees ORDER BY name");
        while ($emp = $empRes->fetch_assoc()) {
            echo "<option value='{$emp['employee_id']}'>" . htmlspecialchars($emp['name']) . "</option>";
        }
        ?>
      </select>
    </div>

    <!-- Work Date -->
    <div>
      <label class="block text-sm font-medium text-gray-700">Work Date</label>
      <input type="date" name="work_date" class="w-full border rounded-lg p-2" required>
    </div>

    <!-- Clock In -->
    <div>
      <label class="block text-sm font-medium text-gray-700">Clock In</label>
      <input type="time" name="clock_in" class="w-full border rounded-lg p-2">
    </div>

    <!-- Clock Out -->
    <div>
      <label class="block text-sm font-medium text-gray-700">Clock Out</label>
      <input type="time" name="clock_out" class="w-full border rounded-lg p-2">
    </div>

    <!-- Remarks -->
    <div class="md:col-span-2">
      <label class="block text-sm font-medium text-gray-700">Remarks</label>
      <textarea name="remarks" class="w-full border rounded-lg p-2" rows="2"></textarea>
    </div>

    <!-- Submit -->
    <div class="md:col-span-2 text-right">
      <button type="submit" name="manual_submit" class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto">
        Save Attendance
      </button>
    </div>
  </form>
</div>

<script>
  $(document).ready(function() {
    // Make the employee dropdown searchable
    $('#employee-select').select2({
      placeholder: "Select Employee",
      allowClear: true
    });
  });
</script>

<?php
// DB connections
include __DIR__ . '/../dbconnection/dbEmployee.php'; // hr3_system
$empConn = $conn;

include __DIR__ . '/../dbconnection/mainDB.php'; // hr3_maindb
$mainConn = $conn;

// Query attendance with break info and images
$sql = "
SELECT 
    e.employee_id,
    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
    d.name AS department_name,
    p.title AS position,
    u.username,
    s.work_date,
    a.clock_in,
    a.clock_out,
    a.break_in,
    a.break_out,
    a.break_violation,
    a.break_over_minutes,
    a.clock_in_image,
    a.clock_out_image,
    a.hours_worked,
    a.remarks
FROM hr3_maindb.attendance a
JOIN hr3_maindb.users u ON a.user_id = u.user_id
JOIN hr3_maindb.employee_schedules s ON a.schedule_id = s.schedule_id
JOIN hr3_system.employees e ON s.employee_id = e.employee_id
LEFT JOIN hr3_system.departments d ON e.department_id = d.department_id
LEFT JOIN hr3_system.positions p ON e.position_id = p.position_id
ORDER BY e.employee_id ASC, s.work_date DESC
";

$result = $mainConn->query($sql);
if (!$result) die("Query failed: " . $mainConn->error);

// Helper function for hours worked
function formatHoursWorked($decimalHours) {
    if ($decimalHours === null || $decimalHours === '') return '';
    $totalMinutes = round($decimalHours * 60);
    $hours = floor($totalMinutes / 60);
    $minutes = $totalMinutes % 60;
    $text = '';
    if ($hours > 0) $text .= $hours . ' hr' . ($hours > 1 ? 's ' : ' ');
    if ($minutes > 0) $text .= $minutes . ' min';
    if ($hours == 0 && $minutes == 0) $text = '0 min';
    return $text;
}
?>
<div class="overflow-x-auto p-4 bg-gray-50">
  <table class="min-w-full divide-y divide-gray-300 table-auto text-sm md:text-base">
    <thead class="bg-gray-100">
      <tr>
        <th class="px-4 py-2 text-left font-semibold text-gray-700">Employee ID</th>
        <th class="px-4 py-2 text-left font-semibold text-gray-700">Name</th>
        <th class="px-4 py-2 text-left font-semibold text-gray-700">Department</th>
        <th class="px-4 py-2 text-left font-semibold text-gray-700">Position</th>
        <th class="px-4 py-2 text-left font-semibold text-gray-700">Username</th>
        <th class="px-4 py-2 text-left font-semibold text-gray-700">Work Date</th>
        <th class="px-4 py-2 text-left font-semibold text-gray-700">Clock In</th>
        <th class="px-4 py-2 text-left font-semibold text-gray-700">Clock Out</th>
        <th class="px-4 py-2 text-left font-semibold text-gray-700">Break In</th>
        <th class="px-4 py-2 text-left font-semibold text-gray-700">Break Out</th>
        <th class="px-4 py-2 text-left font-semibold text-gray-700">Break Violation</th>
        <th class="px-4 py-2 text-left font-semibold text-gray-700">Clock In Photo</th>
        <th class="px-4 py-2 text-left font-semibold text-gray-700">Clock Out Photo</th>
        <th class="px-4 py-2 text-left font-semibold text-gray-700">Hours Worked</th>
        <th class="px-4 py-2 text-left font-semibold text-gray-700">Remarks</th>
      </tr>
    </thead>
    <tbody class="bg-white divide-y divide-gray-200">
      <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr class="<?= $row['break_violation'] ? 'bg-red-100' : '' ?>">
            <td class="px-4 py-2"><?= htmlspecialchars($row['employee_id']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($row['employee_name']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($row['department_name']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($row['position']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($row['username']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($row['work_date']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($row['clock_in'] ?? 'Not Clocked In') ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($row['clock_out'] ?? 'Not Clocked Out') ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($row['break_in'] ?? 'Not Started') ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($row['break_out'] ?? 'Not Ended') ?></td>
            <td class="px-4 py-2 text-red-600 font-semibold">
              <?= $row['break_violation'] ? "Exceeded {$row['break_over_minutes']} min" : '-' ?>
            </td>
            <td class="px-4 py-2">
              <?php if ($row['clock_in_image']): ?>
                <img src="/public_html/<?= htmlspecialchars(imageUrl($row['clock_in_image'])) ?>" 
                     class="h-20 w-20 object-cover rounded border cursor-pointer preview-img" 
                     data-src="/public_html/<?= htmlspecialchars(imageUrl($row['clock_in_image'])) ?>">
              <?php else: ?>No Photo<?php endif; ?>
            </td>
            <td class="px-4 py-2">
              <?php if ($row['clock_out_image']): ?>
                <img src="/public_html/<?= htmlspecialchars(imageUrl($row['clock_out_image'])) ?>" 
                     class="h-20 w-20 object-cover rounded border cursor-pointer preview-img" 
                     data-src="/public_html/<?= htmlspecialchars(imageUrl($row['clock_out_image'])) ?>">
              <?php else: ?>No Photo<?php endif; ?>
            </td>
            <td class="px-4 py-2"><?= htmlspecialchars(formatHoursWorked($row['hours_worked'])) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($row['remarks'] ?? '-') ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="15" class="text-center text-gray-500 py-4">No attendance records found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>


<!-- Image Preview Modal -->
<div id="imagePreviewModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center hidden z-50">
  <div class="relative">
    <button id="closeImagePreview" class="absolute top-2 right-2 text-white text-3xl font-bold">&times;</button>
    <img id="imagePreview" src="" class="max-w-[90vw] max-h-[90vh] rounded shadow-lg" alt="Preview">
  </div>
</div>

<script>
document.querySelectorAll('.preview-img').forEach(img => {
  img.addEventListener('click', function() {
    const src = this.dataset.src;
    const modal = document.getElementById('imagePreviewModal');
    document.getElementById('imagePreview').src = src;
    modal.classList.remove('hidden');
  });
});
document.getElementById('closeImagePreview').addEventListener('click', function() {
  document.getElementById('imagePreviewModal').classList.add('hidden');
});
document.getElementById('imagePreviewModal').addEventListener('click', function(e) {
  if(e.target === this) this.classList.add('hidden');
});
</script>



        <?php 
else: 
  
endif; 
?>


<?php
// Assume $roles contains the role of the currently logged-in user
// e.g., $roles = $_SESSION['user_role'];

if (in_array($roles, [ 'Employee'])): 
?>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../index.php");
    exit;
}

$loggedInEmployeeId = $_SESSION['employee_id'];

// DB connections
include __DIR__ . '/../dbconnection/dbEmployee.php'; // hr3_system
$empConn = $conn;

include __DIR__ . '/../dbconnection/mainDB.php'; // hr3_maindb
$mainConn = $conn;

date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');

// ---------------------------
// Handle Break In / Break Out
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Fetch latest ongoing attendance with shift info
    $sqlCheck = "
        SELECT a.attendance_id, a.break_in, a.break_out, a.clock_in,
               s.work_date, s.shift_id, sh.start_time, sh.end_time, sh.break_minutes
        FROM hr3_maindb.attendance a
        JOIN hr3_maindb.employee_schedules s ON a.schedule_id = s.schedule_id
        JOIN hr3_system.employees e ON s.employee_id = e.employee_id
        JOIN hr3_maindb.shifts sh ON s.shift_id = sh.shift_id
        WHERE e.employee_id = ? AND a.clock_out IS NULL
        ORDER BY a.clock_in DESC
        LIMIT 1
    ";
    $stmtCheck = $mainConn->prepare($sqlCheck);
    $stmtCheck->bind_param("s", $loggedInEmployeeId);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    $attendance = $resultCheck->fetch_assoc();

    if ($attendance) {
        // Determine shift start/end based on clock-in date
        $clockInDate = date('Y-m-d', strtotime($attendance['clock_in']));
        $shiftStart = strtotime($clockInDate . ' ' . $attendance['start_time']);
        $shiftEnd   = strtotime($clockInDate . ' ' . $attendance['end_time']);

        // Handle overnight shifts
        if ($attendance['start_time'] > $attendance['end_time']) {
            $shiftEnd += 24*3600; // add 1 day
        }

        $nowTimestamp = strtotime($now);

        if ($nowTimestamp < $shiftStart || $nowTimestamp > $shiftEnd) {
            echo "<div class='text-red-500'>Cannot record break outside shift hours ({$attendance['start_time']} - {$attendance['end_time']})</div>";
        } else {
            // Enforce break_minutes
            $breakAllowed = (int)$attendance['break_minutes'];

            if ($_POST['action'] === 'break_in' && !$attendance['break_in']) {
                if ($breakAllowed === 0) {
                    echo "<div class='text-red-500'>This shift does not allow a break.</div>";
                } else {
                    $sqlUpdate = "UPDATE hr3_maindb.attendance SET break_in=? WHERE attendance_id=? LIMIT 1";
                    $stmtUpdate = $mainConn->prepare($sqlUpdate);
                    $stmtUpdate->bind_param("ss", $now, $attendance['attendance_id']);
                    $stmtUpdate->execute();
                }
            }elseif ($_POST['action'] === 'break_out' && $attendance['break_in'] && !$attendance['break_out']) {
    if ($breakAllowed === 0) {
        echo "<div class='text-red-500'>This shift does not allow a break.</div>";
    } else {
        $breakStart = strtotime($attendance['break_in']);
        $breakDurationMinutes = ($nowTimestamp - $breakStart) / 60;

        $breakViolation = 0;
        $overMinutes = 0;

        if ($breakDurationMinutes > $breakAllowed) {
            $breakViolation = 1;
            $overMinutes = round($breakDurationMinutes - $breakAllowed, 2);
        }

        $sqlUpdate = "
            UPDATE hr3_maindb.attendance 
            SET break_out=?, break_violation=?, break_over_minutes=?
            WHERE attendance_id=? LIMIT 1
        ";
        $stmtUpdate = $mainConn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("siis", $now, $breakViolation, $overMinutes, $attendance['attendance_id']);
        $stmtUpdate->execute();

        if ($breakViolation) {
            echo "<div class='text-red-500'>Break exceeded allowed {$breakAllowed} minutes by {$overMinutes} minutes.</div>";
        }
    }
}

        }
    }
}

// ---------------------------
// Fetch all attendance for display
// ---------------------------
$sql = "
SELECT 
    e.employee_id,
    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
    d.name AS department_name,
    p.title AS position,
    u.username,
    s.work_date,
    a.attendance_id,
    a.clock_in,
    a.clock_out,
    a.break_in,
    a.break_out,
    a.clock_in_image,
    a.clock_out_image,
    a.hours_worked,
    a.remarks,
    sh.start_time AS shift_start,
    sh.end_time AS shift_end,
    sh.break_minutes
FROM hr3_maindb.attendance a
JOIN hr3_maindb.users u ON a.user_id = u.user_id
JOIN hr3_maindb.employee_schedules s ON a.schedule_id = s.schedule_id
JOIN hr3_system.employees e ON s.employee_id = e.employee_id
LEFT JOIN hr3_system.departments d ON e.department_id = d.department_id
LEFT JOIN hr3_system.positions p ON e.position_id = p.position_id
LEFT JOIN hr3_maindb.shifts sh ON s.shift_id = sh.shift_id
WHERE e.employee_id = ?
ORDER BY s.work_date DESC, a.clock_in DESC
";
$stmt = $mainConn->prepare($sql);
$stmt->bind_param("s", $loggedInEmployeeId);
$stmt->execute();
$result = $stmt->get_result();

// Fetch latest attendance for live display
$sqlToday = "
SELECT a.attendance_id, a.clock_in, a.clock_out, a.break_in, a.break_out
FROM hr3_maindb.attendance a
JOIN hr3_maindb.employee_schedules s ON a.schedule_id = s.schedule_id
JOIN hr3_system.employees e ON s.employee_id = e.employee_id
WHERE e.employee_id = ?
ORDER BY a.clock_in DESC
LIMIT 1
";
$stmtToday = $mainConn->prepare($sqlToday);
$stmtToday->bind_param("s", $loggedInEmployeeId);
$stmtToday->execute();
$resultToday = $stmtToday->get_result();
$latest = $resultToday->fetch_assoc() ?? [];
$clockInTime = $latest['clock_in'] ?? null;
$clockOutTime = $latest['clock_out'] ?? null;
$breakInTime = $latest['break_in'] ?? null;
$breakOutTime = $latest['break_out'] ?? null;
?>

<div class="overflow-x-auto p-4 bg-gray-50 min-h-screen">
  <h2 class="text-2xl font-bold mb-4 text-gray-800">My Attendance Log</h2>

  <div class="mb-6 p-4 bg-white shadow rounded-lg border border-gray-200">
      <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
          <div>
              <span class="font-semibold text-gray-700">Today's Clock In:</span>
              <span class="text-gray-900"><?= htmlspecialchars($clockInTime ?? 'Not Clocked In') ?></span>
          </div>
          <div>
              <span class="font-semibold text-gray-700">Hours Worked So Far:</span>
              <span id="liveHoursWorked" class="text-gray-900">0 min</span>
          </div>
      </div>
  </div>


  <script>
  const clockInTime = "<?= $clockInTime ?? '' ?>";
  const clockOutTime = "<?= $clockOutTime ?? '' ?>";
  const breakInTime = "<?= $breakInTime ?? '' ?>";
  const breakOutTime = "<?= $breakOutTime ?? '' ?>";

  function getTimeDiffInSeconds(start, end = null) {
      if (!start) return 0;
      const s = new Date(start.replace(' ', 'T'));
      const e = end ? new Date(end.replace(' ', 'T')) : new Date();
      return Math.floor((e - s) / 1000);
  }

  function formatDuration(seconds) {
      const hours = Math.floor(seconds / 3600);
      seconds %= 3600;
      const minutes = Math.floor(seconds / 60);
      const secs = seconds % 60;
      let text = '';
      if (hours) text += hours + ' hr' + (hours>1?'s ':' ');
      if (minutes) text += minutes + ' min ';
      text += secs + ' sec';
      return text;
  }

  function updateLiveHours() {
      if (clockInTime) {
          let totalSeconds = getTimeDiffInSeconds(clockInTime, clockOutTime);
          if (breakInTime) {
              totalSeconds -= getTimeDiffInSeconds(breakInTime, breakOutTime);
          }
          document.getElementById('liveHoursWorked').textContent = formatDuration(totalSeconds);
      }
  }

  updateLiveHours();
  setInterval(updateLiveHours, 1000);

  // Handle Break In / Break Out buttons
  document.addEventListener('click', function(e){
      if(e.target.classList.contains('break-in-btn')){
          fetch('', {
              method: 'POST',
              headers: {'Content-Type':'application/x-www-form-urlencoded'},
              body: 'action=break_in'
          }).then(()=>location.reload());
      }
      if(e.target.classList.contains('break-out-btn')){
          fetch('', {
              method: 'POST',
              headers: {'Content-Type':'application/x-www-form-urlencoded'},
              body: 'action=break_out'
          }).then(()=>location.reload());
      }
  });
  </script>
  <form method="POST" class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 table-auto">
          <thead class="bg-gray-100 sticky top-0 z-10">
              <tr>
                  <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Work Date</th>
                  <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Clock In</th>
                  <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Clock Out</th>
                  <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Break In</th>
                  <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Break Out</th>
                  <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Break Actions</th>
                  <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Clock In Photo</th>
                  <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Clock Out Photo</th>
                  <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Hours Worked</th>
                  <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Remarks</th>
              </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
          <?php
          function formatHoursWorked($decimalHours){
              if($decimalHours===null||$decimalHours==='') return '';
              $totalMinutes = round($decimalHours*60);
              $hours = floor($totalMinutes/60);
              $minutes = $totalMinutes%60;
              $text = '';
              if($hours>0) $text .= $hours.' hr'.($hours>1?'s ':' ');
              if($minutes>0) $text .= $minutes.' min';
              if($hours==0 && $minutes==0) $text='0 min';
              return $text;
          }

          if($result->num_rows>0):
              while($row=$result->fetch_assoc()):
          ?>
              <tr class="hover:bg-gray-50">
                  <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap"><?= htmlspecialchars($row['work_date'] ?? '') ?></td>
                  <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap"><?= htmlspecialchars($row['clock_in'] ?? '') ?></td>
                  <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap"><?= htmlspecialchars($row['clock_out'] ?? '') ?></td>
                  <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap"><?= htmlspecialchars($row['break_in'] ?? 'Not Started') ?></td>
                  <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap"><?= htmlspecialchars($row['break_out'] ?? 'Not Ended') ?></td>
                  <td class="px-4 py-2 text-sm whitespace-nowrap">
                      <?php if(!$row['break_in']): ?>
                          <button type="submit" name="action" value="break_in" class="px-2 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-sm">Break In</button>
                      <?php elseif(!$row['break_out']): ?>
                          <button type="submit" name="action" value="break_out" class="px-2 py-1 bg-green-500 hover:bg-green-600 text-white rounded text-sm">Break Out</button>
                      <?php else: ?>
                          <span class="text-gray-500 text-sm">Completed</span>
                      <?php endif; ?>
                  </td>
               <td class="px-4 py-2 whitespace-nowrap">
    <?php if($row['clock_in_image']): ?>
        <img 
            src="/public_html/<?= htmlspecialchars(imageUrl($row['clock_in_image'])) ?>" 
            data-src="/public_html/<?= htmlspecialchars(imageUrl($row['clock_in_image'])) ?>"
            class="h-16 w-16 object-cover rounded border cursor-pointer preview-img"
            alt="Clock In Photo"
        >
    <?php else: ?>
        <span class="text-gray-400 text-sm">No Photo</span>
    <?php endif; ?>
</td>

<td class="px-4 py-2 whitespace-nowrap">
    <?php if($row['clock_out_image']): ?>
        <img 
            src="/public_html/<?= htmlspecialchars(imageUrl($row['clock_out_image'])) ?>" 
            data-src="/public_html/<?= htmlspecialchars(imageUrl($row['clock_out_image'])) ?>"
            class="h-16 w-16 object-cover rounded border cursor-pointer preview-img"
            alt="Clock Out Photo"
        >
    <?php else: ?>
        <span class="text-gray-400 text-sm">No Photo</span>
    <?php endif; ?>
</td>

                  <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap"><?= htmlspecialchars(formatHoursWorked($row['hours_worked'])) ?></td>
                  <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
              </tr>
          <?php
              endwhile;
          else: ?>
              <tr>
                  <td colspan="10" class="text-center text-gray-500 py-4">No attendance records found.</td>
              </tr>
          <?php endif; ?>
          </tbody>
      </table>
    </div>
  </form>
</div>


<!-- Image Preview Modal -->
<div id="imagePreviewModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center hidden z-50">
  <div class="relative">
    <button id="closeImagePreview" class="absolute top-2 right-2 text-white text-3xl font-bold">&times;</button>
    <img id="imagePreview" src="" class="max-w-[90vw] max-h-[90vh] rounded shadow-lg" alt="Preview">
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('imagePreviewModal');
    const modalImg = document.getElementById('imagePreview');
    const closeBtn = document.getElementById('closeImagePreview');

    // Open modal when any preview image is clicked
    document.querySelectorAll('.preview-img').forEach(img => {
        img.addEventListener('click', function() {
            modalImg.src = this.dataset.src;
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden'); // optional: prevent background scroll
        });
    });

    // Close modal
    const closeModal = () => {
        modal.classList.add('hidden');
        modalImg.src = '';
        document.body.classList.remove('overflow-hidden');
    };

    closeBtn.addEventListener('click', closeModal);

    // Close modal on outside click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    // Close modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
});
</script>



        <?php 
else: 
  
endif; 
?>


</body>
</html>
