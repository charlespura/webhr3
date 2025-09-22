<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

use Mpdf\Mpdf;
require_once __DIR__ . '/../vendor/autoload.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get session info
$roles = $_SESSION['roles'] ?? 'Employee';
$roleArray = explode(',', $roles);
$role = trim($roleArray[0]);

$userName = $_SESSION['user_name'] ?? 'Guest';
$userId = $_SESSION['user_id'] ?? null;
$employeeId = $_SESSION['employee_id'] ?? null;

date_default_timezone_set('Asia/Manila');

// DB connections
include __DIR__ . '/../dbconnection/dbEmployee.php';
$empConn = $conn;
include __DIR__ . '/../dbconnection/mainDB.php';
$mainConn = $conn;

// Auto-sync daily timesheet
$syncSql = "
INSERT INTO hr3_maindb.timesheet (
    timesheet_id, user_id, schedule_id, period_start, period_end, total_hours, break_hours, status
)
SELECT 
    UUID(),
    a.user_id,
    a.schedule_id,
    DATE(a.clock_in) AS period_start,
    DATE(a.clock_in) AS period_end,
    SUM(a.hours_worked) AS total_hours,
    IFNULL(SUM(TIMESTAMPDIFF(MINUTE, a.break_in, a.break_out)) / 60, 0) AS break_hours,
    'Pending' AS status
FROM hr3_maindb.attendance a
WHERE a.clock_in IS NOT NULL
GROUP BY a.user_id, a.schedule_id, DATE(a.clock_in)
ON DUPLICATE KEY UPDATE
    schedule_id = VALUES(schedule_id),
    total_hours = VALUES(total_hours),
    break_hours = VALUES(break_hours),
    updated_at = NOW()
";
$mainConn->query($syncSql);

// Handle single Approve/Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['timesheet_action'], $_POST['timesheet_id'])) {
    $timesheet_id = $_POST['timesheet_id'];
    $action = $_POST['timesheet_action'];

    $stmt = $mainConn->prepare("UPDATE timesheet SET status = ? WHERE timesheet_id = ?");
    $stmt->bind_param('ss', $action, $timesheet_id);
    $stmt->execute();
}

// Handle bulk Approve/Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'], $_POST['timesheet_ids'])) {
    $bulk_action = $_POST['bulk_action'];
    $ids = $_POST['timesheet_ids'];

    if (!empty($ids) && in_array($bulk_action, ['Approved', 'Rejected'])) {
        $escaped_ids = array_map(function($id) use ($mainConn) {
            return "'" . $mainConn->real_escape_string($id) . "'";
        }, $ids);
        $ids_list = implode(',', $escaped_ids);
        $mainConn->query("UPDATE timesheet SET status='$bulk_action' WHERE timesheet_id IN ($ids_list)");
    }
}

// --- FILTERS ---
$filters = [];
$where = "";

// Employee filter
$employeeSearch = $_GET['employee_name'] ?? '';
if ($role === 'Employee') {
    $filters[] = "t.user_id = '".$mainConn->real_escape_string($userId)."'";
} elseif ($employeeSearch) {
    $filters[] = "CONCAT(e.first_name,' ',e.last_name) LIKE '%".$mainConn->real_escape_string($employeeSearch)."%'";
}

// Department filter
$selectedDept = $_GET['department'] ?? '';
if ($selectedDept) $filters[] = "e.department_id = '".$mainConn->real_escape_string($selectedDept)."'";

// Position filter
$selectedPos = $_GET['position'] ?? '';
if ($selectedPos) $filters[] = "e.position_id = '".$mainConn->real_escape_string($selectedPos)."'";

// Status filter
$selectedStatus = $_GET['status'] ?? '';
if ($selectedStatus) $filters[] = "t.status = '".$mainConn->real_escape_string($selectedStatus)."'";

// Date range filter
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
if ($startDate && $endDate) {
    $filters[] = "t.period_start BETWEEN '".$mainConn->real_escape_string($startDate)."' AND '".$mainConn->real_escape_string($endDate)."'";
} elseif ($startDate) {
    $filters[] = "t.period_start >= '".$mainConn->real_escape_string($startDate)."'";
} elseif ($endDate) {
    $filters[] = "t.period_start <= '".$mainConn->real_escape_string($endDate)."'";
}

if (!empty($filters)) {
    $where = "WHERE " . implode(" AND ", $filters);
}

// Fetch timesheets
$sql = "
SELECT 
    t.*, 
    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
    d.name AS department_name,
    p.title AS position_title
FROM hr3_maindb.timesheet t
INNER JOIN hr3_system.employees e ON t.user_id = e.user_id
LEFT JOIN hr3_system.departments d ON e.department_id = d.department_id
LEFT JOIN hr3_system.positions p ON e.position_id = p.position_id
$where
ORDER BY t.period_start DESC
";
$res = $mainConn->query($sql);

// Fetch all departments and positions
$deptRes = $mainConn->query("SELECT department_id, name FROM hr3_system.departments");
$posRes = $mainConn->query("SELECT position_id, title FROM hr3_system.positions");
// Function to generate PDF HTML
function generateTimesheetHTML($res) {
    $totalWorkedHours = 0; // Initialize cumulative total
    $html = '
    <div style="text-align:center; margin-bottom:20px;">
        <img src="../picture/logo2.png" style="width:100px; height:auto; margin-bottom:10px;">
        <div style="font-size:18px; font-weight:bold;">ATIÉRA Hotel and Restaurant</div>
    </div>
    <h2 style="text-align:center; margin-bottom:15px;">Timesheet Report</h2>
    <table border="1" cellpadding="5" cellspacing="0" width="100%">
        <thead>
            <tr style="background-color:#f2f2f2;">
                <th>Employee</th><th>Department</th><th>Position</th><th>Period</th>
                <th>Total Hours</th><th>Overtime</th><th>Undertime</th><th>Break</th><th>Status</th>
            </tr>
        </thead>
        <tbody>';

    if ($res && $res->num_rows > 0) {
        while($row = $res->fetch_assoc()) {
            $totalHours = floatval($row['total_hours']);
            $totalWorkedHours += $totalHours; // accumulate total

            $hours = floor($totalHours);
            $minutes = round(($totalHours - $hours) * 60);

            $html .= '<tr>
                <td>'.htmlspecialchars($row['employee_name']).'</td>
                <td>'.htmlspecialchars($row['department_name'] ?? 'N/A').'</td>
                <td>'.htmlspecialchars($row['position_title'] ?? 'N/A').'</td>
                <td>'.htmlspecialchars($row['period_start']).' → '.htmlspecialchars($row['period_end']).'</td>
                <td>'.$hours.' hrs '.$minutes.' mins</td>
                <td>'.($row['overtime_hours'] ?? 0).' hrs</td>
                <td>'.($row['undertime_hours'] ?? 0).' hrs</td>
                <td>'.($row['break_hours'] ?? 0).' hrs</td>
                <td>'.htmlspecialchars($row['status']).'</td>
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="9" style="text-align:center;">No records found</td></tr>';
    }

    $html .= '</tbody></table>';

    // Add cumulative total hours at the bottom
    $totalHrs = floor($totalWorkedHours);
    $totalMins = round(($totalWorkedHours - $totalHrs) * 60);

    $html .= '<p style="text-align:right; font-weight:bold; margin-top:10px;">
                Total Hours Worked: '.$totalHrs.' hrs '.$totalMins.' mins
              </p>';

    return $html;
}

// --- HANDLE PDF VIEW ---
if(isset($_GET['view_pdf'])){
    $html = generateTimesheetHTML($res);
    // Add buttons inside the PDF view (optional)
    $html .= '
    <div style="margin-top:20px; text-align:center;">
        <a href="timesheet.php" style="padding:8px 15px; background:#3490dc; color:#fff; text-decoration:none; border-radius:4px;">Back</a>
        <a href="timesheet.php?download_pdf=1" style="padding:8px 15px; background:#38c172; color:#fff; text-decoration:none; border-radius:4px;">Download PDF</a>
    </div>';

    $mpdf = new Mpdf(['format'=>'A4','margin_left'=>10,'margin_right'=>10,'margin_top'=>15,'margin_bottom'=>15,'tempDir'=>__DIR__.'/../tmp']);
    $mpdf->WriteHTML($html);
    $mpdf->Output('timesheet_report.pdf','I');
    exit;
}

// --- HANDLE PDF DOWNLOAD ---
if(isset($_GET['download_pdf'])){
    $html = generateTimesheetHTML($res); // <-- generate HTML again for download

    $mpdf = new Mpdf(['format'=>'A4','margin_left'=>10,'margin_right'=>10,'margin_top'=>15,'margin_bottom'=>15,'tempDir'=>__DIR__.'/../tmp']);
    $mpdf->WriteHTML($html);
    $mpdf->Output('timesheet_report.pdf','D'); // Force download
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Timesheet</title>
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
    <?php include '../sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-y-auto">
        <main class="p-6 space-y-4">
            <div class="flex items-center justify-between border-b py-6">
                <h2 class="text-xl font-semibold text-gray-800">Timesheet</h2>
                <?php include '../profile.php'; ?>
            </div>

            <?php include 'timesheetnavbar.php'; ?>

         <div class="bg-white shadow-md rounded-2xl p-6 md:p-10 w-full mx-auto mt-6 mb-10">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold mb-4 flex items-center gap-2">
            <i data-lucide="clock"></i>
            Timesheet Management (<?= htmlspecialchars($role) ?>)
        </h1>

        <?php $canEdit = in_array($role, ['Manager', 'Admin']); ?>

      <!-- FILTER FORM -->
<form method="GET" class="mb-6 flex flex-wrap gap-2 items-end" id="filterForm">
    <div class="flex flex-col">
        <label class="text-sm">Employee</label>
        <input 
            type="text" 
            name="employee_name" 
            class="border rounded px-2 py-1 w-full md:w-48" 
            placeholder="Type employee..." 
            value="<?= htmlspecialchars($_GET['employee_name'] ?? '') ?>"
            id="employeeInput"
        >
    </div>
    <div class="flex flex-col">
        <label class="text-sm">Department</label>
        <select name="department" class="border rounded px-2 py-1 w-full md:w-48" onchange="this.form.submit()">
            <option value="">All</option>
            <?php $deptRes->data_seek(0); while($dept = $deptRes->fetch_assoc()): ?>
                <option value="<?= $dept['department_id'] ?>" <?= $selectedDept==$dept['department_id']?'selected':'' ?>>
                    <?= htmlspecialchars($dept['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="flex flex-col">
        <label class="text-sm">Position</label>
        <select name="position" class="border rounded px-2 py-1 w-full md:w-48" onchange="this.form.submit()">
            <option value="">All</option>
            <?php $posRes->data_seek(0); while($pos = $posRes->fetch_assoc()): ?>
                <option value="<?= $pos['position_id'] ?>" <?= $selectedPos==$pos['position_id']?'selected':'' ?>>
                    <?= htmlspecialchars($pos['title']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="flex flex-col">
        <label class="text-sm">Status</label>
        <select name="status" class="border rounded px-2 py-1 w-full md:w-36" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="Pending" <?= $selectedStatus=='Pending'?'selected':'' ?>>Pending</option>
            <option value="Approved" <?= $selectedStatus=='Approved'?'selected':'' ?>>Approved</option>
            <option value="Rejected" <?= $selectedStatus=='Rejected'?'selected':'' ?>>Rejected</option>
        </select>
    </div>
    <div class="flex flex-col">
        <label class="text-sm">Start Date</label>
        <input type="date" name="start_date" class="border rounded px-2 py-1 w-full md:w-40" 
               value="<?= htmlspecialchars($startDate) ?>" onchange="this.form.submit()">
    </div>
    <div class="flex flex-col">
        <label class="text-sm">End Date</label>
        <input type="date" name="end_date" class="border rounded px-2 py-1 w-full md:w-40" 
               value="<?= htmlspecialchars($endDate) ?>" onchange="this.form.submit()">
    </div>
    <div class="flex flex-col gap-1">
        <button type="submit" name="view_pdf" value="1" class="bg-green-500 text-white px-3 py-1 rounded w-full md:w-auto">View PDF</button>
        <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" 
           class="bg-gray-500 text-white px-3 py-1 rounded w-full md:w-auto text-center">
           Clear Filters
        </a>
    </div>
</form>

<script>
// Auto-submit employee text field after typing (debounce 500ms)
let typingTimer;
document.getElementById("employeeInput").addEventListener("keyup", function() {
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        document.getElementById("filterForm").submit();
    }, 500);
});
</script>


        <!-- BULK ACTION -->
        <?php if ($canEdit): ?>
        <form method="POST">
            <div class="mb-4 flex flex-wrap gap-2 items-center">
                <select name="bulk_action" class="border rounded px-2 py-1">
                    <option value="">Bulk Action</option>
                    <option value="Approved">Approve Selected</option>
                    <option value="Rejected">Reject Selected</option>
                </select>
                <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded">Apply</button>
            </div>
        <?php endif; ?>

        <!-- TIMESHEET TABLE -->
        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <?php $totalWorkedHours = 0; ?>
            <table class="w-full text-sm text-left text-gray-700 min-w-[700px]">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                    <tr>
                        <?php if ($canEdit): ?>
                        <th class="px-4 py-3"><input type="checkbox" id="select_all"></th>
                        <?php endif; ?>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Period</th>
                        <th class="px-4 py-3">Total Hours</th>
                        <th class="px-4 py-3">Overtime</th>
                        <th class="px-4 py-3">Undertime</th>
                        <th class="px-4 py-3">Break</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($res && $res->num_rows > 0): ?>
                        <?php while($row = $res->fetch_assoc()): ?>
                            <?php
                                $totalHours = floatval($row['total_hours']);
                                $totalWorkedHours += $totalHours;
                                $hours = floor($totalHours);
                                $minutes = round(($totalHours - $hours) * 60);
                            ?>
                            <tr class="border-b hover:bg-gray-50">
                                <?php if ($canEdit): ?>
                                    <td class="px-4 py-3">
                                        <input type="checkbox" name="timesheet_ids[]" value="<?= $row['timesheet_id'] ?>">
                                    </td>
                                <?php endif; ?>
                                <td class="px-4 py-3">
                                    <div class="text-xs text-gray-500">
                                        <?= htmlspecialchars($row['department_name'] ?? 'N/A') ?> • <?= htmlspecialchars($row['position_title'] ?? 'N/A') ?>
                                    </div>
                                    <?= htmlspecialchars($row['employee_name']) ?>
                                </td>
                                <td class="px-4 py-3"><?= htmlspecialchars($row['period_start']) ?> → <?= htmlspecialchars($row['period_end']) ?></td>
                                <td class="px-4 py-3"><?= $hours ?> hrs <?= $minutes ?> mins</td>
                                <td class="px-4 py-3 text-green-600"><?= $row['overtime_hours'] ?? 0 ?> hrs</td>
                                <td class="px-4 py-3 text-red-600"><?= $row['undertime_hours'] ?? 0 ?> hrs</td>
                                <td class="px-4 py-3"><?= $row['break_hours'] ?? 0 ?> hrs</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium
                                        <?= $row['status']=='Approved' ? 'bg-green-100 text-green-700' : 
                                           ($row['status']=='Rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($canEdit): ?>
                                        <form method="POST" class="flex gap-2">
                                            <input type="hidden" name="timesheet_id" value="<?= $row['timesheet_id'] ?>">
                                            <button type="submit" name="timesheet_action" value="Approved" class="text-green-600 hover:text-green-800" title="Approve">
                                                <i data-lucide="check-circle"></i>
                                            </button>
                                            <button type="submit" name="timesheet_action" value="Rejected" class="text-red-600 hover:text-red-800" title="Reject">
                                                <i data-lucide="x-circle"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">No Actions</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $canEdit ? 9 : 8 ?>" class="px-4 py-6 text-center text-gray-500">No timesheet records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($role === 'Employee'): ?>
                <?php
                    $totalHrs = floor($totalWorkedHours);
                    $totalMins = round(($totalWorkedHours - $totalHrs) * 60);
                ?>
                <p class="mt-4 font-bold text-right">
                    Total Hours Worked: <?= $totalHrs ?> hrs <?= $totalMins ?> mins
                </p>
            <?php endif; ?>
        </div>

        <?php if ($canEdit): ?>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
    // Select all checkboxes
    document.getElementById('select_all')?.addEventListener('change', function(e){
        document.querySelectorAll('input[name="timesheet_ids[]"]').forEach(cb => cb.checked = e.target.checked);
    });
</script>


<script>
// Select/Deselect all checkboxes
const selectAll = document.getElementById('select_all');
if (selectAll) {
    selectAll.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[name="timesheet_ids[]"]');
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
    });
}
</script>
</body>
</html>
