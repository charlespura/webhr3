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

// --- Request method and optional schedule ID ---
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

// --- Helper: load shifts ---
function getShifts($conn) {
    $shifts = [];
    $res = $conn->query("SELECT shift_id, shift_code, name AS shift_name, start_time, end_time FROM shifts ORDER BY start_time");
    while($row = $res->fetch_assoc()) {
        $shifts[$row['shift_id']] = $row;
    }
    return $shifts;
}

// --- GET: Fetch schedule(s) with employee names ---
if ($method === 'GET') {
    $shiftsArray = getShifts($shiftConn);

    if ($id) {
        $stmt = $shiftConn->prepare("
            SELECT es.*, e.first_name, e.last_name
            FROM employee_schedules es
            JOIN hr3_system.employees e ON es.employee_id = e.employee_id
            WHERE es.schedule_id = ? LIMIT 1
        ");
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $schedule = $res->fetch_assoc();

        if ($schedule) {
            $schedule['employee_name'] = $schedule['first_name'] . ' ' . $schedule['last_name'];
            $schedule['shift_details'] = $shiftsArray[$schedule['shift_id']] ?? null;
            
            unset($schedule['first_name'], $schedule['last_name']); // clean up
            echo json_encode($schedule);
        } else {
            echo json_encode(["message" => "Schedule not found"]);
        }
    } else {
        $res = $shiftConn->query("
            SELECT es.*, e.first_name, e.last_name
            FROM employee_schedules es
            JOIN hr3_system.employees e ON es.employee_id = e.employee_id
            ORDER BY es.work_date DESC, e.first_name
        ");

        $schedules = [];
        while ($row = $res->fetch_assoc()) {
            $row['employee_name'] = $row['first_name'] . ' ' . $row['last_name'];
            $row['shift_details'] = $shiftsArray[$row['shift_id']] ?? null;
            unset($row['first_name'], $row['last_name']); // clean up
            $schedules[] = $row;
        }
        echo json_encode($schedules);
    }
    exit;
}

// --- POST: Create schedule ---
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!isset($input['employee_id'], $input['shift_id'], $input['work_date'])) {
        echo json_encode(["message" => "employee_id, shift_id, work_date required"]);
        exit;
    }

    $notes = $input['notes'] ?? '';
    $stmt = $shiftConn->prepare("INSERT INTO employee_schedules 
        (schedule_id, employee_id, shift_id, work_date, notes)
        VALUES (UUID(), ?, ?, ?, ?)");
    $stmt->bind_param('ssss', $input['employee_id'], $input['shift_id'], $input['work_date'], $notes);

    echo $stmt->execute()
        ? json_encode(["message" => "Schedule created"])
        : json_encode(["message" => "Failed to create schedule"]);
    exit;
}

// --- PUT: Update schedule ---
elseif ($method === 'PUT') {
    if (!$id) { echo json_encode(["message" => "ID required"]); exit; }
    parse_str(file_get_contents("php://input"), $input);

    if (!isset($input['employee_id'], $input['shift_id'], $input['work_date'])) {
        echo json_encode(["message" => "employee_id, shift_id, work_date required"]);
        exit;
    }

    $notes = $input['notes'] ?? '';
    $stmt = $shiftConn->prepare("UPDATE employee_schedules 
        SET employee_id=?, shift_id=?, work_date=?, notes=? 
        WHERE schedule_id=? LIMIT 1");
    $stmt->bind_param('sssss', $input['employee_id'], $input['shift_id'], $input['work_date'], $notes, $id);

    echo $stmt->execute()
        ? json_encode(["message" => "Schedule updated"])
        : json_encode(["message" => "Failed to update schedule"]);
    exit;
}

// --- DELETE: Delete schedule ---
elseif ($method === 'DELETE') {
    if (!$id) { echo json_encode(["message" => "ID required"]); exit; }

    $stmt = $shiftConn->prepare("DELETE FROM employee_schedules WHERE schedule_id=? LIMIT 1");
    $stmt->bind_param('s', $id);

    echo $stmt->execute()
        ? json_encode(["message" => "Schedule deleted"])
        : json_encode(["message" => "Failed to delete schedule"]);
    exit;
}

// --- Unsupported method ---
else {
    echo json_encode(["message" => "Method not allowed"]);
    exit;
}
?>
