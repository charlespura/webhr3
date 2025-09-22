<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");

// DB connections
include __DIR__ . '/../dbconnection/dbEmployee.php'; // hr3_system
$empConn = $conn;

include __DIR__ . '/../dbconnection/mainDB.php'; // hr3_maindb
$mainConn = $conn;

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? $_GET['id'] : null;

// Helper function for formatting hours worked
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

// --- GET: Fetch attendance ---
if ($method === 'GET') {

    $sql = "
    SELECT 
        e.employee_id,
        CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
        d.name AS department,
        p.title AS position,
        u.username,
        s.work_date,
        a.clock_in,
        a.clock_out,
        IFNULL(a.break_in,'Not Started') AS break_in,
        IFNULL(a.break_out,'Not Ended') AS break_out,
        IFNULL(a.break_violation,0) AS break_violation,
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
    " . ($id ? "WHERE a.attendance_id = ?" : "") . "
    ORDER BY e.employee_id ASC, s.work_date DESC
    ";

    if ($id) {
        $stmt = $mainConn->prepare($sql);
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $attendance = $res->fetch_assoc();
        if ($attendance) {
            $attendance['hours_worked'] = formatHoursWorked($attendance['hours_worked']);
            echo json_encode($attendance);
        } else {
            echo json_encode(["message" => "Attendance not found"]);
        }
    } else {
        $res = $mainConn->query($sql);
        $attendances = [];
        while ($row = $res->fetch_assoc()) {
            $row['hours_worked'] = formatHoursWorked($row['hours_worked']);
            $attendances[] = $row;
        }
        echo json_encode($attendances);
    }
    exit;
}

// --- POST: Create attendance ---
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!isset($input['schedule_id'], $input['user_id'], $input['clock_in'], $input['clock_out'])) {
        echo json_encode(["message" => "schedule_id, user_id, clock_in, clock_out required"]);
        exit;
    }

    $hours_worked = ($input['hours_worked'] ?? null);
    $remarks = $input['remarks'] ?? '';

    $stmt = $mainConn->prepare("INSERT INTO attendance 
        (attendance_id, schedule_id, user_id, clock_in, clock_out, break_in, break_out, break_violation, clock_in_image, clock_out_image, hours_worked, remarks)
        VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssssssssds',
        $input['schedule_id'], $input['user_id'], $input['clock_in'], $input['clock_out'],
        $input['break_in'], $input['break_out'], $input['break_violation'], 
        $input['clock_in_image'], $input['clock_out_image'], $hours_worked, $remarks
    );

    echo $stmt->execute() 
        ? json_encode(["message" => "Attendance created"])
        : json_encode(["message" => "Failed to create attendance"]);
}

// --- PUT: Update attendance ---
elseif ($method === 'PUT') {
    if (!$id) { echo json_encode(["message"=>"ID required"]); exit; }
    parse_str(file_get_contents("php://input"), $input);

    $hours_worked = ($input['hours_worked'] ?? null);
    $stmt = $mainConn->prepare("UPDATE attendance SET clock_in=?, clock_out=?, break_in=?, break_out=?, break_violation=?, clock_in_image=?, clock_out_image=?, hours_worked=?, remarks=? WHERE attendance_id=? LIMIT 1");
    $stmt->bind_param('ssssssdsss',
        $input['clock_in'], $input['clock_out'], $input['break_in'], $input['break_out'],
        $input['break_violation'], $input['clock_in_image'], $input['clock_out_image'],
        $hours_worked, $input['remarks'], $id
    );

    echo $stmt->execute() 
        ? json_encode(["message"=>"Attendance updated"])
        : json_encode(["message"=>"Failed to update attendance"]);
}

// --- DELETE: Delete attendance ---
elseif ($method === 'DELETE') {
    if (!$id) { echo json_encode(["message"=>"ID required"]); exit; }
    $stmt = $mainConn->prepare("DELETE FROM attendance WHERE attendance_id=? LIMIT 1");
    $stmt->bind_param('s', $id);
    echo $stmt->execute()
        ? json_encode(["message"=>"Attendance deleted"])
        : json_encode(["message"=>"Failed to delete attendance"]);
}

// --- Unsupported method ---
else {
    echo json_encode(["message"=>"Method not allowed"]);
}
?>
