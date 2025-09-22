<?php
include __DIR__ . '/../dbconnection/mainDB.php';
header('Content-Type: application/json');

$employee_id = $_POST['employee_id'] ?? '';
$work_date   = $_POST['work_date'] ?? '';
$notes       = $_POST['notes'] ?? '';

if (!$employee_id || !$work_date) {
    echo json_encode(["status"=>"error","message"=>"Missing data"]);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO employee_schedules (employee_id, work_date, notes, status, created_at, updated_at)
    VALUES (?, ?, ?, 'scheduled', NOW(), NOW())
    ON DUPLICATE KEY UPDATE 
        notes = VALUES(notes),
        updated_at = NOW()
");
$stmt->bind_param("sss", $employee_id, $work_date, $notes);

if ($stmt->execute()) {
    echo json_encode(["status"=>"success","message"=>"Note saved"]);
} else {
    echo json_encode(["status"=>"error","message"=>$stmt->error]);
}
$stmt->close();
