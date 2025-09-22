
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
      <main class="p-6 ">
        <!-- Header -->
        <div class="flex items-center justify-between border-b py-6">
          <!-- Left: Title -->
          <h2 class="text-xl font-semibold text-gray-800" id="main-content-title">Shift and Schedule</h2>


          <!-- ito yung profile ng may login wag kalimutan lagyan ng session yung profile.php para madetect nya if may login or wala -->
<?php 
include '../profile.php'; 
?>

        </div>


     <?php 
include 'shiftnavbar.php';
?>


<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../dbconnection/dbEmployee.php'; // employee DB
$empConn = $conn;

include __DIR__ . '/../dbconnection/mainDB.php'; // shift DB
$shiftConn = $conn;

// Capture filters
$start_date   = $_GET['start_date'] ?? '';
$end_date     = $_GET['end_date'] ?? '';
$employee_id  = $_GET['employee_id'] ?? '';

// Fetch employees list for autocomplete
$employees = [];
$empResult = $empConn->query("SELECT employee_id, first_name, last_name FROM employees ORDER BY first_name, last_name");
if ($empResult && $empResult->num_rows > 0) {
    while ($row = $empResult->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Default empty list
$noShiftEmployees = [];

if ($start_date && $end_date) {
    // Query employees without assigned shifts (only consider rows with a shift_id)
    $noShiftSql = "
        SELECT e.employee_id, e.first_name, e.last_name
        FROM hr3_system.employees e
        LEFT JOIN employee_schedules es 
            ON e.employee_id = es.employee_id
            AND es.work_date BETWEEN '" . $shiftConn->real_escape_string($start_date) . "' 
                                 AND '" . $shiftConn->real_escape_string($end_date) . "'
            AND es.shift_id IS NOT NULL
        WHERE es.schedule_id IS NULL
    ";

    if ($employee_id) {
        $noShiftSql .= " AND e.employee_id = '" . $shiftConn->real_escape_string($employee_id) . "'";
    }

    $noShiftSql .= " ORDER BY e.first_name, e.last_name";

    $noShiftResult = $shiftConn->query($noShiftSql);
    if ($noShiftResult && $noShiftResult->num_rows > 0) {
        while ($row = $noShiftResult->fetch_assoc()) {
            $noShiftEmployees[] = $row;
        }
    }
}

?>

<div class="bg-white shadow-md rounded-2xl p-10 w-full mx-auto mt-10 mb-10">
    <h2 class="text-2xl font-bold mb-6">🚫 Employees Without Shifts</h2>

    <!-- Filter Form -->
    <form method="get" id="filterForm" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" 
               class="border rounded p-2" onchange="document.getElementById('filterForm').submit()">
        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" 
               class="border rounded p-2" onchange="document.getElementById('filterForm').submit()">

        <!-- Employee Autocomplete -->
        <div>
            <label class="block mb-1">Employee</label>
            <input type="text" id="employeeInput" class="w-full border p-2 rounded" 
                   placeholder="Type employee name..." autocomplete="off"
                   value="<?php
                       if ($employee_id) {
                           $emp = array_filter($employees, fn($e) => $e['employee_id'] == $employee_id);
                           if ($emp) {
                               $emp = reset($emp);
                               echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']);
                           }
                       }
                   ?>">
            <input type="hidden" name="employee_id" id="employee_id" value="<?php echo htmlspecialchars($employee_id); ?>">
            <div id="suggestions" class="border rounded mt-1 max-h-40 overflow-auto hidden bg-white absolute z-10"></div>
        </div>

        <div class="flex items-end">
            <a href="noShift.php" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded">Reset</a>
        </div>
    </form>

    <?php if ($start_date && $end_date): ?>
        <?php if (!empty($noShiftEmployees)): ?>
            <table class="w-full border">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 border">Employee</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($noShiftEmployees as $emp): ?>
                        <tr>
                            <td class="p-2 border">
                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-gray-600 italic">All employees have shifts assigned between 
                <b><?php echo htmlspecialchars($start_date); ?></b> and 
                <b><?php echo htmlspecialchars($end_date); ?></b>.
            </p>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-gray-600 italic">Please select a start and end date to check employees without shifts.</p>
    <?php endif; ?>
</div>

<script>
  const employees = <?php echo json_encode($employees); ?>; // pass PHP array to JS
  const input = document.getElementById('employeeInput');
  const suggestions = document.getElementById('suggestions');
  const hiddenInput = document.getElementById('employee_id');
  const form = document.getElementById('filterForm');

  input.addEventListener('input', () => {
    const value = input.value.toLowerCase();
    suggestions.innerHTML = '';

    if (!value) {
      suggestions.classList.add('hidden');
      hiddenInput.value = '';
      return;
    }

    const matches = employees.filter(emp =>
      (emp.first_name + ' ' + emp.last_name).toLowerCase().includes(value)
    );

    if (matches.length === 0) {
      suggestions.classList.add('hidden');
      hiddenInput.value = '';
      return;
    }

    matches.forEach(emp => {
      const div = document.createElement('div');
      div.classList.add('p-2', 'cursor-pointer', 'hover:bg-gray-200');
      div.textContent = emp.first_name + ' ' + emp.last_name;
      div.addEventListener('click', () => {
        input.value = emp.first_name + ' ' + emp.last_name;
        hiddenInput.value = emp.employee_id;
        suggestions.classList.add('hidden');
        form.submit();
      });
      suggestions.appendChild(div);
    });

    suggestions.classList.remove('hidden');
  });

  // Hide suggestions when clicking outside
  document.addEventListener('click', (e) => {
    if (!input.contains(e.target) && !suggestions.contains(e.target)) {
      suggestions.classList.add('hidden');
    }
  });
</script>

</body>
</html>
