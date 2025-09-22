
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<?php
// Include DB connections
include __DIR__ . '/../dbconnection/dbEmployee.php';
$empConn = $conn; // Employee DB
include __DIR__ . '/../dbconnection/mainDB.php';
$shiftConn = $conn; // Shift DB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_request'])) {
        $ids = $_POST['request_id'] ?? [];
        if (!empty($ids)) {
            if (!is_array($ids)) $ids = [$ids]; // single delete
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('s', count($ids));

            $stmt = $shiftConn->prepare("DELETE FROM employee_shift_requests WHERE request_id IN ($placeholders)");
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();

            $_SESSION['flash_message'] = "✅ Selected request(s) deleted successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }




    $request_id   = bin2hex(random_bytes(16));
    $employee_id  = $_POST['employee_id'] ?? '';
    $shift_id     = $_POST['shift_id'] ?? null;
    $request_date = $_POST['request_date'] ?? '';
    $request_type = $_POST['request_type'] ?? '';
    $notes        = $_POST['notes'] ?? '';

    if ($employee_id && $request_date && $request_type) {

        // Lookup request_type_id
        $typeStmt = $shiftConn->prepare("SELECT type_id FROM request_types WHERE type_name = ?");
        $typeStmt->bind_param("s", $request_type);
        $typeStmt->execute();
        $typeResult = $typeStmt->get_result();
        $typeRow = $typeResult->fetch_assoc();
        $request_type_id = $typeRow['type_id'] ?? null;

        if (!$request_type_id) {
            die("Invalid request type selected.");
        }

        // Determine status: Auto-approve if Admin/Manager
        session_start();
        $roles = $_SESSION['roles'] ?? 'Employee';
        $loggedInUserId = $_SESSION['employee_id'] ?? null;

        if (in_array($roles, ['Admin','Manager'])) {
            // Auto-approved
            $statusStmt = $shiftConn->prepare("SELECT status_id FROM request_statuses WHERE status_name = 'approved'");
            $statusStmt->execute();
            $statusRow = $statusStmt->get_result()->fetch_assoc();
            $status_id = $statusRow['status_id'];
            $approved_by = $loggedInUserId; // keep track of approver
        } else {
            // Default pending
            $statusStmt = $shiftConn->prepare("SELECT status_id FROM request_statuses WHERE status_name = 'pending'");
            $statusStmt->execute();
            $statusRow = $statusStmt->get_result()->fetch_assoc();
            $status_id = $statusRow['status_id'];
            $approved_by = null;
        }

        // Force shift_id to NULL if request type is day_off or shift not selected
        if ($request_type === "day_off" || empty($shift_id)) {
            $shift_id = null;
        }

      $approved_by = in_array($roles, ['Admin','Manager']) ? $loggedInUserId : null;

$stmt = $shiftConn->prepare("
    INSERT INTO employee_shift_requests 
    (request_id, employee_id, shift_id, request_date, request_type_id, status_id, approver_id, approved_at, notes, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
");

$approved_at = $approved_by ? date('Y-m-d H:i:s') : null;

$stmt->bind_param(
    "sssssssss",
    $request_id,
    $employee_id,
    $shift_id,
    $request_date,
    $request_type_id,
    $status_id,
    $approved_by,
    $approved_at,
    $notes
);

        if ($stmt->execute()) {
            $_SESSION['flash_message'] = in_array($roles, ['Admin','Manager']) 
                ? "✅ Shift request submitted and auto-approved!" 
                : "Shift request submitted successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $_SESSION['flash_message'] = "Error: " . $stmt->error;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}


// Fetch employees for dropdown
$employees = $empConn->query("
    SELECT employee_id, CONCAT(first_name, ' ', last_name) AS fullname 
    FROM employees 
    ORDER BY first_name, last_name
");

// Fetch shifts for dropdown
$shifts = $shiftConn->query("
    SELECT shift_id, shift_code, name, start_time, end_time 
    FROM shifts 
    ORDER BY start_time
");

// Fetch existing requests for listing
$requests = $shiftConn->query("
    SELECT r.request_id, r.employee_id, r.shift_id, r.request_date, r.notes, r.created_at,
           rt.type_name AS request_type, rs.status_name AS status,
           s.shift_code, s.name AS shift_name, s.start_time, s.end_time
    FROM employee_shift_requests r
    LEFT JOIN shifts s ON r.shift_id = s.shift_id
    LEFT JOIN request_types rt ON r.request_type_id = rt.type_id
    LEFT JOIN request_statuses rs ON r.status_id = rs.status_id
    ORDER BY r.created_at DESC
");
?>

<?php
// 1️⃣ Sync all approved requests to employee_schedules
$syncApproved = $shiftConn->query("
    INSERT INTO employee_schedules (employee_id, work_date, shift_id, status, created_at, updated_at)
    SELECT 
        r.employee_id, 
        r.request_date, 
        r.shift_id, 
        'scheduled', 
        NOW(), 
        NOW()
    FROM employee_shift_requests r
    JOIN request_statuses rs ON r.status_id = rs.status_id
    WHERE rs.status_name = 'approved'
    ON DUPLICATE KEY UPDATE
        shift_id = VALUES(shift_id),
        status   = VALUES(status),
        updated_at = NOW()
");

// 2️⃣ Then fetch schedules to display
$schedulesResult = $shiftConn->query("
    SELECT * 
    FROM employee_schedules
    ORDER BY work_date, employee_id
");
?>

<?php
// Build employee lookup from employee DB
$employeeLookup = [];
$empResult = $empConn->query("SELECT employee_id, CONCAT(first_name, ' ', last_name) AS fullname FROM employees");
while ($emp = $empResult->fetch_assoc()) {
    $employeeLookup[$emp['employee_id']] = $emp['fullname'];
}

$requests = $shiftConn->query("
    SELECT 
        r.request_id, 
        r.employee_id, 
        r.shift_id, 
        r.request_date, 
        r.notes, 
        r.created_at,
        rt.type_name AS request_type,
        rs.status_name AS status,
        s.shift_code, 
        s.name AS shift_name, 
        s.start_time, 
        s.end_time
    FROM employee_shift_requests r
    LEFT JOIN shifts s ON r.shift_id = s.shift_id
    LEFT JOIN request_types rt ON r.request_type_id = rt.type_id
    LEFT JOIN request_statuses rs ON r.status_id = rs.status_id
    ORDER BY r.created_at DESC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ----------------- APPROVE REQUEST -----------------
    if (isset($_POST['approve_request'])) {
        $reqId = $_POST['request_id'] ?? '';
        if ($reqId === '') {
            error_log("approve_request missing request_id");
        } else {
            // 1) Mark request as approved
            $upd = $shiftConn->prepare("
                UPDATE employee_shift_requests
                SET status_id = (SELECT status_id FROM request_statuses WHERE status_name = 'approved'),
                    approver_id = ?, 
                    approved_at = NOW()
                WHERE request_id = ?
            ");
            $approverId = $_SESSION['user_id'] ?? null; // adjust based on your login session
            $upd->bind_param("ss", $approverId, $reqId);
            $upd->execute();
            $upd->close();

            // 2) Fetch request details
            $stmt = $shiftConn->prepare("
                SELECT r.employee_id, r.shift_id, r.request_date, rt.type_name AS request_type
                FROM employee_shift_requests r
                LEFT JOIN request_types rt ON r.request_type_id = rt.type_id
                WHERE r.request_id = ?
                LIMIT 1
            ");
            $stmt->bind_param("s", $reqId);
            $stmt->execute();
            $res = $stmt->get_result();
            $reqDetails = ($res && $res->num_rows) ? $res->fetch_assoc() : null;
            $stmt->close();

            if ($reqDetails) {
                $employeeId = $reqDetails['employee_id'];
                $shiftId    = $reqDetails['shift_id'] ?? null;
                $reqDate    = $reqDetails['request_date'];
                $reqType    = strtolower(trim($reqDetails['request_type'] ?? ''));

                error_log("approve_request: Applying {$reqType} for employee={$employeeId}, date={$reqDate}, shift={$shiftId}");

                if (!empty($employeeId) && !empty($reqDate)) {
                    switch ($reqType) {
                        case 'extra_shift':
                            $status = 'scheduled';
                            $sql = "
                                INSERT INTO employee_schedules
                                    (employee_id, work_date, shift_id, status, created_at, updated_at)
                                VALUES (?, ?, ?, ?, NOW(), NOW())
                                ON DUPLICATE KEY UPDATE
                                    shift_id   = VALUES(shift_id),
                                    status     = VALUES(status),
                                    updated_at = NOW()
                            ";
                            $i = $shiftConn->prepare($sql);
                            $i->bind_param("ssss", $employeeId, $reqDate, $shiftId, $status);
                            if (!$i->execute()) {
                                error_log("approve_request: failed insert/update schedule: " . $i->error);
                            } else {
                                error_log("approve_request: schedule set to shift_id={$shiftId} for employee={$employeeId} on {$reqDate}");
                            }
                            $i->close();
                            break;

                        case 'day_off':
                            $d = $shiftConn->prepare("
                                DELETE FROM employee_schedules 
                                WHERE employee_id = ? AND work_date = ?
                            ");
                            $d->bind_param("ss", $employeeId, $reqDate);
                            if (!$d->execute()) {
                                error_log("approve_request: failed delete schedule: " . $d->error);
                            } else {
                                error_log("approve_request: day_off removed rows = " . $d->affected_rows);
                            }
                            $d->close();
                            break;

                        case 'half_day':
                            $status = 'half_day';
                            $sql = "
                                INSERT INTO employee_schedules
                                    (employee_id, work_date, shift_id, status, created_at, updated_at)
                                VALUES (?, ?, ?, ?, NOW(), NOW())
                                ON DUPLICATE KEY UPDATE
                                    shift_id   = VALUES(shift_id),
                                    status     = VALUES(status),
                                    updated_at = NOW()
                            ";
                            $h = $shiftConn->prepare($sql);
                            $h->bind_param("ssss", $employeeId, $reqDate, $shiftId, $status);
                            if (!$h->execute()) {
                                error_log("approve_request: failed half_day schedule: " . $h->error);
                            } else {
                                error_log("approve_request: half_day schedule set for employee={$employeeId} on {$reqDate}");
                            }
                            $h->close();
                            break;

                        default:
                            error_log("approve_request: unknown request type '{$reqType}' for request_id={$reqId}");
                            break;
                    }
                }
            } else {
                error_log("approve_request: no request details found for request_id={$reqId}");
            }

            // Refresh page to show updated status
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    // ----------------- REJECT REQUEST -----------------
    if (isset($_POST['reject_request'])) {
        $reqId = $_POST['request_id'] ?? '';
        if ($reqId === '') {
            error_log("reject_request missing request_id");
        } else {
            $stmt = $shiftConn->prepare("
                UPDATE employee_shift_requests
                SET status_id = (SELECT status_id FROM request_statuses WHERE status_name = 'rejected')
                WHERE request_id = ?
            ");
            $stmt->bind_param("s", $reqId);
            if (!$stmt->execute()) {
                error_log("reject_request: update failed: " . $stmt->error);
            }
            $stmt->close();

            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Shift and Schedule</title>
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
          <h2 class="text-xl font-semibold text-gray-800" id="main-content-title">Shift and Schedule</h2>


          <!-- ito yung profile ng may login wag kalimutan lagyan ng session yung profile.php para madetect nya if may login or wala -->
<?php 
include '../profile.php'; 
?>

        </div>

<?php 
include 'shiftnavbar.php';
 ?>


<div class="bg-white shadow-md rounded-2xl p-10 w-full mx-auto mt-10 mb-10">
<?php
// --- Request Stats ---

// Approved
$approvedRes = $shiftConn->query("
    SELECT COUNT(*) AS total 
    FROM employee_shift_requests r
    LEFT JOIN request_statuses rs ON r.status_id = rs.status_id
    WHERE rs.status_name = 'approved'
");
$approvedCount = $approvedRes->fetch_assoc()['total'] ?? 0;

// Rejected
$rejectedRes = $shiftConn->query("
    SELECT COUNT(*) AS total 
    FROM employee_shift_requests r
    LEFT JOIN request_statuses rs ON r.status_id = rs.status_id
    WHERE rs.status_name = 'rejected'
");
$rejectedCount = $rejectedRes->fetch_assoc()['total'] ?? 0;

// Pending
$pendingRes = $shiftConn->query("
    SELECT COUNT(*) AS total 
    FROM employee_shift_requests r
    LEFT JOIN request_statuses rs ON r.status_id = rs.status_id
    WHERE rs.status_name = 'pending'
");
$pendingCount = $pendingRes->fetch_assoc()['total'] ?? 0;
?>

 <h2 class="text-2xl font-bold mt-12 mb-6">Request a Shift</h2>

<!-- Request Dashboard Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6 ">
   

<?php
// Assume $roles contains the role of the currently logged-in user
// e.g., $roles = $_SESSION['user_role'];

if (in_array($roles, ['Admin', 'Manager'])): 
?>


  <!-- Card: Pending Requests -->
  <div class="bg-white shadow-lg rounded-2xl p-6 flex items-center justify-between">
    <div>
      <h3 class="text-gray-500 text-sm font-medium">Pending Requests</h3>
      <p class="text-2xl font-bold text-gray-800"><?= $pendingCount ?></p>
    </div>
    <i data-lucide="hourglass" class="w-10 h-10 text-yellow-500"></i>
  </div>

  <!-- Card: Approved Requests -->
  <div class="bg-white shadow-lg rounded-2xl p-6 flex items-center justify-between">
    <div>
      <h3 class="text-gray-500 text-sm font-medium">Approved Requests</h3>
      <p class="text-2xl font-bold text-gray-800"><?= $approvedCount ?></p>
    </div>
    <i data-lucide="check-circle" class="w-10 h-10 text-green-500"></i>
  </div>

  <!-- Card: Rejected Requests -->
  <div class="bg-white shadow-lg rounded-2xl p-6 flex items-center justify-between">
    <div>
      <h3 class="text-gray-500 text-sm font-medium">Rejected Requests</h3>
      <p class="text-2xl font-bold text-gray-800"><?= $rejectedCount ?></p>
    </div>
    <i data-lucide="x-circle" class="w-10 h-10 text-red-500"></i>
  </div>

</div>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    lucide.createIcons();
  });
</script>







<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="bg-green-100 text-green-700 p-3 mb-4 rounded">
        <?= htmlspecialchars($_SESSION['flash_message']) ?>
    </div>
    <?php unset($_SESSION['flash_message']); ?>
<?php endif; ?>


<!-- Request Form -->
<form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-5">
  <!-- Employee -->
<div>

  <label class="block mb-1 font-medium">Employee</label>
  <select name="employee_id" required class="border rounded w-full p-2">
    <option value="">-- Select Employee --</option>
    <?php foreach ($employees as $e): ?>
      <option value="<?= $e['employee_id'] ?>">
        <?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>


  <!-- Request Date -->
  <div>
      <label class="block mb-1 font-medium">Request Date</label>
      <input type="date" name="request_date" required class="border rounded w-full p-2">
  </div>

  <?php
// Fetch request types dynamically
$requestTypes = $shiftConn->query("SELECT type_name FROM request_types WHERE is_active = 1 ORDER BY type_name");
?>
<div>
    <label class="block mb-1 font-medium">Request Type</label>
    <select name="request_type" id="request_type" required class="border rounded w-full p-2" onchange="toggleShiftSelect()">
        <option value="">-- Select Type --</option>
        <?php while ($rt = $requestTypes->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($rt['type_name']) ?>">
                <?= ucwords(str_replace('_', ' ', htmlspecialchars($rt['type_name']))) ?>
            </option>
        <?php endwhile; ?>
    </select>
</div>

  <!-- Shift -->
  <div id="shift_select_wrapper">
      <label class="block mb-1 font-medium">Shift</label>
      <select name="shift_id" class="border rounded w-full p-2">
          <option value="">-- Select Shift (if applicable) --</option>
          <?php while ($s = $shifts->fetch_assoc()): ?>
              <option value="<?= $s['shift_id'] ?>">
                  <?= htmlspecialchars($s['shift_code'] . " - " . $s['name']) ?>
                  (<?= substr($s['start_time'],0,5) ?> - <?= substr($s['end_time'],0,5) ?>)
              </option>
          <?php endwhile; ?>
      </select>
  </div>

  <!-- Notes -->
  <div class="md:col-span-2">
      <label class="block mb-1 font-medium">Notes</label>
      <textarea name="notes" rows="2" class="border rounded w-full p-2"></textarea>
  </div>

  <!-- Submit -->
  <div class="md:col-span-2 flex justify-end">
      <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto">
          📌 Submit Request
      </button>
  </div>
</form>

<script>
function toggleShiftSelect() {
    const type = document.getElementById('request_type').value;
    const wrapper = document.getElementById('shift_select_wrapper');
    wrapper.style.display = (type === 'day_off' || type === '') ? 'none' : 'block';
}
toggleShiftSelect();
</script>

<!-- Bulk Delete Button -->
<button id="bulkDeleteBtn" onclick="showDeleteModal()" 
        class="bg-red-600 text-white px-4 py-2 rounded mb-4 hidden">
    Delete Selected
</button>

<!-- Requests Table -->
<div class="bg-white shadow-md rounded-2xl p-6 w-full mx-auto mt-10">
    <h2 class="text-xl font-bold mb-4">Submitted Requests</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200 rounded-lg">
            <thead>
                <tr class="bg-gray-100 text-left">
                    <th class="px-4 py-2 border">
                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                    </th>
                    <th class="px-4 py-2 border">Employee</th>
                    <th class="px-4 py-2 border">Request Date</th>
                    <th class="px-4 py-2 border">Type</th>
                    <th class="px-4 py-2 border">Shift</th>
                    <th class="px-4 py-2 border">Notes</th>
                    <th class="px-4 py-2 border">Status</th>
                    <th class="px-4 py-2 border">Submitted</th>
                    <th class="px-4 py-2 border">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($requests->num_rows > 0): ?>
                    <?php while ($row = $requests->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <!-- Checkbox -->
                            <td class="px-4 py-2 border text-center">
                                <input type="checkbox" class="request_checkbox" value="<?= htmlspecialchars($row['request_id']) ?>" onchange="toggleBulkButton()">
                            </td>

                            <!-- Employee Name -->
                            <td class="px-4 py-2 border">
                                <?= htmlspecialchars($employeeLookup[$row['employee_id']] ?? "Unknown") ?>
                            </td>

                            <!-- Request Date -->
                            <td class="px-4 py-2 border"><?= htmlspecialchars($row['request_date']) ?></td>

                            <!-- Request Type -->
                            <td class="px-4 py-2 border capitalize"><?= htmlspecialchars($row['request_type']) ?></td>

                            <!-- Shift -->
                            <td class="px-4 py-2 border">
                                <?= $row['shift_id'] 
                                    ? htmlspecialchars($row['shift_code'] . " - " . $row['shift_name'] . " (" . substr($row['start_time'],0,5) . " - " . substr($row['end_time'],0,5) . ")") 
                                    : "-" ?>
                            </td>

                            <!-- Notes -->
                            <td class="px-4 py-2 border"><?= htmlspecialchars($row['notes'] ?? "") ?></td>

                            <!-- Status + Actions -->
                            <td class="px-4 py-2 border">
                                <?php if ($row['status'] === "pending"): ?>
                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm">Pending</span>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="request_id" value="<?= htmlspecialchars($row['request_id']) ?>">
                                        <button type="submit" name="approve_request" class="ml-2 bg-green-500 hover:bg-green-600 text-white px-2 py-1 rounded text-sm">Approve</button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="request_id" value="<?= htmlspecialchars($row['request_id']) ?>">
                                        <button type="submit" name="reject_request" class="ml-1 bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-sm">Reject</button>
                                    </form>
                                <?php elseif ($row['status'] === "approved"): ?>
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">Approved</span>
                                <?php elseif ($row['status'] === "rejected"): ?>
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm">Rejected</span>
                                <?php else: ?>
                                    <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm">Unknown</span>
                                <?php endif; ?>

                                <!-- Single Delete Button -->
                                <form method="POST" class="inline">
                                    <input type="hidden" name="request_id" value="<?= htmlspecialchars($row['request_id']) ?>">
                                   <!-- Single Delete Button triggers modal -->
<button type="button" 
        class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600"
        onclick="showDeleteModalSingle('<?= htmlspecialchars($row['request_id']) ?>')">
    Delete
</button>
 </form>
                            </td>

                            <!-- Submitted -->
                            <td class="px-4 py-2 border text-sm text-gray-600"><?= htmlspecialchars($row['created_at']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-gray-500">No requests submitted yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50 transition-opacity duration-300">
  <div id="deleteModalContent" 
       class="bg-white rounded-lg shadow-lg w-96 p-6 transform scale-95 opacity-0 transition-all duration-300">
    <h2 class="text-lg font-bold mb-4">Are you Sure?</h2>
    <p id="deleteMessage" class="mb-6 text-gray-600">
      The selected record(s) will be permanently deleted. Are you sure you want to continue?
    </p>
    
    <div class="flex justify-end space-x-3">
      <button onclick="closeDeleteModal()" 
              class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 text-gray-700">
        No, Cancel
      </button>
      <form method="POST" id="bulkDeleteForm">
        <div id="bulkInputs"></div>
        <button type="submit" name="delete_request" 
                class="px-4 py-2 rounded bg-red-600 hover:bg-red-700 text-white">
          Yes, Delete
        </button>
      </form>
    </div>
  </div>
</div>
<script>
function toggleShiftSelect() {
    const type = document.getElementById("request_type").value;
    const shiftWrapper = document.getElementById("shift_select_wrapper");
    shiftWrapper.style.display = (type === "day_off") ? "none" : "block";
}
toggleShiftSelect();

// Bulk selection logic
function toggleSelectAll(source) {
    const checkboxes = document.querySelectorAll('.request_checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    toggleBulkButton();
}

function toggleBulkButton() {
    const selected = document.querySelectorAll('.request_checkbox:checked').length;
    document.getElementById('bulkDeleteBtn').style.display = selected > 0 ? 'inline-block' : 'none';
}

// Show modal for bulk delete
function showDeleteModal() {
    const selected = Array.from(document.querySelectorAll('.request_checkbox:checked'));
    if (selected.length === 0) return;

    populateDeleteModal(selected.map(cb => cb.value), selected.length);
}

// Show modal for single delete
function showDeleteModalSingle(requestId) {
    populateDeleteModal([requestId], 1);
}

// Populate modal inputs and message
function populateDeleteModal(ids, count) {
    const bulkInputs = document.getElementById('bulkInputs');
    bulkInputs.innerHTML = '';

    ids.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'request_id[]';
        input.value = id;
        bulkInputs.appendChild(input);
    });

    // Update modal message dynamically
    const deleteMessage = document.getElementById('deleteMessage');
    deleteMessage.textContent = count === 1 
        ? "This request will be permanently deleted. Are you sure you want to continue?" 
        : `These ${count} requests will be permanently deleted. Are you sure you want to continue?`;

    // Show modal
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('hidden');
    setTimeout(() => {
        document.getElementById('deleteModalContent').classList.add('scale-100', 'opacity-100');
    }, 10);
}

// Close modal
function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    document.getElementById('deleteModalContent').classList.remove('scale-100', 'opacity-100');
    setTimeout(() => modal.classList.add('hidden'), 300);
}
</script>


        <?php 
else: 
  
endif; 
?>




<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$employeeId = $_SESSION['employee_id'] ?? null;
$roles = $_SESSION['roles'] ?? 'Employee';

// DB connections
include __DIR__ . '/../dbconnection/mainDB.php';
$shiftConn = $conn;

// ============================
// HANDLE REQUEST SUBMISSION
// ============================
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_shift'])) {
    $request_id   = bin2hex(random_bytes(16));
    $request_date = $_POST['request_date'] ?? '';
    $request_type = $_POST['request_type'] ?? '';
    $shift_id     = $_POST['shift_id'] ?? null;
    $notes        = trim($_POST['notes'] ?? '');

    if ($employeeId && $request_date && $request_type) {

        // Lookup request_type_id
        $typeStmt = $shiftConn->prepare("SELECT type_id FROM request_types WHERE type_name = ?");
        $typeStmt->bind_param("s", $request_type);
        $typeStmt->execute();
        $typeResult = $typeStmt->get_result();
        $typeRow = $typeResult->fetch_assoc();
        $request_type_id = $typeRow['type_id'] ?? null;
        $typeStmt->close();

        if (!$request_type_id) {
            $message = "Invalid request type.";
            $messageType = "error";
        } else {
            // Lookup pending status
            $statusStmt = $shiftConn->prepare("SELECT status_id FROM request_statuses WHERE status_name = 'pending'");
            $statusStmt->execute();
            $statusResult = $statusStmt->get_result();
            $statusRow = $statusResult->fetch_assoc();
            $status_id = $statusRow['status_id'] ?? null;
            $statusStmt->close();

            if (!$status_id) {
                $message = "Request status not found.";
                $messageType = "error";
            } else {
                if ($request_type === "day_off" || empty($shift_id)) {
                    $shift_id = null;
                }

                // Insert request
                $stmt = $shiftConn->prepare("
                    INSERT INTO employee_shift_requests 
                    (request_id, employee_id, shift_id, request_date, request_type_id, status_id, notes, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->bind_param("sssssss", 
                    $request_id, $employeeId, $shift_id, $request_date, $request_type_id, $status_id, $notes
                );

                if ($stmt->execute()) {
                    $message = "Shift request submitted!";
                    $messageType = "success";
                } else {
                    $message = " Error: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
            }
        }
    } else {
        $message = "Please complete all required fields.";
        $messageType = "error";
    }
}

// ============================
// FETCH DROPDOWN DATA
// ============================
$shifts = $shiftConn->query("
    SELECT shift_id, shift_code, name, start_time, end_time 
    FROM shifts 
    ORDER BY start_time
");

$requestTypes = $shiftConn->query("
    SELECT type_name FROM request_types WHERE is_active = 1 ORDER BY type_name
");

// ============================
// FETCH EMPLOYEE REQUESTS
// ============================
$requests = $shiftConn->query("
    SELECT r.*, rt.type_name, rs.status_name, 
           s.shift_code, s.name AS shift_name, s.start_time, s.end_time
    FROM employee_shift_requests r
    LEFT JOIN request_types rt ON r.request_type_id = rt.type_id
    LEFT JOIN request_statuses rs ON r.status_id = rs.status_id
    LEFT JOIN shifts s ON r.shift_id = s.shift_id
    WHERE r.employee_id = '{$employeeId}'
    ORDER BY r.created_at DESC
")->fetch_all(MYSQLI_ASSOC);
?>







<?php
// Assume $roles contains the role of the currently logged-in user
// e.g., $roles = $_SESSION['user_role'];

if (in_array($roles, [ 'Employee'])): 
?>


<!-- ============================ -->
<!-- REQUEST FORM -->
<div class="max-w-xl mx-auto bg-white p-6 rounded shadow mb-6">
    <h2 class="text-xl font-bold mb-4">Request a Shift</h2>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="mb-4 p-3 rounded <?= $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <!-- Request Date -->
        <div>
            <label class="block mb-1 font-medium">Request Date *</label>
            <input type="date" name="request_date" required class="border rounded w-full p-2">
        </div>

        <!-- Request Type -->
        <div>
            <label class="block mb-1 font-medium">Request Type *</label>
            <select name="request_type" id="request_type" required class="border rounded w-full p-2" onchange="toggleShiftSelect()">
                <option value="">-- Select Type --</option>
                <?php while ($rt = $requestTypes->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($rt['type_name']) ?>">
                        <?= ucwords(str_replace('_',' ', htmlspecialchars($rt['type_name']))) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Shift (optional) -->
        <div id="shift_select_wrapper">
            <label class="block mb-1 font-medium">Shift</label>
            <select name="shift_id" class="border rounded w-full p-2">
                <option value="">-- Select Shift (if applicable) --</option>
                <?php while ($s = $shifts->fetch_assoc()): ?>
                    <option value="<?= $s['shift_id'] ?>">
                        <?= htmlspecialchars($s['shift_code']." - ".$s['name']) ?>
                        (<?= substr($s['start_time'],0,5) ?> - <?= substr($s['end_time'],0,5) ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Notes -->
        <div>
            <label class="block mb-1 font-medium">Notes</label>
            <textarea name="notes" rows="2" class="border rounded w-full p-2"></textarea>
        </div>

        <!-- Submit -->
        <div class="text-right">
            <button type="submit" name="request_shift" class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto">
                📌 Submit Request
            </button>
        </div>
    </form>
</div>


<script>
function toggleShiftSelect() {
    const type = document.getElementById('request_type').value;
    const wrapper = document.getElementById('shift_select_wrapper');
    wrapper.style.display = (type === 'day_off' || type === '') ? 'none' : 'block';
}
toggleShiftSelect();
</script>

<!-- ============================ -->
<!-- Submitted Requests -->
<div class="max-w-4xl mx-auto bg-white p-6 rounded shadow">
    <h2 class="text-xl font-bold mb-4">My Shift Requests</h2>

    <?php if (count($requests) === 0): ?>
        <p class="text-gray-500">No requests submitted yet.</p>
    <?php else: ?>
        <table class="w-full border-collapse border">
            <thead class="bg-gray-200">
                <tr>
                    <th class="border px-3 py-2">Date</th>
                    <th class="border px-3 py-2">Type</th>
                    <th class="border px-3 py-2">Shift</th>
                    <th class="border px-3 py-2">Notes</th>
                    <th class="border px-3 py-2">Status</th>
                    <th class="border px-3 py-2">Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="border px-3 py-2"><?= $r['request_date'] ?></td>
                    <td class="border px-3 py-2"><?= ucwords(str_replace('_',' ',$r['type_name'])) ?></td>
                    <td class="border px-3 py-2">
                        <?= $r['shift_code'] ? htmlspecialchars($r['shift_code']." - ".$r['shift_name'])." (".substr($r['start_time'],0,5)." - ".substr($r['end_time'],0,5).")" : '-' ?>
                    </td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($r['notes']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($r['status_name']) ?></td>
                    <td class="border px-3 py-2"><?= $r['created_at'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
        <?php 
else: 
  
endif; 
?>



</body>
</html>
