

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Employee  </title>
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
      <main class="p-6 space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between border-b py-6">
          <!-- Left: Title -->
          <h2 class="text-xl font-semibold text-gray-800" id="main-content-title">Employee  </h2>

  <?php include '../profile.php'; ?>


        </div>




<!-- Second Header: Submodules -->
<?php 
include 'employeeNavbar.php'; ?>


        <!-- Page Body -->
        <p class="text-gray-600"></p>
      </main>
     

      
<div class="bg-white shadow-md rounded-2xl p-10 w-full mx-auto mt-10 mb-10">



<?php
// Include the database connection file
include __DIR__ .'/../dbconnection/dbEmployee.php';

function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// -------------------------
// Handle form submission
// -------------------------
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
    "ssssssssssssssisds",
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
        echo "<p style='color:green;'>Employee updated successfully!</p>";
    } else {
        echo "<p style='color:red;'>Error: " . $stmt->error . "</p>";
    }
}

// -------------------------
// Show form (GET request)
// -------------------------
$employee_id = $_GET['id'] ?? $_POST['employee_id'] ?? '';
if (empty($employee_id)) {
    die("Invalid Employee ID");
}

$stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
$stmt->bind_param("s", $employee_id);
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

<div class="bg-white shadow-md rounded-2xl p-10 w-full mx-auto mt-10 mb-10">
<h2 class="text-2xl font-bold mb-6">Edit Employee</h2>
<form method="post" action="" class="space-y-8">

    <input type="hidden" name="employee_id" value="<?php echo safe($employee['employee_id']); ?>">

    <!-- Personal Info -->
    <fieldset class="border p-4 rounded-lg">
        <legend class="font-semibold text-lg">Personal Info</legend>
        <div class="grid grid-cols-2 gap-4 mt-2">
            <div>
                <label>Employee Code</label>
                <input type="text" name="employee_code" value="<?php echo safe($employee['employee_code']); ?>" class="border rounded w-full p-2" required>
            </div>
            <div>
                <label>First Name</label>
                <input type="text" name="first_name" value="<?php echo safe($employee['first_name']); ?>" class="border rounded w-full p-2" required>
            </div>
            <div>
                <label>Middle Name</label>
                <input type="text" name="middle_name" value="<?php echo safe($employee['middle_name']); ?>" class="border rounded w-full p-2">
            </div>
            <div>
                <label>Last Name</label>
                <input type="text" name="last_name" value="<?php echo safe($employee['last_name']); ?>" class="border rounded w-full p-2" required>
            </div>
            <div>
                <label>Preferred Name</label>
                <input type="text" name="preferred_name" value="<?php echo safe($employee['preferred_name']); ?>" class="border rounded w-full p-2">
            </div>
            <div>
                <label>Date of Birth</label>
                <input type="date" name="dob" value="<?php echo safe($employee['dob']); ?>" class="border rounded w-full p-2">
            </div>
            <div>
                <label>Gender</label>
                <select name="gender" class="border rounded w-full p-2">
                    <option value="">-- Select --</option>
                    <option <?php if($employee['gender'] === 'Male') echo 'selected'; ?>>Male</option>
                    <option <?php if($employee['gender'] === 'Female') echo 'selected'; ?>>Female</option>
                    <option <?php if($employee['gender'] === 'Other') echo 'selected'; ?>>Other</option>
                </select>
            </div>
        </div>
    </fieldset>

    <!-- Contact Info -->
    <fieldset class="border p-4 rounded-lg">
        <legend class="font-semibold text-lg">Contact Info</legend>
        <div class="grid grid-cols-2 gap-4 mt-2">
            <div>
                <label>Personal Email</label>
                <input type="email" name="personal_email" value="<?php echo safe($employee['personal_email']); ?>" class="border rounded w-full p-2">
            </div>
        <div>
  <label>Phone</label>
  <input 
    type="text" 
    name="phone" 
    value="<?php echo safe($employee['phone']); ?>" 
    placeholder="09171234567" 
    class="border rounded w-full p-2" 
    pattern="\d{11}" 
    maxlength="11" 
    title="Please enter exactly 11 digits" 
    inputmode="numeric"
    required
  >
</div>


        </div>
    </fieldset>

    <!-- Job Info -->
    <fieldset class="border p-4 rounded-lg">
        <legend class="font-semibold text-lg">Job Info</legend>
        <div class="grid grid-cols-2 gap-4 mt-2">
            <div>
                <label>Hire Date</label>
                <input type="date" name="hire_date" value="<?php echo safe($employee['hire_date']); ?>" class="border rounded w-full p-2" required>
            </div>
            <div>
                <label>End Date</label>
                <input type="date" name="end_date" value="<?php echo safe($employee['end_date']); ?>" class="border rounded w-full p-2">
            </div>
            <div>
                <label>Department</label>
                <select name="department_id" class="border rounded w-full p-2" required>
                    <?php while($d = $departments->fetch_assoc()): ?>
                        <option value="<?php echo $d['department_id']; ?>" <?php if($employee['department_id'] == $d['department_id']) echo 'selected'; ?>>
                            <?php echo safe($d['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label>Position</label>
                <select name="position_id" class="border rounded w-full p-2" required>
                    <?php while($p = $positions->fetch_assoc()): ?>
                        <option value="<?php echo $p['position_id']; ?>" <?php if($employee['position_id'] == $p['position_id']) echo 'selected'; ?>>
                            <?php echo safe($p['title']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label>Contract Type</label>
                <select name="contract_type_id" class="border rounded w-full p-2">
                    <?php while($c = $contracts->fetch_assoc()): ?>
                        <option value="<?php echo $c['contract_type_id']; ?>" <?php if($employee['contract_type_id'] == $c['contract_type_id']) echo 'selected'; ?>>
                            <?php echo safe($c['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label>Status</label>
                <select name="status" class="border rounded w-full p-2" required>
                    <?php while($s = $statuses->fetch_assoc()): ?>
                        <option value="<?php echo $s['status_id']; ?>" <?php if($employee['status'] == $s['status_id']) echo 'selected'; ?>>
                            <?php echo safe($s['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
    </fieldset>

    <!-- Salary Info -->
    <fieldset class="border p-4 rounded-lg">
        <legend class="font-semibold text-lg">Salary Info</legend>
        <div class="grid grid-cols-2 gap-4 mt-2">
            <div>
                <label>Salary Currency</label>
                <input type="text" name="salary_currency" value="<?php echo safe($employee['salary_currency']); ?>" class="border rounded w-full p-2">
            </div>
            <div>
                <label>Salary Amount</label>
                <input type="number" step="0.01" name="salary_amount" value="<?php echo safe($employee['salary_amount']); ?>" class="border rounded w-full p-2">
            </div>
        </div>
    </fieldset>

    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white py-2 px-6 rounded-lg">
        Save Changes
    </button>
</form>
</div>

<?php $conn->close(); ?>

</div>




</body>
</html>
