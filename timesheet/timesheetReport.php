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

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['roles'] ?? 'Employee';
$roleArray = explode(',', $role);
$role = trim($roleArray[0]);

date_default_timezone_set('Asia/Manila');

// DB connections
include __DIR__ . '/../dbconnection/mainDB.php';
$mainConn = $conn;

// Fetch employees for autocomplete
$employeeList = [];
$empRes = $mainConn->query("SELECT user_id, CONCAT(first_name,' ',last_name) as full_name FROM hr3_system.employees ORDER BY first_name");
while($emp = $empRes->fetch_assoc()) {
    $employeeList[] = $emp;
}

// Handle search filters
$selectedUserId = $_GET['employee_id'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$where = [];

if ($selectedUserId) {
    $where[] = "t.user_id='".$mainConn->real_escape_string($selectedUserId)."'";
}
if ($startDate && $endDate) {
    $where[] = "t.period_start BETWEEN '".$mainConn->real_escape_string($startDate)."' AND '".$mainConn->real_escape_string($endDate)."'";
} elseif ($startDate) {
    $where[] = "t.period_start >= '".$mainConn->real_escape_string($startDate)."'";
} elseif ($endDate) {
    $where[] = "t.period_start <= '".$mainConn->real_escape_string($endDate)."'";
}

$whereSQL = '';
if (!empty($where)) $whereSQL = 'WHERE ' . implode(' AND ', $where);

// Fetch timesheet records
$sql = "
SELECT t.*, CONCAT(e.first_name,' ',e.last_name) AS employee_name
FROM hr3_maindb.timesheet t
INNER JOIN hr3_system.employees e ON t.user_id = e.user_id
$whereSQL
ORDER BY t.period_start DESC
";
$res = $mainConn->query($sql);

// Calculate total hours
$totalHours = 0;
if($res && $res->num_rows>0){
    while($row=$res->fetch_assoc()){
        $totalHours += floatval($row['total_hours']);
        $rows[] = $row;
    }
} else {
    $rows = [];
}

// Generate PDF HTML
function generateReportHTML($rows, $employeeName, $startDate, $endDate, $totalHours) {
    $html = '<div style="text-align:center; margin-bottom:20px;">
    <img src="../picture/logo2.png" style="width:100px; height:auto; margin-bottom:10px;">
    <div style="font-size:18px; font-weight:bold;">ATIÉRA Hotel and Restaurant</div>
    </div>';

    $html .= '<h2 style="text-align:center; margin-bottom:15px;">Timesheet Report</h2>';
    $html .= '<p><strong>Employee:</strong> '.htmlspecialchars($employeeName).' &nbsp; | &nbsp; <strong>Period:</strong> '.htmlspecialchars($startDate).' → '.htmlspecialchars($endDate).'</p>';

    $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">
    <thead>
    <tr style="background-color:#f2f2f2;">
        <th>Period</th><th>Total Hours</th><th>Overtime</th><th>Undertime</th><th>Break</th><th>Status</th>
    </tr>
    </thead>
    <tbody>';

    if($rows){
        foreach($rows as $row){
            $hours = floor($row['total_hours']);
            $minutes = round(($row['total_hours'] - $hours)*60);
            $html .= '<tr>
<td>'.htmlspecialchars($row['period_start']).' → '.htmlspecialchars($row['period_end']).'</td>
<td>'.$hours.' hrs '.$minutes.' mins</td>
<td>'.($row['overtime_hours'] ?? 0).' hrs</td>
<td>'.($row['undertime_hours'] ?? 0).' hrs</td>
<td>'.($row['break_hours'] ?? 0).' hrs</td>
<td>'.htmlspecialchars($row['status']).'</td>
</tr>';
        }
    } else {
        $html .= '<tr><td colspan="6" style="text-align:center;">No records found</td></tr>';
    }

    $html .= '</tbody></table>';
    $html .= '<p style="text-align:right; font-weight:bold; margin-top:10px;">Total Hours Worked: '.$totalHours.' hrs</p>';

    return $html;
}

// Handle PDF download
if(isset($_GET['download_pdf']) && $selectedUserId){
    $employeeName = '';
    foreach($employeeList as $emp){
        if($emp['user_id']==$selectedUserId){
            $employeeName = $emp['full_name'];
            break;
        }
    }
    $html = generateReportHTML($rows, $employeeName, $startDate, $endDate, $totalHours);
    $mpdf = new Mpdf(['format'=>'A4','margin_left'=>10,'margin_right'=>10,'margin_top'=>15,'margin_bottom'=>15,'tempDir'=>__DIR__.'/../tmp']);
    $mpdf->WriteHTML($html);
      // Sanitize employee name for filename (remove spaces/special chars)
$employeeFileName = preg_replace("/[^a-zA-Z0-9_-]/", "_", $employeeName);
$mpdf->Output("timesheet_report_{$employeeFileName}.pdf", 'D');

    exit;

}
?>



<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Timesheet  </title>
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

  <!-- FLEX LAYOUT: Sidebar + Main -->
  <div class="flex h-full">

    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-y-auto">

      <!-- Main Top Header (inside content) -->
      <main class="p-6 space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between border-b py-6">
          <!-- Left: Title -->
          <h2 class="text-xl font-semibold text-gray-800" id="main-content-title">Timesheet  </h2>

  <?php include '../profile.php'; ?>


        </div>

        
<?php 
include 'timesheetnavbar.php'; ?>


        <!-- Page Body -->
        <p class="text-gray-600"></p>
      </main>
     

      
<div class="bg-white shadow-md rounded-2xl p-10 w-full mx-auto mt-10 mb-10">








<h1 class="text-2xl font-bold mb-4">Timesheet Report</h1>

<form method="GET" class="mb-4 flex gap-2">
    <div>
        <label>Employee:</label>
        <input list="employees" name="employee_id" class="border px-2 py-1 rounded" required>
        <datalist id="employees">
            <?php foreach($employeeList as $emp): ?>
                <option value="<?= $emp['user_id'] ?>"><?= htmlspecialchars($emp['full_name']) ?></option>
            <?php endforeach; ?>
        </datalist>
    </div>
    <div>
        <label>Start Date:</label>
        <input type="date" name="start_date" class="border px-2 py-1 rounded" value="<?= htmlspecialchars($startDate) ?>">
    </div>
    <div>
        <label>End Date:</label>
        <input type="date" name="end_date" class="border px-2 py-1 rounded" value="<?= htmlspecialchars($endDate) ?>">
    </div>
    <div class="flex flex-col justify-end">
        <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded">Search</button>
    </div>
</form>

<?php if($selectedUserId): ?>
    <h2 class="text-xl font-semibold mb-2">Timesheet Records</h2>
    <table class="w-full border border-gray-300 text-left">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-3 py-2">Period</th>
                <th class="px-3 py-2">Total Hours</th>
                <th class="px-3 py-2">Overtime</th>
                <th class="px-3 py-2">Undertime</th>
                <th class="px-3 py-2">Break</th>
                <th class="px-3 py-2">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if($rows): ?>
                <?php foreach($rows as $row): 
                    $hours = floor($row['total_hours']);
                    $minutes = round(($row['total_hours'] - $hours)*60);
                ?>
                <tr class="border-t">
                    <td class="px-3 py-2"><?= htmlspecialchars($row['period_start']) ?> → <?= htmlspecialchars($row['period_end']) ?></td>
                    <td class="px-3 py-2"><?= $hours ?> hrs <?= $minutes ?> mins</td>
                    <td class="px-3 py-2"><?= $row['overtime_hours'] ?? 0 ?> hrs</td>
                    <td class="px-3 py-2"><?= $row['undertime_hours'] ?? 0 ?> hrs</td>
                    <td class="px-3 py-2"><?= $row['break_hours'] ?? 0 ?> hrs</td>
                    <td class="px-3 py-2"><?= htmlspecialchars($row['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="px-3 py-4 text-center">No records found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
   <?php
$totalHoursInt = floor($totalHours); // whole hours
$totalMinutes = round(($totalHours - $totalHoursInt) * 60); // remaining minutes
?>
<p class="mt-2 font-bold">Total Hours Worked: <?= $totalHoursInt ?> hrs <?= $totalMinutes ?> mins</p>

    <a href="?employee_id=<?= $selectedUserId ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&download_pdf=1" class="mt-3 inline-block bg-green-500 text-white px-4 py-2 rounded">Download PDF</a>
<?php endif; ?>

</body>
</html>
