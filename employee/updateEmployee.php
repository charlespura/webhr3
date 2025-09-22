<?php
// Include the database connection file
include __DIR__ .'/../dbconnection/dbEmployee.php';

function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Handle form submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id      = $_POST['employee_id'];
    $employee_code    = $_POST['employee_code'];
    $first_name       = $_POST['first_name'];
    $middle_name      = $_POST['middle_name'] ?? null;
    $last_name        = $_POST['last_name'];
    $preferred_name   = $_POST['preferred_name'] ?? null;
    $dob              = $_POST['dob'] ?? null;
    $gender           = $_POST['gender'] ?? null;
    $personal_email   = $_POST['personal_email'] ?? null;
    $phone            = $_POST['phone'] ?? null;
    $hire_date        = $_POST['hire_date'];
    $end_date         = $_POST['end_date'] ?? null;
    $department_id    = $_POST['department_id'];
    $position_id      = $_POST['position_id'];
    $contract_type_id = $_POST['contract_type_id'] ?? null;
    $status           = $_POST['status'];
    $salary_currency  = $_POST['salary_currency'] ?? null;
    $salary_amount    = $_POST['salary_amount'] ?? null;

    $sql = "UPDATE employees SET 
                employee_code = ?, 
                first_name = ?, 
                middle_name = ?, 
                last_name = ?, 
                preferred_name = ?, 
                dob = ?, 
                gender = ?, 
                personal_email = ?, 
                phone = ?, 
                hire_date = ?, 
                end_date = ?, 
                department_id = ?, 
                position_id = ?, 
                contract_type_id = ?, 
                status = ?, 
                salary_currency = ?, 
                salary_amount = ?
            WHERE employee_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssssiiisdsis",
        $employee_code,
        $first_name,
        $middle_name,
        $last_name,
        $preferred_name,
        $dob,
        $gender,
        $personal_email,
        $phone,
        $hire_date,
        $end_date,
        $department_id,
        $position_id,
        $contract_type_id,
        $status,
        $salary_currency,
        $salary_amount,
        $employee_id
    );

    if ($stmt->execute()) {
        header("Location: employee_list.php?msg=updated");
        exit;
    } else {
        echo "Error updating employee: " . $conn->error;
    }
}

// If GET request, fetch employee details for editing
$employee_id = $_GET['id'] ?? '';
if (empty($employee_id)) {
    die("Invalid Employee ID");
}

$stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    die("Employee not found.");
}

// Fetch dropdown data
$departments = $conn->query("SELECT department_id, name FROM departments ORDER BY name");
$positions   = $conn->query("SELECT position_id, title FROM positions ORDER BY title");
$contracts   = $conn->query("SELECT contract_type_id, name FROM contract_types ORDER BY name");
$statuses    = $conn->query("SELECT status_id, name FROM employee_statuses ORDER BY status_id");
?>

<!-- Your HTML form here (the same form you already wrote) -->
<?php include "viewPersonalDetails.php"; ?> 

<?php $conn->close(); ?>
