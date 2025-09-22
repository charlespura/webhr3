

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>


<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Always set these before using them
$roles = $_SESSION['roles'] ?? 'Employee';          
$loggedInUserId = $_SESSION['employee_id'] ?? null; 
$loggedInUserName = $_SESSION['user_name'] ?? 'Guest';
$approver = $loggedInUserId; // keep for consistency

error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB Connections
include __DIR__ . '/../dbconnection/dbEmployee.php';
$empConn = $conn;
include __DIR__ . '/../dbconnection/mainDB.php';
$shiftConn = $conn;

// ==========================
// MESSAGE FEEDBACK
// ==========================
$message = '';
$messageType = '';
// ==========================
// ADD LEAVE REQUEST
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_leave_request'])) {

    $employee_id = $_POST['employee_id'] ?? '';
    $leave_type_id = isset($_POST['leave_type_id']) ? intval($_POST['leave_type_id']) : 0;
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

    if (empty($employee_id) || $leave_type_id === 0 || empty($start_date) || empty($end_date)) {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } else {
        $start_ts = strtotime($start_date);
        $end_ts = strtotime($end_date);
        $today_ts = strtotime(date('Y-m-d'));
        $max_ts = strtotime('+1 year', $today_ts);

        if ($start_ts === false || $end_ts === false) {
            $message = "Invalid start or end date.";
            $messageType = "error";
        } elseif ($start_ts > $max_ts || $end_ts > $max_ts) {
            $message = "Start or end date cannot be more than 1 year from today.";
            $messageType = "error";
        } elseif ($end_ts < $start_ts) {
            $message = "End date cannot be before start date.";
            $messageType = "error";
        } else {
            $days = ($end_ts - $start_ts) / (60*60*24) + 1;

            // Check max days per leave type
            $stmt = $shiftConn->prepare("SELECT max_days_per_year FROM leave_types WHERE leave_type_id=?");
            $stmt->bind_param("i", $leave_type_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $max_days = $result['max_days_per_year'];
            $stmt->close();

            if ($days > $max_days) {
                $message = "Requested leave days ($days) exceed the maximum allowed ($max_days) for this leave type.";
                $messageType = "error";
            } else {
                // ==========================
                // Check employee leave balance
                // ==========================
                $stmt = $shiftConn->prepare("
                    SELECT total_days, used_days 
                    FROM employee_leave_balance 
                    WHERE employee_id = ? AND leave_type_id = ?
                ");
                $stmt->bind_param("si", $employee_id, $leave_type_id);
                $stmt->execute();
                $balanceResult = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$balanceResult) {
                    $message = "No leave balance found for this employee and leave type.";
                    $messageType = "error";
                } else {
                    $remainingDays = $balanceResult['total_days'] - $balanceResult['used_days'];

                    if ($days > $remainingDays) {
                        $message = "Requested leave days ($days) exceed remaining balance ($remainingDays).";
                        $messageType = "error";
                    } else {
                        // Check if logged-in user is admin or manager
                        $isAutoApprove = in_array($roles, ['Admin', 'Manager']);

                        if ($isAutoApprove) {
                            // Auto approve
                            $stmt = $shiftConn->prepare("
                                INSERT INTO leave_requests 
                                (employee_id, leave_type_id, start_date, end_date, total_days, reason, status, approved_by, approved_date) 
                                VALUES (?, ?, ?, ?, ?, ?, 'Approved', ?, NOW())
                            ");
                            $stmt->bind_param("sississ", $employee_id, $leave_type_id, $start_date, $end_date, $days, $reason, $loggedInUserId);
                        } else {
                            // Regular request (pending)
                            $stmt = $shiftConn->prepare("
                                INSERT INTO leave_requests 
                                (employee_id, leave_type_id, start_date, end_date, total_days, reason, status) 
                                VALUES (?, ?, ?, ?, ?, ?, 'Pending')
                            ");
                            $stmt->bind_param("sissis", $employee_id, $leave_type_id, $start_date, $end_date, $days, $reason);
                        }

                        if ($stmt->execute()) {
                            // Update used_days if auto-approved
                            if ($isAutoApprove) {
                                $stmtUpdate = $shiftConn->prepare("
                                    UPDATE employee_leave_balance 
                                    SET used_days = used_days + ? 
                                    WHERE employee_id = ? AND leave_type_id = ?
                                ");
                                $stmtUpdate->bind_param("isi", $days, $employee_id, $leave_type_id);
                                $stmtUpdate->execute();
                                $stmtUpdate->close();
                            }

                            $message = $isAutoApprove 
                                ? "Leave request submitted and auto-approved!" 
                                : "Leave request submitted successfully!";
                            $messageType = "success";
                        } else {
                            $message = "Error: " . $shiftConn->error;
                            $messageType = "error";
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}


// ==========================
// UPDATE STATUS (Approve/Reject/Cancel)
// ==========================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    if (in_array($action, ['Approved','Rejected','Cancelled'])) {

        if ($approver) { // Now guaranteed to exist
            $stmt = $shiftConn->prepare("UPDATE leave_requests 
                SET status=?, approved_by=?, approved_date=NOW() 
                WHERE leave_request_id=?");
            $stmt->bind_param("ssi", $action, $approver, $id);
            if ($stmt->execute()) {
                $message = "Leave request $action successfully!";
                $messageType = "success";
            } else {
                $message = "Error: " . $shiftConn->error;
                $messageType = "error";
            }
            $stmt->close();
        } else {
            $message = "No approver logged in!";
            $messageType = "error";
        }
    }
}

// ==========================
// DELETE REQUEST
// ==========================
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $stmt = $shiftConn->prepare("DELETE FROM leave_requests WHERE leave_request_id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Leave request deleted!";
        $messageType = "success";
    } else {
        $message = "Error deleting request: " . $shiftConn->error;
        $messageType = "error";
    }
    $stmt->close();
}

// ==========================
// FETCH EMPLOYEES & LEAVE TYPES
// ==========================


// Employees (include gender)
$employees = $shiftConn->query("
    SELECT employee_id, first_name, last_name, gender 
    FROM hr3_system.employees 
    ORDER BY first_name
")->fetch_all(MYSQLI_ASSOC);

// Leave Types (include gender)
$leaveTypes = $shiftConn->query("
    SELECT leave_type_id, leave_name, description, max_days_per_year, gender
    FROM leave_types 
    ORDER BY leave_name
")->fetch_all(MYSQLI_ASSOC);

// ==========================
// FETCH LEAVE REQUESTS WITH APPROVER NAMES
// ==========================
$requests = $shiftConn->query("
    SELECT lr.*, lt.leave_name, 
           e.first_name AS emp_first, e.last_name AS emp_last,
           a.first_name AS approver_first, a.last_name AS approver_last
    FROM leave_requests lr
    JOIN leave_types lt ON lr.leave_type_id=lt.leave_type_id
    JOIN hr3_system.employees e ON lr.employee_id=e.employee_id
    LEFT JOIN hr3_system.employees a ON lr.approved_by=a.employee_id
    ORDER BY lr.created_at DESC
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

      <div class="bg-white shadow-md rounded-2xl p-8 w-full mx-auto mt-8">


        <!-- Feedback Message -->
        <?php if ($message): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-white <?= $messageType === 'success' ? 'bg-green-500' : 'bg-red-500' ?>">
          <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>


        
<?php
// Assume $roles contains the role of the currently logged-in user
// e.g., $roles = $_SESSION['user_role'];

if (in_array($roles, ['Admin', 'Manager'])): 
?>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$roles = $_SESSION['roles'] ?? 'Employee';
$loggedInUserId = $_SESSION['employee_id'] ?? null;

// DB Connections
include __DIR__ . '/../dbconnection/dbEmployee.php';
$empConn = $conn;
include __DIR__ . '/../dbconnection/mainDB.php';
$shiftConn = $conn;

// ==========================
// DASHBOARD METRICS
// ==========================

// Total employees
$empCountRes = $empConn->query("SELECT COUNT(*) AS total FROM employees");
$totalEmployees = $empCountRes->fetch_assoc()['total'] ?? 0;

// Total leave requests
$totalLeavesRes = $shiftConn->query("SELECT COUNT(*) AS total FROM leave_requests");
$totalLeaves = $totalLeavesRes->fetch_assoc()['total'] ?? 0;

// Pending leave requests
$pendingLeavesRes = $shiftConn->query("SELECT COUNT(*) AS total FROM leave_requests WHERE status='Pending'");
$pendingLeaves = $pendingLeavesRes->fetch_assoc()['total'] ?? 0;

// Approved leave requests
$approvedLeavesRes = $shiftConn->query("SELECT COUNT(*) AS total FROM leave_requests WHERE status='Approved'");
$approvedLeaves = $approvedLeavesRes->fetch_assoc()['total'] ?? 0;

// Rejected leave requests
$rejectedLeavesRes = $shiftConn->query("SELECT COUNT(*) AS total FROM leave_requests WHERE status='Rejected'");
$rejectedLeaves = $rejectedLeavesRes->fetch_assoc()['total'] ?? 0;

// Upcoming leaves (from today)
$today = date("Y-m-d");
$upcomingLeavesRes = $shiftConn->prepare("
    SELECT COUNT(*) AS total 
    FROM leave_requests 
    WHERE start_date >= ? AND status='Approved'
");
$upcomingLeavesRes->bind_param("s", $today);
$upcomingLeavesRes->execute();
$upcomingLeavesData = $upcomingLeavesRes->get_result()->fetch_assoc();
$upcomingLeaves = $upcomingLeavesData['total'] ?? 0;
$upcomingLeavesRes->close();
?>

<!-- Dashboard Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">

    <!-- Total Employees -->
    <div class="bg-white shadow-lg rounded-2xl p-6 flex items-center justify-between">
        <div>
            <h3 class="text-gray-500 text-sm font-medium">Total Employees</h3>
            <p class="text-2xl font-bold text-gray-800"><?= $totalEmployees ?></p>
        </div>
        <i data-lucide="users" class="w-10 h-10 text-blue-500"></i>
    </div>

    <!-- Total Leave Requests -->
    <div class="bg-white shadow-lg rounded-2xl p-6 flex items-center justify-between">
        <div>
            <h3 class="text-gray-500 text-sm font-medium">Total Leave Requests</h3>
            <p class="text-2xl font-bold text-gray-800"><?= $totalLeaves ?></p>
        </div>
        <i data-lucide="file-text" class="w-10 h-10 text-purple-500"></i>
    </div>

    <!-- Pending Leaves -->
    <div class="bg-white shadow-lg rounded-2xl p-6 flex items-center justify-between">
        <div>
            <h3 class="text-gray-500 text-sm font-medium">Pending Leaves</h3>
            <p class="text-2xl font-bold text-gray-800"><?= $pendingLeaves ?></p>
        </div>
        <i data-lucide="clock" class="w-10 h-10 text-yellow-500"></i>
    </div>

    <!-- Approved Leaves -->
    <div class="bg-white shadow-lg rounded-2xl p-6 flex items-center justify-between">
        <div>
            <h3 class="text-gray-500 text-sm font-medium">Approved Leaves</h3>
            <p class="text-2xl font-bold text-gray-800"><?= $approvedLeaves ?></p>
        </div>
        <i data-lucide="check-circle" class="w-10 h-10 text-green-500"></i>
    </div>

    <!-- Rejected Leaves -->
    <div class="bg-white shadow-lg rounded-2xl p-6 flex items-center justify-between">
        <div>
            <h3 class="text-gray-500 text-sm font-medium">Rejected Leaves</h3>
            <p class="text-2xl font-bold text-gray-800"><?= $rejectedLeaves ?></p>
        </div>
        <i data-lucide="x-circle" class="w-10 h-10 text-red-500"></i>
    </div>

    <!-- Upcoming Leaves -->
    <div class="bg-white shadow-lg rounded-2xl p-6 flex items-center justify-between">
        <div>
            <h3 class="text-gray-500 text-sm font-medium">Upcoming Leaves</h3>
            <p class="text-2xl font-bold text-gray-800"><?= $upcomingLeaves ?></p>
        </div>
        <i data-lucide="calendar" class="w-10 h-10 text-indigo-500"></i>
    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    lucide.createIcons();
});
</script>

<!-- Leave Assign -->
<div class="bg-white shadow rounded-2xl p-6">
  <h3 class="text-lg font-semibold mb-4">Assign Leave</h3>
  <form method="POST" class="grid grid-cols-2 gap-4">
    <!-- Employee input -->
    <div>
      <label class="block">Employee</label>
      <input type="text" id="employeeInput" class="w-full border p-2 rounded" placeholder="Type employee name..." autocomplete="off" required>
      <div id="suggestions" class="border rounded mt-1 max-h-40 overflow-auto hidden"></div>
      <p id="employeeGender" class="mt-1 text-sm text-gray-600 hidden"></p> <!-- 🔥 Gender shows here -->
    </div>

    <!-- Leave Type -->
    <div>
      <label class="block">Leave Type</label>
      <select id="leaveTypeSelect" name="leave_type_id" class="w-full border p-2 rounded" required>
        <option value="">Select Type</option>
        <!-- Options injected by JS -->
      </select>
    </div>

    <!-- Leave Details -->
    <div class="col-span-2 mt-2">
      <div id="leaveDetails" class="p-3 border rounded bg-gray-50 hidden">
        <p><strong>Description:</strong> <span id="leaveDescription"></span></p>
        <p><strong>Max Days per Year:</strong> <span id="leaveMaxDays"></span></p>
      </div>
    </div>

    <!-- Dates -->
    <div>
      <label class="block">Start Date</label>
      <input type="date" name="start_date" class="w-full border p-2 rounded" required>
    </div>
    <div>
      <label class="block">End Date</label>
      <input type="date" name="end_date" class="w-full border p-2 rounded" required>
    </div>

    <!-- Reason -->
    <div class="col-span-2">
      <label class="block">Reason</label>
      <textarea name="reason" class="w-full border p-2 rounded"></textarea>
    </div>

    <!-- Submit -->
    <div class="col-span-2 flex justify-end">
      <button type="submit" name="add_leave_request" class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto">Assign Leave</button>
    </div>
  </form>
</div>

<script>
  // Employees & leave types from PHP
  const employees = <?php echo json_encode($employees); ?>; 
  const leaveTypes = <?php echo json_encode($leaveTypes); ?>; 

  const input = document.getElementById('employeeInput');
  const suggestions = document.getElementById('suggestions');
  const empGenderEl = document.getElementById('employeeGender');

  const leaveTypeSelect = document.getElementById('leaveTypeSelect');
  const leaveDetails = document.getElementById('leaveDetails');
  const leaveDescription = document.getElementById('leaveDescription');
  const leaveMaxDays = document.getElementById('leaveMaxDays');

  // 🔥 Populate leave type dropdown based on gender
  function populateLeaveTypes(empGender) {
    const genderNorm = (empGender || "Both").toLowerCase().trim();
    leaveTypeSelect.innerHTML = '<option value="">Select Type</option>'; // reset

    let added = 0;
    leaveTypes.forEach(lt => {
      const ltGender = (lt.gender || "Both").toLowerCase().trim();

      if (ltGender === "both" || ltGender === genderNorm) {
        const option = document.createElement("option");
        option.value = lt.leave_type_id;
        option.textContent = lt.leave_name;
        option.dataset.description = lt.description;
        option.dataset.maxdays = lt.max_days_per_year;
        leaveTypeSelect.appendChild(option);
        added++;
      }
    });

    // reset leave details
    leaveDetails.classList.add('hidden');
    console.log("Leave options added:", added);
  }

  // Employee search
  input.addEventListener('input', () => {
    const value = input.value.toLowerCase();
    suggestions.innerHTML = '';

    if (!value) {
      suggestions.classList.add('hidden');
      return;
    }

    const matches = employees.filter(emp =>
      (emp.first_name + ' ' + emp.last_name).toLowerCase().includes(value)
    );

    if (matches.length === 0) {
      suggestions.classList.add('hidden');
      return;
    }

    matches.forEach(emp => {
      const div = document.createElement('div');
      div.classList.add('p-2', 'cursor-pointer', 'hover:bg-gray-200');
      div.textContent = emp.first_name + ' ' + emp.last_name;
      div.addEventListener('click', () => {
        input.value = emp.first_name + ' ' + emp.last_name;
        suggestions.classList.add('hidden');

        // Hidden employee_id
        let hiddenInput = document.getElementById('employee_id');
        if (!hiddenInput) {
          hiddenInput = document.createElement('input');
          hiddenInput.type = 'hidden';
          hiddenInput.name = 'employee_id';
          hiddenInput.id = 'employee_id';
          input.parentNode.appendChild(hiddenInput);
        }
        hiddenInput.value = emp.employee_id;

        // 🔥 Show employee gender
        empGenderEl.textContent = "Gender: " + (emp.gender || "Not specified");
        empGenderEl.classList.remove('hidden');

        // 🔥 Populate leave types by employee gender
        populateLeaveTypes(emp.gender);
      });
      suggestions.appendChild(div);
    });

    suggestions.classList.remove('hidden');
  });

  // Hide suggestions when clicking outside
  document.addEventListener('click', (e) => {
    if (!input.contains(e.target) && !suggestions.contains(e.target)) {
      suggestions.classList.add('hidden');
    }
  });

  // Show leave details on change
  leaveTypeSelect.addEventListener('change', () => {
    const selectedOption = leaveTypeSelect.selectedOptions[0];
    if (selectedOption && selectedOption.value) {
      leaveDescription.textContent = selectedOption.dataset.description;
      leaveMaxDays.textContent = selectedOption.dataset.maxdays;
      leaveDetails.classList.remove('hidden');
    } else {
      leaveDetails.classList.add('hidden');
    }
  });
</script>


<script>
  const leaveSelect = document.getElementById('leaveTypeSelect');
  const leaveDetails = document.getElementById('leaveDetails');
  const leaveDescription = document.getElementById('leaveDescription');
  const leaveMaxDays = document.getElementById('leaveMaxDays');

  leaveSelect.addEventListener('change', () => {
    const selectedOption = leaveSelect.selectedOptions[0];
    if (selectedOption.value) {
      leaveDescription.textContent = selectedOption.dataset.description;
      leaveMaxDays.textContent = selectedOption.dataset.maxdays;
      leaveDetails.classList.remove('hidden');
    } else {
      leaveDetails.classList.add('hidden');
    }
  });
</script>



<!-- Leave Requests Table -->
<div class="bg-white shadow rounded-2xl p-6">
  <h3 class="text-lg font-semibold mb-4">Leave Requests</h3>
  <table class="w-full border-collapse border">
    <thead class="bg-gray-200">
      <tr>
        <th class="border px-3 py-2">Leave Id</th>
        <th class="border px-3 py-2">Employee</th>
        <th class="border px-3 py-2">Leave Type</th>
        <th class="border px-3 py-2">Dates</th>
        <th class="border px-3 py-2">Days</th>
        <th class="border px-3 py-2">Status</th>
        <th class="border px-3 py-2">Approved By</th>
        <th class="border px-3 py-2">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($requests as $r): ?>
      <tr class="hover:bg-gray-50">
        <td class="border px-3 py-2"><?= $r['leave_request_id'] ?></td>
        <td class="border px-3 py-2"><?= htmlspecialchars($r['emp_first'].' '.$r['emp_last']) ?></td>
        <td class="border px-3 py-2"><?= htmlspecialchars($r['leave_name']) ?></td>
        <td class="border px-3 py-2"><?= $r['start_date'] ?> → <?= $r['end_date'] ?></td>
        <td class="border px-3 py-2 text-center"><?= $r['total_days'] ?></td>
        <td class="border px-3 py-2"><?= $r['status'] ?></td>
        <td class="border px-3 py-2">
          <?= $r['approver_first'] ? htmlspecialchars($r['approver_first'].' '.$r['approver_last']) : '-' ?>
        </td>
        <td class="border px-3 py-2 flex space-x-2">
          <?php if ($r['status']=='Pending'): ?>
          <a href="?action=Approved&id=<?= $r['leave_request_id'] ?>" class="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600">Approve</a>
          <a href="?action=Rejected&id=<?= $r['leave_request_id'] ?>" class="px-3 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600">Reject</a>
          <?php endif; ?>
          <button onclick="openDeleteModal('<?= $r['leave_request_id'] ?>')" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600">Delete</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
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
// Open modal with animation
function openDeleteModal(id) {
    const modal = document.getElementById("deleteModal");
    const confirmBtn = document.getElementById("confirmDeleteBtn");
    confirmBtn.href = "?delete_id=" + id;

    modal.classList.remove("hidden");
    setTimeout(() => {
        modal.classList.remove("opacity-0");      // fade in
        modal.firstElementChild.classList.remove("scale-90"); // scale up
    }, 10); // slight delay to trigger transition
}

// Close modal with animation
function closeDeleteModal() {
    const modal = document.getElementById("deleteModal");
    modal.classList.add("opacity-0");          // fade out
    modal.firstElementChild.classList.add("scale-90"); // scale down
    setTimeout(() => {
        modal.classList.add("hidden");
    }, 300); // match duration-300
}

// Optional: close modal when clicking outside the content
document.getElementById("deleteModal").addEventListener("click", function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>



  
        <?php 
else: 
  
endif; 
?>




<?php
// Assume $roles contains the role of the currently logged-in user
// e.g., $roles = $_SESSION['roles'];

// Admin & Manager → Assign Leave
if (in_array($roles, [ 'Employee'])): 
?> 

<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Employee info
$roles = $_SESSION['roles'] ?? 'Employee';
$employeeId = $_SESSION['employee_id'] ?? null;

// DB Connections
include __DIR__ . '/../dbconnection/mainDB.php';
$shiftConn = $conn;

// Message feedback
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_leave'])) {

    $leave_type_id = intval($_POST['leave_type_id']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = trim($_POST['reason']);

    // Calculate total days
    $start_ts = strtotime($start_date);
    $end_ts = strtotime($end_date);
    $days = ($end_ts - $start_ts) / (60*60*24) + 1;

    // Get max days for selected leave type
    $stmt = $shiftConn->prepare("SELECT max_days_per_year FROM leave_types WHERE leave_type_id = ?");
    $stmt->bind_param("i", $leave_type_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $max_days = $result['max_days_per_year'] ?? 0;
    $stmt->close();

    // Check max days
    if ($days > $max_days) {
        $message = "Requested leave days ($days) exceed the maximum allowed ($max_days) for this leave type.";
        $messageType = "error";
    } else {
        // Insert leave request
        $stmt = $shiftConn->prepare("INSERT INTO leave_requests 
            (employee_id, leave_type_id, start_date, end_date, total_days, reason, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("sissis", $employeeId, $leave_type_id, $start_date, $end_date, $days, $reason);

        if ($stmt->execute()) {
            $message = "Leave request submitted successfully!";
            $messageType = "success";
        } else {
            $message = "Error: " . $shiftConn->error;
            $messageType = "error";
        }
        $stmt->close();
    }
}


$leaveTypes = $shiftConn->query("
    SELECT leave_type_id, leave_name, description, max_days_per_year, gender
    FROM leave_types
    ORDER BY leave_name
")->fetch_all(MYSQLI_ASSOC);


// ==========================
// FETCH EMPLOYEE LEAVE REQUESTS
$requests = $shiftConn->query("
    SELECT lr.*, lt.leave_name, 
           a.first_name AS approver_first, a.last_name AS approver_last
    FROM leave_requests lr
    JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
    LEFT JOIN hr3_system.employees a ON lr.approved_by = a.employee_id
    WHERE lr.employee_id = '{$employeeId}'
    ORDER BY lr.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

?>
<?php
// Get logged-in employee's gender
$employeeGenderResult = $shiftConn->query("
    SELECT gender 
    FROM hr3_system.employees 
    WHERE employee_id = '{$employeeId}'
")->fetch_assoc();

$employeeGender = $employeeGenderResult['gender'] ?? 'Both';
?>
<div class="max-w-xl mx-auto bg-white p-6 rounded shadow mb-6">
    <h2 class="text-xl font-bold mb-4">Request Leave</h2>

    <!-- Feedback -->
    <?php if ($message): ?>
        <div class="mb-4 p-3 rounded <?= $messageType === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div>
            <label class="block mb-1">Leave Type</label>
            <select id="leaveTypeSelect" name="leave_type_id" class="w-full border p-2 rounded" required>
                <option value="">Select Type</option>
            </select>
            <p id="maxDaysDisplay" class="mt-1 text-sm text-gray-600 hidden">
                Max Days per Year: <span id="maxDaysValue"></span>
            </p>
        </div>

        <div>
            <label class="block mb-1">Start Date</label>
            <input type="date" name="start_date" class="w-full border p-2 rounded" required>
        </div>
        <div>
            <label class="block mb-1">End Date</label>
            <input type="date" name="end_date" class="w-full border p-2 rounded" required>
        </div>
        <div>
            <label class="block mb-1">Reason</label>
            <textarea name="reason" class="w-full border p-2 rounded"></textarea>
        </div>
        <div class="text-right">
            <button type="submit" name="request_leave" class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto">Submit Request</button>
        </div>
    </form>
</div>

<script>
const leaveSelect = document.getElementById('leaveTypeSelect');
const maxDaysDisplay = document.getElementById('maxDaysDisplay');
const maxDaysValue = document.getElementById('maxDaysValue');

// Employee gender from PHP
const employeeGender = "<?= strtolower($employeeGender) ?>";

// Leave types from PHP
const leaveTypes = <?= json_encode($leaveTypes) ?>;

// Populate leave types based on gender
function populateLeaveTypes() {
    leaveSelect.innerHTML = '<option value="">Select Type</option>';

    leaveTypes.forEach(lt => {
        const ltGender = (lt.gender || 'Both').toLowerCase().trim();

        if (ltGender === 'both' || ltGender === employeeGender) {
            const option = document.createElement('option');
            option.value = lt.leave_type_id;
            option.textContent = lt.leave_name;
            option.dataset.maxdays = lt.max_days_per_year;
            leaveSelect.appendChild(option);
        }
    });

    maxDaysDisplay.classList.add('hidden');
    maxDaysValue.textContent = '';
}

// Populate leave types on page load
populateLeaveTypes();

// Update max days when a leave type is selected
leaveSelect.addEventListener('change', () => {
    const selectedOption = leaveSelect.selectedOptions[0];
    if (selectedOption.value) {
        maxDaysValue.textContent = selectedOption.dataset.maxdays || 0;
        maxDaysDisplay.classList.remove('hidden');
    } else {
        maxDaysDisplay.classList.add('hidden');
        maxDaysValue.textContent = '';
    }
});
</script>
<div class="max-w-4xl mx-auto p-4">
  <h2 class="text-xl font-bold mb-4 text-gray-800">My Leave Requests</h2>

  <!-- Desktop Table -->
  <div class="hidden md:block bg-white rounded shadow overflow-hidden">
    <table class="w-full border-collapse">
      <thead class="bg-gray-200">
        <tr>
          <th class="border px-3 py-2 text-left">#</th>
          <th class="border px-3 py-2 text-left">Leave Type</th>
          <th class="border px-3 py-2 text-left">Dates</th>
          <th class="border px-3 py-2 text-center">Days</th>
          <th class="border px-3 py-2 text-left">Status</th>
          <th class="border px-3 py-2 text-left">Approved By</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r): ?>
          <tr class="hover:bg-gray-50">
            <td class="border px-3 py-2"><?= $r['leave_request_id'] ?></td>
            <td class="border px-3 py-2"><?= htmlspecialchars($r['leave_name']) ?></td>
            <td class="border px-3 py-2"><?= $r['start_date'] ?> → <?= $r['end_date'] ?></td>
            <td class="border px-3 py-2 text-center"><?= $r['total_days'] ?></td>
            <td class="border px-3 py-2"><?= $r['status'] ?></td>
            <td class="border px-3 py-2"><?= $r['approver_first'] ? htmlspecialchars($r['approver_first'].' '.$r['approver_last']) : '-' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile Cards -->
  <div class="md:hidden space-y-4">
    <?php foreach ($requests as $r): ?>
      <div class="bg-white rounded shadow p-4 border">
        <div class="flex justify-between mb-2">
          <span class="font-semibold text-gray-700">#</span>
          <span><?= $r['leave_request_id'] ?></span>
        </div>
        <div class="flex justify-between mb-2">
          <span class="font-semibold text-gray-700">Leave Type</span>
          <span><?= htmlspecialchars($r['leave_name']) ?></span>
        </div>
        <div class="flex justify-between mb-2">
          <span class="font-semibold text-gray-700">Dates</span>
          <span><?= $r['start_date'] ?> → <?= $r['end_date'] ?></span>
        </div>
        <div class="flex justify-between mb-2">
          <span class="font-semibold text-gray-700">Days</span>
          <span><?= $r['total_days'] ?></span>
        </div>
        <div class="flex justify-between mb-2">
          <span class="font-semibold text-gray-700">Status</span>
          <span><?= $r['status'] ?></span>
        </div>
        <div class="flex justify-between">
          <span class="font-semibold text-gray-700">Approved By</span>
          <span><?= $r['approver_first'] ? htmlspecialchars($r['approver_first'].' '.$r['approver_last']) : '-' ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>



<?php 
else: 
  
endif; 
?>

</body>
</html>
