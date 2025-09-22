

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shift Report</title>
<!-- Tailwind CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<style>
@media print {
    /* Remove scrolling for print */
    .print-table-container {
        overflow: visible !important;
        max-height: none !important;
    }

    /* Remove sticky positioning in print */
    .print-table th,
    .print-table td {
        position: static !important;
    }

    /* Shrink font for print */
    .print-table th,
    .print-table td {
        font-size: 10pt !important;
        padding: 4px !important;
    }

    /* Hide buttons during print */
    .no-print {
        display: none !important;
    }
}
</style>


<?php
include __DIR__ . '/../dbconnection/dbEmployee.php';
$empConn = $conn;
include __DIR__ . '/../dbconnection/mainDB.php';
$shiftConn = $conn;

$selected_department = $_GET['department'] ?? '';
$selected_role = $_GET['role'] ?? '';
$week_start_input = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));

// Fetch department name
$department_name = '';
if ($selected_department) {
    $deptRes = $empConn->query("SELECT name FROM departments WHERE department_id='{$selected_department}' LIMIT 1");
    if ($deptRes && $deptRes->num_rows > 0) {
        $department_name = $deptRes->fetch_assoc()['name'];
    }
}

// Fetch role title
$role_name = '';
if ($selected_role) {
    $roleRes = $empConn->query("SELECT title FROM positions WHERE position_id='{$selected_role}' LIMIT 1");
    if ($roleRes && $roleRes->num_rows > 0) {
        $role_name = $roleRes->fetch_assoc()['title'];
    }
}

// Get employees for role
$employees = [];
if ($selected_role) {
    $employees = $empConn->query("
        SELECT employee_id, CONCAT(first_name,' ',last_name) AS fullname 
        FROM employees 
        WHERE position_id = '{$selected_role}' 
        ORDER BY first_name
    ");
}

// Get shifts
$shiftsArray = [];
$shiftsResult = $shiftConn->query("SELECT shift_id, shift_code, start_time, end_time FROM shifts ORDER BY start_time");
while($row = $shiftsResult->fetch_assoc()){
    $shiftsArray[$row['shift_id']] = $row;
}

// Week days
$days = [];
for($i = 0; $i < 7; $i++){
    $days[] = date('Y-m-d', strtotime("$week_start_input +$i days"));
}

// Get schedules and notes
$schedules = [];
$notes = [];
if ($selected_role) {
    $dayStart = $days[0];
    $dayEnd = end($days);
    $res = $shiftConn->query("
        SELECT employee_id, shift_id, work_date, notes 
        FROM employee_schedules 
        WHERE work_date BETWEEN '$dayStart' AND '$dayEnd'
    ");
    while($row = $res->fetch_assoc()){
        $schedules[$row['employee_id']][$row['work_date']] = $row['shift_id'];
        $notes[$row['employee_id']][$row['work_date']] = $row['notes'];
    }
}
?>

<div class="flex justify-center my-10">
    <div class="bg-white shadow-2xl p-10 w-full max-w-4xl border border-gray-300 rounded-lg">
        <!-- Header like bondpaper -->
     <div class="text-center mb-6">
    <img src="../picture/logo.png" class="mx-auto w-32" alt="Logo"><br>
    <h2 class="text-2xl font-bold mt-2">ATIÉRA Hotel and Restaurant</h2>
    <p class="mt-1 font-semibold">Shift Report</p>
    <p class="mt-1"><strong>Department:</strong> <?= htmlspecialchars($department_name) ?></p>
    <p><strong>Position:</strong> <?= htmlspecialchars($role_name) ?></p>
    <p class="mt-1"><strong>Week Starting:</strong> <?= date('m/d/Y', strtotime($week_start_input)) ?></p>
</div>



        
<!-- Schedule Table -->
<div class="overflow-y-auto max-h-[500px] mb-6 border border-gray-400 rounded-lg shadow print-table-container">
    <table class="w-full table-fixed border-collapse print-table">
    <thead class="bg-gray-100">
    <tr>
        <!-- Sticky Employee Column -->
        <th class="border border-gray-300 px-4 py-2 sticky left-0 top-0 bg-gray-100 z-20 w-40">
            Employee
        </th>
        <?php foreach($days as $day): ?>
            <th class="border border-gray-300 px-4 py-2 text-center sticky top-0 bg-gray-100 z-10">
                <div class="whitespace-nowrap">
                    <span class="block font-medium"><?= date('D', strtotime($day)) ?></span>
                    <span class="block text-sm text-gray-600"><?= date('m/d', strtotime($day)) ?></span>
                </div>
            </th>
        <?php endforeach; ?>
    </tr>
</thead>

        <tbody>
            <?php if($employees->num_rows > 0): ?>
                <?php while($emp=$employees->fetch_assoc()): ?>
                    <tr class="bg-white">
                        <td class="border border-gray-300 px-4 py-2 sticky left-0 bg-white font-medium z-10 w-40">
                            <?= htmlspecialchars($emp['fullname']) ?>
                        </td>
                        <?php foreach($days as $day):
                            $shift_id = $schedules[$emp['employee_id']][$day] ?? '';
                            $note_text = $notes[$emp['employee_id']][$day] ?? '';
                        ?>




                            <td class="border border-gray-300 px-2 py-2 text-center">
                                <?php
                                if($shift_id && isset($shiftsArray[$shift_id])){
                                    $s = $shiftsArray[$shift_id];
                                    echo $s['shift_code'].' ('.$s['start_time'].'-'.$s['end_time'].')';
                                } else {
                                    echo 'Off';
                                }
                                if($note_text) echo '<br><small>'.$note_text.'</small>';
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= count($days)+1 ?>" class="text-center px-4 py-2">No employees found for this role.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>



<!-- Buttons section -->
<div class="flex justify-between mt-8 no-print">
  <!-- Go Back Button -->
<button 
    onclick="if(document.referrer){ window.location.href = document.referrer; } else { window.location.href='index.php'; }" 
    class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 no-print">
    Go Back
</button>


    <div class="flex gap-4">
        <form method="POST" action="exportpdf.php" target="_blank">
            <input type="hidden" name="department" value="<?= htmlspecialchars($selected_department) ?>">
            <input type="hidden" name="role" value="<?= htmlspecialchars($selected_role) ?>">
            <input type="hidden" name="week_start" value="<?= htmlspecialchars($week_start_input) ?>">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Download PDF
            </button>
        </form>
        <button onclick="window.print()" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
            Print
        </button>
    </div>
</div>

    </div>
</div>
</body>
</html>
