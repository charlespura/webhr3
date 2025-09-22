

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Shift and Schedule</title>
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
          <h2 class="text-xl font-semibold text-gray-800" id="main-content-title">Shift and Schedule</h2>


          <!-- ito yung profile ng may login wag kalimutan lagyan ng session yung profile.php para madetect nya if may login or wala -->
<?php include '../profile.php'; ?>

        </div>
<!-- Second Header: Submodules -->


<?php 
include 'shiftnavbar.php'; ?>



<div class="bg-white shadow-md rounded-2xl p-10 w-full mx-auto mt-10 mb-10">
    <h2 class="text-2xl font-bold mb-6">Employee Shift Schedule</h2>

<?php
// Include employee DB connection
include __DIR__ . '/../dbconnection/dbEmployee.php';
$empConn = $conn; // Employee DB connection

// Include shift DB connection
include __DIR__ . '/../dbconnection/mainDB.php';
$shiftConn = $conn; // Shift DB connection

// Capture filters
$filter_date    = $_GET['filter_date']    ?? '';
$filter_emp     = $_GET['filter_emp']     ?? '';
$filter_shift   = $_GET['filter_shift']   ?? '';
$filter_status  = $_GET['filter_status']  ?? '';
$filter_notes   = $_GET['filter_notes']   ?? '';

// Build query with filters
$sql = "
    SELECT es.schedule_id, es.work_date, es.status, es.notes,
           e.first_name, e.last_name,
           s.shift_code, s.name AS shift_name, s.start_time, s.end_time
    FROM employee_schedules es
    JOIN hr3_system.employees e ON es.employee_id = e.employee_id
    JOIN shifts s ON es.shift_id = s.shift_id
    WHERE 1=1
";

if ($filter_date)   $sql .= " AND es.work_date = '" . $shiftConn->real_escape_string($filter_date) . "'";
if ($filter_emp)    $sql .= " AND (e.first_name LIKE '%" . $shiftConn->real_escape_string($filter_emp) . "%' 
                              OR e.last_name LIKE '%" . $shiftConn->real_escape_string($filter_emp) . "%')";
if ($filter_shift)  $sql .= " AND (s.shift_code LIKE '%" . $shiftConn->real_escape_string($filter_shift) . "%' 
                              OR s.name LIKE '%" . $shiftConn->real_escape_string($filter_shift) . "%')";
if ($filter_status) $sql .= " AND es.status LIKE '%" . $shiftConn->real_escape_string($filter_status) . "%'";
if ($filter_notes)  $sql .= " AND es.notes LIKE '%" . $shiftConn->real_escape_string($filter_notes) . "%'";

$sql .= " ORDER BY es.work_date DESC, e.first_name";
$result = $shiftConn->query($sql);
?>

<!-- 🔎 Filter Form -->
<form method="get" class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
    <input type="date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>" 
           class="border rounded p-2" placeholder="Date">

    <input type="text" name="filter_emp" value="<?php echo htmlspecialchars($filter_emp); ?>" 
           class="border rounded p-2" placeholder="Employee">

    <input type="text" name="filter_shift" value="<?php echo htmlspecialchars($filter_shift); ?>" 
           class="border rounded p-2" placeholder="Shift">

    <input type="text" name="filter_status" value="<?php echo htmlspecialchars($filter_status); ?>" 
           class="border rounded p-2" placeholder="Status">

    <input type="text" name="filter_notes" value="<?php echo htmlspecialchars($filter_notes); ?>" 
           class="border rounded p-2" placeholder="Notes">

    <div class="flex space-x-2">
        <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto">Filter</button>
        <a href="viewShift.php" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded">Reset</a>
    </div>
</form>

<?php
// Include employee DB connection
include __DIR__ . '/../dbconnection/dbEmployee.php';
$empConn = $conn; // Employee DB connection

// Include shift DB connection
include __DIR__ . '/../dbconnection/mainDB.php';
$shiftConn = $conn; // Shift DB connection

// Capture filters
$filter_date    = $_GET['filter_date']    ?? '';
$filter_emp     = $_GET['filter_emp']     ?? '';
$filter_shift   = $_GET['filter_shift']   ?? '';
$filter_status  = $_GET['filter_status']  ?? '';
$filter_notes   = $_GET['filter_notes']   ?? '';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

// Count total records for pagination
$countSql = "
    SELECT COUNT(*) AS total
    FROM employee_schedules es
    JOIN hr3_system.employees e ON es.employee_id = e.employee_id
    JOIN shifts s ON es.shift_id = s.shift_id
    WHERE 1=1
";
if ($filter_date)   $countSql .= " AND es.work_date = '" . $shiftConn->real_escape_string($filter_date) . "'";
if ($filter_emp)    $countSql .= " AND (e.first_name LIKE '%" . $shiftConn->real_escape_string($filter_emp) . "%' 
                                   OR e.last_name LIKE '%" . $shiftConn->real_escape_string($filter_emp) . "%')";
if ($filter_shift)  $countSql .= " AND (s.shift_code LIKE '%" . $shiftConn->real_escape_string($filter_shift) . "%' 
                                   OR s.name LIKE '%" . $shiftConn->real_escape_string($filter_shift) . "%')";
if ($filter_status) $countSql .= " AND es.status LIKE '%" . $shiftConn->real_escape_string($filter_status) . "%'";
if ($filter_notes)  $countSql .= " AND es.notes LIKE '%" . $shiftConn->real_escape_string($filter_notes) . "%'";

$total_result = $shiftConn->query($countSql);
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch filtered rows with pagination
$sql = "
    SELECT es.schedule_id, es.work_date, es.status, es.notes,
           e.first_name, e.last_name,
           s.shift_code, s.name AS shift_name, s.start_time, s.end_time
    FROM employee_schedules es
    JOIN hr3_system.employees e ON es.employee_id = e.employee_id
    JOIN shifts s ON es.shift_id = s.shift_id
    WHERE 1=1
";
if ($filter_date)   $sql .= " AND es.work_date = '" . $shiftConn->real_escape_string($filter_date) . "'";
if ($filter_emp)    $sql .= " AND (e.first_name LIKE '%" . $shiftConn->real_escape_string($filter_emp) . "%' 
                              OR e.last_name LIKE '%" . $shiftConn->real_escape_string($filter_emp) . "%')";
if ($filter_shift)  $sql .= " AND (s.shift_code LIKE '%" . $shiftConn->real_escape_string($filter_shift) . "%' 
                              OR s.name LIKE '%" . $shiftConn->real_escape_string($filter_shift) . "%')";
if ($filter_status) $sql .= " AND es.status LIKE '%" . $shiftConn->real_escape_string($filter_status) . "%'";
if ($filter_notes)  $sql .= " AND es.notes LIKE '%" . $shiftConn->real_escape_string($filter_notes) . "%'";

$sql .= " ORDER BY es.work_date DESC, e.first_name
          LIMIT $records_per_page OFFSET $offset";

$result = $shiftConn->query($sql);
?>

<!-- Schedule Table -->
<table class="w-full border">
    <thead class="bg-gray-100">
        <tr>
            <th class="p-2 border">Date</th>
            <th class="p-2 border">Employee</th>
            <th class="p-2 border">Shift</th>
            <th class="p-2 border">Time</th>
            <th class="p-2 border">Status</th>
            <th class="p-2 border">Notes</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td class="p-2 border"><?php echo htmlspecialchars($row['work_date']); ?></td>
                    <td class="p-2 border"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                    <td class="p-2 border"><?php echo htmlspecialchars($row['shift_code'] . " - " . $row['shift_name']); ?></td>
                    <td class="p-2 border"><?php echo substr($row['start_time'],0,5) . " - " . substr($row['end_time'],0,5); ?></td>
                    <td class="p-2 border"><?php echo htmlspecialchars($row['status']); ?></td>
                    <td class="p-2 border"><?= htmlspecialchars($row['notes'] ?? "No notes") ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" class="p-4 text-center text-gray-500">No schedules found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Pagination -->
<div class="mt-4 flex justify-center space-x-2">
    <?php if($page > 1): ?>
        <a href="?page=<?php echo $page-1; ?>&filter_date=<?php echo urlencode($filter_date); ?>&filter_emp=<?php echo urlencode($filter_emp); ?>&filter_shift=<?php echo urlencode($filter_shift); ?>&filter_status=<?php echo urlencode($filter_status); ?>&filter_notes=<?php echo urlencode($filter_notes); ?>" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300">Prev</a>
    <?php endif; ?>

    <?php for($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?php echo $i; ?>&filter_date=<?php echo urlencode($filter_date); ?>&filter_emp=<?php echo urlencode($filter_emp); ?>&filter_shift=<?php echo urlencode($filter_shift); ?>&filter_status=<?php echo urlencode($filter_status); ?>&filter_notes=<?php echo urlencode($filter_notes); ?>" class="px-3 py-1 rounded <?php echo ($i == $page) ? 'bg-gray-700 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>

    <?php if($page < $total_pages): ?>
        <a href="?page=<?php echo $page+1; ?>&filter_date=<?php echo urlencode($filter_date); ?>&filter_emp=<?php echo urlencode($filter_emp); ?>&filter_shift=<?php echo urlencode($filter_shift); ?>&filter_status=<?php echo urlencode($filter_status); ?>&filter_notes=<?php echo urlencode($filter_notes); ?>" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300">Next</a>
    <?php endif; ?>
</div>



</body>
</html>

