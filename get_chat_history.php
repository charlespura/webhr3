<?php
session_start();
header("Content-Type: application/json");

// ==========================
// DB Connection
// ==========================
include __DIR__ . '/dbconnection/mainDB.php'; // chat_history table
$conn = $conn;

// Get current logged-in employee
$employeeId = $_SESSION['employee_id'] ?? null;
if (!$employeeId) {
    echo json_encode([]);
    exit;
}

// Fetch chat history for this employee, order by oldest first
$stmt = $conn->prepare("SELECT sender, message, created_at FROM chat_history WHERE employee_id = ? ORDER BY created_at ASC");
$stmt->bind_param("s", $employeeId);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = [
        "sender" => $row['sender'],
        "message" => $row['message'],
        "created_at" => $row['created_at']
    ];
}

$stmt->close();
echo json_encode($history);
exit;
?>
