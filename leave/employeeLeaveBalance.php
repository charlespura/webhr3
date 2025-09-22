<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$roles = $_SESSION['roles'] ?? 'Employee'; // Role of logged-in user

$message = '';
$messageType = '';

include __DIR__ . '/../dbconnection/dbEmployee.php';
$empConn = $conn;
include __DIR__ . '/../dbconnection/mainDB.php';
$shiftConn = $conn;

// ==========================
// Handle Edit / Update / Delete
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_balance']) && in_array($roles, ['Admin','Manager'])) {
    $balance_id = intval($_POST['balance_id']);
    $total_days = intval($_POST['total_days']);

    $stmt = $shiftConn->prepare("UPDATE employee_leave_balance SET total_days=? WHERE id=?");
    $stmt->bind_param("ii", $total_days, $balance_id);
    if ($stmt->execute()) {
        $message = "Leave balance updated successfully!";
        $messageType = "success";
    } else {
        $message = "Error updating balance: " . $shiftConn->error;
        $messageType = "error";
    }
    $stmt->close();
}

if (isset($_GET['delete_id']) && in_array($roles, ['Admin','Manager'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $shiftConn->prepare("DELETE FROM employee_leave_balance WHERE id=?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "Leave balance deleted!";
        $messageType = "success";
    } else {
        $message = "Error deleting balance: " . $shiftConn->error;
        $messageType = "error";
    }
    $stmt->close();
}

// ==========================
// Fetch Employees & Leave Types
// ==========================
$employees = $empConn->query("SELECT employee_id, first_name, last_name FROM hr3_system.employees ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);
$leaveTypes = $shiftConn->query("SELECT leave_type_id, leave_name FROM leave_types ORDER BY leave_name")->fetch_all(MYSQLI_ASSOC);

// ==========================
// Fetch All Leave Balances
// ==========================
$balances = $shiftConn->query("
    SELECT elb.id AS balance_id, e.first_name, e.last_name, lt.leave_name, elb.total_days, elb.used_days, (elb.total_days - elb.used_days) AS remaining_days
    FROM employee_leave_balance elb
    JOIN hr3_system.employees e ON elb.employee_id = e.employee_id
    JOIN leave_types lt ON elb.leave_type_id = lt.leave_type_id
    ORDER BY e.first_name, lt.leave_name
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title> Leave</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="icon" type="image/png" href="../picture/logo2.png" />
  <script>
    document.addEventListener("DOMContentLoaded", () => lucide.createIcons());
    function openDeleteModal(id) {
      document.getElementById("deleteModal").classList.remove("hidden");
      document.getElementById("confirmDeleteBtn").href = "?delete_id=" + id;
    }
    function closeDeleteModal() {
      document.getElementById("deleteModal").classList.add("hidden");
    }
  </script>
</head>

<body class="h-screen overflow-hidden">

<div class="flex h-full">
  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main -->
  <div class="flex-1 flex flex-col overflow-y-auto">
    <main class="p-6 space-y-4">
      <div class="flex items-center justify-between border-b py-6">
        <h2 class="text-xl font-semibold text-gray-800">Leave Management</h2>
        <?php include '../profile.php'; ?>
      </div>

      <?php include 'leavenavbar.php'; ?>

    



<h2 class="text-xl font-semibold mb-4">Employee Leave Balances</h2>

<?php if ($message): ?>
<div class="mb-4 px-4 py-3 rounded-lg text-white <?= $messageType === 'success' ? 'bg-green-500' : 'bg-red-500' ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Search Filters -->
<div class="flex gap-4 mb-4">
    <input type="text" id="employeeSearch" placeholder="Search Employee" class="border p-2 rounded flex-1">
    <input type="text" id="leaveTypeSearch" placeholder="Search Leave Type" class="border p-2 rounded flex-1">
</div>

<!-- Leave Balance Table -->
<table class="w-full border-collapse border text-sm" id="balanceTable">
    <thead class="bg-gray-200">
        <tr>
            <th class="border px-3 py-2">Employee</th>
            <th class="border px-3 py-2">Leave Type</th>
            <th class="border px-3 py-2">Total Days</th>
            <th class="border px-3 py-2">Used Days</th>
            <th class="border px-3 py-2">Remaining</th>
            <?php if (in_array($roles, ['Admin','Manager'])): ?>
            <th class="border px-3 py-2">Actions</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
     <?php foreach ($balances as $b): ?>
<tr class="hover:bg-gray-50">
    <td class="border px-3 py-2"><?= htmlspecialchars($b['first_name'].' '.$b['last_name']) ?></td>
    <td class="border px-3 py-2"><?= htmlspecialchars($b['leave_name']) ?></td>
    <td class="border px-3 py-2">
        <?php if (in_array($roles, ['Admin','Manager'])): ?>
        <form method="POST" class="flex items-center gap-2">
            <input type="number" name="total_days" value="<?= $b['total_days'] ?>" class="w-20 border p-1 rounded" required>
            <input type="hidden" name="balance_id" value="<?= $b['balance_id'] ?>">
            <button type="submit" name="update_balance" class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded">Update</button>
        </form>
        <?php else: ?>
            <?= $b['total_days'] ?>
        <?php endif; ?>
    </td>
    <td class="border px-3 py-2 text-center"><?= $b['used_days'] ?></td>
    <td class="border px-3 py-2 text-center"><?= $b['remaining_days'] ?></td>
    <?php if (in_array($roles, ['Admin','Manager'])): ?>
    <td class="border px-3 py-2">
        <!-- Connect to modal -->
        <button onclick="openDeleteModal(<?= $b['balance_id'] ?>)" class="text-red-600 hover:underline">Delete</button>
    </td>
    <?php endif; ?>
</tr>
<?php endforeach; ?>

    </tbody>
</table>

</div>

<script>
// Automatic table filter
const employeeInput = document.getElementById('employeeSearch');
const leaveTypeInput = document.getElementById('leaveTypeSearch');
const table = document.getElementById('balanceTable');
const rows = table.querySelectorAll('tbody tr');

function filterTable() {
    const empFilter = employeeInput.value.toLowerCase();
    const leaveFilter = leaveTypeInput.value.toLowerCase();

    rows.forEach(row => {
        const empName = row.cells[0].textContent.toLowerCase();
        const leaveName = row.cells[1].textContent.toLowerCase();
        if (empName.includes(empFilter) && leaveName.includes(leaveFilter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

employeeInput.addEventListener('input', filterTable);
leaveTypeInput.addEventListener('input', filterTable);
</script>
<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50 transition-opacity duration-300 opacity-0">
  <div class="bg-white rounded-lg shadow-lg w-96 p-6 transform scale-90 transition-transform duration-300">
    <h2 class="text-lg font-bold mb-4">Are you sure?</h2>
    <p class="mb-6 text-gray-600">The selected leave request will be permanently deleted. Continue?</p>
    <div class="flex justify-end space-x-3">
      <button onclick="closeDeleteModal()" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400">Cancel</button>
      <a id="confirmDeleteBtn" href="#" class="px-4 py-2 rounded bg-red-600 hover:bg-red-700 text-white">Delete</a>
    </div>
  </div>
</div>

<script>
function openDeleteModal(id) {
    const modal = document.getElementById("deleteModal");
    const confirmBtn = document.getElementById("confirmDeleteBtn");
    confirmBtn.href = "?delete_id=" + id;

    modal.classList.remove("hidden");
    setTimeout(() => {
        modal.classList.remove("opacity-0");
        modal.firstElementChild.classList.remove("scale-90");
    }, 10); // tiny delay to trigger transition
}

function closeDeleteModal() {
    const modal = document.getElementById("deleteModal");
    modal.classList.add("opacity-0");
    modal.firstElementChild.classList.add("scale-90");
    setTimeout(() => {
        modal.classList.add("hidden");
    }, 300); // match duration-300
}
</script>

</body>
</html>
