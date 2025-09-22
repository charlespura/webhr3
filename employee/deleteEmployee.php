<?php
// Include the database connection file
include __DIR__ .'/../dbconnection/dbEmployee.php';


$employee_id = $_GET['id'] ?? null;   // single delete
$ids         = $_GET['ids'] ?? null;  // multiple delete (comma separated)
$confirmed   = $_GET['confirm'] ?? null;

// If no ID(s) provided, redirect
if (!$employee_id && !$ids) {
    header("Location: employee.php?msg=invalid");
    exit;
}

// Only delete when confirmed
if ($confirmed === "yes") {
    if ($ids) {
        // Bulk delete
        $idArray = explode(",", $ids);
        $placeholders = implode(",", array_fill(0, count($idArray), "?"));
        $types = str_repeat("s", count($idArray));

        $stmt = $conn->prepare("DELETE FROM employees WHERE employee_id IN ($placeholders)");
        $stmt->bind_param($types, ...$idArray);
    } elseif ($employee_id) {
        // Single delete
        $stmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
        $stmt->bind_param("s", $employee_id);
    }

    // Execute query
    if (isset($stmt) && $stmt->execute()) {
        header("Location: employee.php?msg=deleted");
        exit;
    } else {
        header("Location: employee.php?msg=error");
        exit;
    }
}

?>
