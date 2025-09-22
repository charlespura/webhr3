<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$employeeId = $_SESSION['employee_id'] ?? null;
$roles = $_SESSION['roles'] ?? 'Employee'; // Role of logged-in user

$message = '';
$messageType = '';

// ==========================
// DB Connections
// ==========================
include __DIR__ . '/../dbconnection/dbEmployee.php';
$empConn = $conn;
include __DIR__ . '/../dbconnection/mainDB.php';
$shiftConn = $conn;

// ==========================
// Handle Admin Assign Leave Balance
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_balance']) && in_array($roles, ['Admin', 'Manager'])) {
    // Basic inputs (use null coalescing and ensure types)
    $emp_id = $_POST['employee_id'] ?? '';
    $leave_type_ids = $_POST['leave_type_id'] ?? [];
    if (!is_array($leave_type_ids)) {
        // single selection may come as a string
        $leave_type_ids = [$leave_type_ids];
    }
    // sanitize/normalize leave type ids to integers
    $leave_type_ids = array_map('intval', $leave_type_ids);

    $total_days = intval($_POST['total_days'] ?? 0);

    // Validate required fields
    if (empty($emp_id)) {
        $message = " Please select an employee before assigning leave balance.";
        $messageType = 'error';
    } elseif (empty($leave_type_ids)) {
        $message = "Please select at least one leave type before assigning.";
        $messageType = 'error';
    } else {
        // Fetch employee gender for server-side validation
        $stmtEmp = $empConn->prepare("SELECT gender, first_name, last_name FROM employees WHERE employee_id = ? LIMIT 1");
        if ($stmtEmp) {
            $stmtEmp->bind_param('s', $emp_id);
            $stmtEmp->execute();
            $resEmp = $stmtEmp->get_result();
            $stmtEmp->close();

            if ($resEmp && $resEmp->num_rows > 0) {
                $empRow = $resEmp->fetch_assoc();
                $empGender = $empRow['gender'] ?? null;
            } else {
                $message = "Selected employee not found.";
                $messageType = 'error';
                $empGender = null;
            }
        } else {
            $message = "Unable to validate employee (DB error).";
            $messageType = 'error';
            $empGender = null;
        }

        // If employee exists, validate leave types against gender
        $validLeaveTypeIds = [];
        $skippedLeaveNames = [];

        if (empty($message)) {
            foreach ($leave_type_ids as $ltid) {
                // skip invalid ids
                if ($ltid <= 0) continue;

                $stmtLT = $shiftConn->prepare("SELECT leave_name, gender FROM leave_types WHERE leave_type_id = ? LIMIT 1");
                if (!$stmtLT) {
                    continue; // leave it; we'll try other ids
                }
                $stmtLT->bind_param('i', $ltid);
                $stmtLT->execute();
                $resLT = $stmtLT->get_result();
                $stmtLT->close();

                if (!$resLT || $resLT->num_rows === 0) {
                    // unknown leave type id — skip
                    $skippedLeaveNames[] = "ID {$ltid} (unknown)";
                    continue;
                }

                $ltRow = $resLT->fetch_assoc();
                $ltGender = $ltRow['gender'] ?? null;
                $ltName = $ltRow['leave_name'] ?? ("Leave #{$ltid}");

                // If leave type is gender-specific, ensure it matches employee gender
                // Treat null/empty employee gender as "unspecified" and disallow gender-specific leaves
                if ($ltGender !== 'Both' && (!isset($empGender) || strcasecmp(trim($ltGender), trim($empGender)) !== 0)) {
                    $skippedLeaveNames[] = $ltName;
                    continue; // skip this leave type
                }

                // passed validation — add to valid list
                $validLeaveTypeIds[] = $ltid;
            }

            // If no valid leave types after validation, set error
            if (empty($validLeaveTypeIds)) {
                if (!empty($skippedLeaveNames)) {
                    $message = "No leave types could be assigned. Skipped due to gender mismatch or invalid types: " . implode(', ', $skippedLeaveNames);
                } else {
                    $message = " No valid leave types selected.";
                }
                $messageType = 'error';
            } else {
                // Process only valid leave types — insert or update balances
                $processed = [];
                foreach ($validLeaveTypeIds as $leave_type_id) {
                    // Check if balance exists
                    $stmt = $shiftConn->prepare("SELECT * FROM employee_leave_balance WHERE employee_id=? AND leave_type_id=?");
                    if (!$stmt) {
                        continue; // skip on prepare error
                    }
                    $stmt->bind_param("si", $emp_id, $leave_type_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result && $result->num_rows > 0) {
                        // Update existing balance
                        $stmtUpdate = $shiftConn->prepare("UPDATE employee_leave_balance SET total_days=? WHERE employee_id=? AND leave_type_id=?");
                        if ($stmtUpdate) {
                            $stmtUpdate->bind_param("isi", $total_days, $emp_id, $leave_type_id);
                            $stmtUpdate->execute();
                            $stmtUpdate->close();
                        }
                    } else {
                        // Insert new balance
                        $stmtInsert = $shiftConn->prepare("INSERT INTO employee_leave_balance (employee_id, leave_type_id, total_days, used_days) VALUES (?, ?, ?, 0)");
                        if ($stmtInsert) {
                            $stmtInsert->bind_param("sii", $emp_id, $leave_type_id, $total_days);
                            $stmtInsert->execute();
                            $stmtInsert->close();
                        }
                    }

                    $stmt->close();
                    $processed[] = $leave_type_id;
                }

                // Build success message and include skipped info if any
                $messageParts = [];
                if (!empty($processed)) {
                    $messageParts[] = " Leave balances updated for " . count($processed) . " leave type(s).";
                    $messageType = 'success';
                }
                if (!empty($skippedLeaveNames)) {
                    $messageParts[] = " Skipped: " . implode(', ', $skippedLeaveNames);
                    // keep success if something processed; otherwise mark error
                    if (empty($processed)) {
                        $messageType = 'error';
                    }
                }

                $message = implode(' ', $messageParts);
            }
        }
    }
}

// ==========================
// Fetch Employees and Leave Types
// ==========================
$employees = $empConn->query("SELECT employee_id, first_name, last_name, gender FROM employees ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

$leaveTypes = $shiftConn->query("SELECT leave_type_id, leave_name, gender FROM leave_types ORDER BY leave_name")
    ->fetch_all(MYSQLI_ASSOC);

// ==========================
// Fetch Employee Leave Balance (for logged-in user)
// ==========================
$balances = $shiftConn->query(
    "SELECT lt.leave_name, elb.total_days, elb.used_days, (elb.total_days - elb.used_days) AS remaining_days
    FROM employee_leave_balance elb
    JOIN leave_types lt ON elb.leave_type_id = lt.leave_type_id
    WHERE elb.employee_id = '" . $shiftConn->real_escape_string($employeeId) . "'"
)->fetch_all(MYSQLI_ASSOC);
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

            <?php if ($message): ?>
            <div class="mb-4 px-4 py-3 rounded-lg text-white <?= $messageType === 'success' ? 'bg-green-500' : 'bg-red-500' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <!-- ========================== -->
            <!-- Admin Assign Leave Balance -->
            <!-- ========================== -->
            <?php if (in_array($roles, ['Admin', 'Manager'])): ?>
            <div class="bg-white shadow-md rounded-2xl p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Assign Leave Balance</h3>
                <form method="POST" class="grid grid-cols-3 gap-4">
           <div>
  <label class="block">Employee</label>
  <input type="text" id="employeeInput" class="w-full border p-2 rounded" placeholder="Type employee name..." autocomplete="off" required>
  <div id="suggestions" class="border rounded mt-1 max-h-40 overflow-auto hidden"></div>
 <p id="employeeGender" class="mt-2 text-sm text-gray-600 hidden"></p>

</div>

<script>
  const employees = <?php echo json_encode($employees); ?>;
  const input = document.getElementById('employeeInput');
  const suggestions = document.getElementById('suggestions');
  const empGenderEl = document.getElementById('employeeGender');

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

        // Show employee gender
        empGenderEl.textContent = "Gender: " + (emp.gender || "Not specified");
        empGenderEl.classList.remove('hidden');

        // ============================
        // Filter leave types by gender
        // ============================
        const leaveTypes = document.querySelectorAll('.leave-type');
     leaveTypes.forEach(lt => {
  const gender = lt.dataset.gender;
  const checkbox = lt.querySelector("input[type='checkbox']");
  if (gender === 'Both' || gender === emp.gender) {
    lt.classList.remove('hidden');
  } else {
    lt.classList.add('hidden');
    checkbox.checked = false; // 🔥 uncheck hidden ones
  }
});

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
</script>


                <div>
    <label class="block mb-2">Leave Types</label>
<div id="leaveTypeContainer">
    <?php foreach ($leaveTypes as $lt): ?>
        <div class="flex items-center mb-1 leave-type" data-gender="<?= $lt['gender'] ?>">
            <input type="checkbox" name="leave_type_id[]" value="<?= $lt['leave_type_id'] ?>" id="leaveType_<?= $lt['leave_type_id'] ?>" class="mr-2">
            <label for="leaveType_<?= $lt['leave_type_id'] ?>"><?= htmlspecialchars($lt['leave_name']) ?></label>
        </div>
    <?php endforeach; ?>
</div>
    </div>



                    <div>
                        <label class="block">Total Days</label>
                        <input type="number" name="total_days" class="w-full border p-2 rounded" required>
                    </div>
                    <div class="col-span-3 flex justify-end mt-4">
                        <button type="submit" name="assign_balance" class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto">Assign / Update Balance</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- ========================== -->
            <!-- Employee Leave Balance Table -->
            <!-- ========================== -->
            <div class="bg-white shadow-md rounded-2xl p-6">
                <h3 class="text-lg font-semibold mb-4">Your Leave Balance</h3>
                <table class="w-full border-collapse border">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="border px-3 py-2">Leave Type</th>
                            <th class="border px-3 py-2">Total Days</th>
                            <th class="border px-3 py-2">Used Days</th>
                            <th class="border px-3 py-2">Remaining Days</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($balances as $b): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border px-3 py-2"><?= htmlspecialchars($b['leave_name']) ?></td>
                            <td class="border px-3 py-2 text-center"><?= $b['total_days'] ?></td>
                            <td class="border px-3 py-2 text-center"><?= $b['used_days'] ?></td>
                            <td class="border px-3 py-2 text-center"><?= $b['remaining_days'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>
</div>
</body>
</html>
