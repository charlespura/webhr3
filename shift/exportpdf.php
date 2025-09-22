<?php
require_once __DIR__ . '/vendor/autoload.php';
$mpdf = new \Mpdf\Mpdf([
    'tempDir'=>__DIR__.'/tmp',
    'format'=>'A4',
    'orientation'=>'P'
]);

include __DIR__ . '/../dbconnection/dbEmployee.php';
$empConn = $conn;
include __DIR__ . '/../dbconnection/mainDB.php';
$shiftConn = $conn;

// Read POST data
$selected_department = $_POST['department'] ?? '';
$selected_role = $_POST['role'] ?? '';
$week_start_input = $_POST['week_start'] ?? date('Y-m-d', strtotime('monday this week'));

// Get employees
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

// Then use $department_name and $role_name in your PDF header:
$html = '<div style="text-align:center; margin-bottom:20px;">
    <img src="../picture/logo.png" width="120"><br>


    <h2>ATIÉRA Hotel and Restaurant</h2>
    <h3>Shift Report</h3>
    <p>Department: ' . htmlspecialchars($department_name) . '</p>
    <p>Position: ' . htmlspecialchars($role_name) . '</p>
    <p>Week Starting: ' . date('m/d/Y', strtotime($week_start_input)) . '</p>
</div>';


$html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%" style="border-collapse: collapse;">
    <thead>
        <tr style="background-color:#f2f2f2;">
            <th style="padding:8px;">Employee</th>';

foreach ($days as $day) {
    $html .= '<th style="padding:8px; text-align:center;">' . date('D<br>m/d', strtotime($day)) . '</th>';
}
$html .= '</tr></thead><tbody>';

if ($employees->num_rows > 0) {
    while($emp = $employees->fetch_assoc()){
        $html .= '<tr>';
        $html .= '<td style="padding:6px;">' . htmlspecialchars($emp['fullname']) . '</td>';
        foreach ($days as $day) {
            $shift_id = $schedules[$emp['employee_id']][$day] ?? '';
            $note_text = $notes[$emp['employee_id']][$day] ?? '';
            if ($shift_id && isset($shiftsArray[$shift_id])) {
                $s = $shiftsArray[$shift_id];
                $html .= '<td style="padding:6px; text-align:center;">' . $s['shift_code'] . '<br>' . $s['start_time'] . '-' . $s['end_time'];
                if ($note_text) $html .= '<br><small>Note: ' . htmlspecialchars($note_text) . '</small>';
                $html .= '</td>';
            } else {
                $html .= '<td style="padding:6px; text-align:center;">Off';
                if ($note_text) $html .= '<br><small>Note: ' . htmlspecialchars($note_text) . '</small>';
                $html .= '</td>';
            }
        }
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td colspan="' . (count($days)+1) . '" style="text-align:center; padding:8px;">No employees found for this role.</td></tr>';
}

$html .= '</tbody></table>';

// Write HTML to PDF and download
$mpdf->WriteHTML($html);
$mpdf->Output('ShiftReport_'.date("Ymd").'.pdf', 'D');
?>
