<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");

// --- DB Connections ---
include __DIR__ . '/../dbconnection/dbEmployee.php';
$empConn = $conn;

include __DIR__ . '/../dbconnection/mainDB.php';
$shiftConn = $conn;

// --- GET: Fetch all employees and their leave balances ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // Fetch all employees
    $employeesRes = $empConn->query("SELECT employee_id, first_name, last_name FROM employees ORDER BY first_name");
    $employees = [];
    while ($emp = $employeesRes->fetch_assoc()) {
        $employees[$emp['employee_id']] = [
            'employee_id' => $emp['employee_id'],
            'employee_name' => trim($emp['first_name'] . ' ' . $emp['last_name']),
            'leave_balances' => []
        ];
    }

    // Fetch leave balances
    $leaveRes = $shiftConn->query(
        "SELECT elb.employee_id, lt.leave_name, elb.total_days, elb.used_days, 
                (elb.total_days - elb.used_days) AS remaining_days
         FROM employee_leave_balance elb
         JOIN leave_types lt ON elb.leave_type_id = lt.leave_type_id
         ORDER BY elb.employee_id, lt.leave_name"
    );

    while ($row = $leaveRes->fetch_assoc()) {
        $empId = $row['employee_id'];
        if (isset($employees[$empId])) {
            $employees[$empId]['leave_balances'][] = [
                'leave_name' => $row['leave_name'],
                'total_days' => floatval($row['total_days']),
                'used_days' => floatval($row['used_days']),
                'remaining_days' => floatval($row['remaining_days'])
            ];
        }
    }

    // Return all employees with leave balances
    echo json_encode(array_values($employees));
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
