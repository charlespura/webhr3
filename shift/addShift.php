
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
      <main class="p-6 ">
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
    <h2 class="text-2xl font-bold mb-6">Manage Shifts</h2>

<?php
include __DIR__ . '/../dbconnection/mainDB.php';
$shiftConn = $conn;

// --- HANDLE ADD/EDIT/DELETE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ADD SHIFT
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $shift_id      = bin2hex(random_bytes(16));
        $shift_code    = $_POST['shift_code'] ?? '';
        $name          = $_POST['name'] ?? '';
        $start_time    = $_POST['start_time'] ?? '';
        $end_time      = $_POST['end_time'] ?? '';
        $break_minutes = $_POST['break_minutes'] ?? 0;
        $is_overnight  = isset($_POST['is_overnight']) ? 1 : 0;

        if ($shift_code && $name && $start_time && $end_time) {
            $stmt = $shiftConn->prepare("INSERT INTO shifts 
                (shift_id, shift_code, name, start_time, end_time, break_minutes, is_overnight) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssi", $shift_id, $shift_code, $name, $start_time, $end_time, $break_minutes, $is_overnight);
            if ($stmt->execute()) {
                echo '<div class="bg-green-100 text-green-700 p-3 mb-4 rounded">Shift added successfully!</div>';
            } else {
                echo '<div class="bg-red-100 text-red-700 p-3 mb-4 rounded"> Error: ' . htmlspecialchars($stmt->error) . '</div>';
            }
        } else {
            echo '<div class="bg-red-100 text-red-700 p-3 mb-4 rounded"> Please fill in all required fields.</div>';
        }
    }

    // EDIT SHIFT
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $shift_id      = $_POST['shift_id'] ?? '';
        $shift_code    = $_POST['shift_code'] ?? '';
        $name          = $_POST['name'] ?? '';
        $start_time    = $_POST['start_time'] ?? '';
        $end_time      = $_POST['end_time'] ?? '';
        $break_minutes = $_POST['break_minutes'] ?? 0;
        $is_overnight  = isset($_POST['is_overnight']) ? 1 : 0;

        if ($shift_id && $shift_code && $name && $start_time && $end_time) {
            $stmt = $shiftConn->prepare("UPDATE shifts SET shift_code=?, name=?, start_time=?, end_time=?, break_minutes=?, is_overnight=? WHERE shift_id=?");
            $stmt->bind_param("ssssiis", $shift_code, $name, $start_time, $end_time, $break_minutes, $is_overnight, $shift_id);
            if ($stmt->execute()) {
                echo '<div class="bg-green-100 text-green-700 p-3 mb-4 rounded">Shift updated successfully!</div>';
            } else {
                echo '<div class="bg-red-100 text-red-700 p-3 mb-4 rounded"> Error: ' . htmlspecialchars($stmt->error) . '</div>';
            }
        }
    }

    // DELETE SHIFT
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $shift_id = $_POST['shift_id'] ?? '';
        if ($shift_id) {
            $stmt = $shiftConn->prepare("DELETE FROM shifts WHERE shift_id=?");
            $stmt->bind_param("s", $shift_id);
            if ($stmt->execute()) {
                echo '<div class="bg-green-100 text-green-700 p-3 mb-4 rounded">Shift deleted successfully!</div>';
            } else {
                echo '<div class="bg-red-100 text-red-700 p-3 mb-4 rounded"> Error: ' . htmlspecialchars($stmt->error) . '</div>';
            }
        }
    }
}

// FETCH ALL SHIFTS
$shifts = $shiftConn->query("SELECT * FROM shifts ORDER BY start_time");
?>

<!-- ADD SHIFT FORM -->
<form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
    <input type="hidden" name="action" value="add">
    <div>
        <label class="block mb-1 font-medium">Shift Code <span class="text-red-500">*</span></label>
        <input type="text" name="shift_code" required class="border rounded w-full p-2">
    </div>
    <div>
        <label class="block mb-1 font-medium">Shift Name <span class="text-red-500">*</span></label>
        <input type="text" name="name" required class="border rounded w-full p-2">
    </div>
    <div>
        <label class="block mb-1 font-medium">Start Time <span class="text-red-500">*</span></label>
        <input type="time" name="start_time" required class="border rounded w-full p-2">
    </div>
    <div>
        <label class="block mb-1 font-medium">End Time <span class="text-red-500">*</span></label>
        <input type="time" name="end_time" required class="border rounded w-full p-2">
    </div>
    <div>
        <label class="block mb-1 font-medium">Break Minutes</label>
        <input type="number" name="break_minutes" value="0" min="0" class="border rounded w-full p-2">
    </div>
    <div class="flex items-center">
        <input type="checkbox" name="is_overnight" value="1" class="mr-2">
        <label class="font-medium">Overnight Shift</label>
    </div>
    <div class="md:col-span-2 flex justify-end">
        <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto">➕ Add Shift</button>
    </div>
</form>
<div class="overflow-x-auto">
    <!-- SHIFT TABLE -->
    <h2 class="text-2xl font-bold mb-4">All Shifts</h2>
    <table class="min-w-full bg-white border rounded shadow">
        <thead class="bg-gray-100">
            <tr>
                <th class="py-2 px-3 border text-left text-sm md:text-base">Code</th>
                <th class="py-2 px-3 border text-left text-sm md:text-base">Name</th>
                <th class="py-2 px-3 border text-left text-sm md:text-base">Start</th>
                <th class="py-2 px-3 border text-left text-sm md:text-base">End</th>
                <th class="py-2 px-3 border text-left text-sm md:text-base">Break</th>
                <th class="py-2 px-3 border text-left text-sm md:text-base">Overnight</th>
                <th class="py-2 px-3 border text-left text-sm md:text-base">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while($s = $shifts->fetch_assoc()): ?>
            <tr class="hover:bg-gray-50">
                <td class="py-2 px-3 border text-sm md:text-base"><?= htmlspecialchars($s['shift_code']) ?></td>
                <td class="py-2 px-3 border text-sm md:text-base"><?= htmlspecialchars($s['name']) ?></td>
                <td class="py-2 px-3 border text-sm md:text-base"><?= $s['start_time'] ?></td>
                <td class="py-2 px-3 border text-sm md:text-base"><?= $s['end_time'] ?></td>
                <td class="py-2 px-3 border text-sm md:text-base"><?= $s['break_minutes'] ?> min</td>
                <td class="py-2 px-3 border text-sm md:text-base"><?= $s['is_overnight'] ? 'Yes' : 'No' ?></td>
                <td class="py-2 px-3 border text-sm md:text-base space-x-2">
                    <button type="button" onclick="openEditModal('<?= $s['shift_id'] ?>','<?= htmlspecialchars($s['shift_code'],ENT_QUOTES) ?>','<?= htmlspecialchars($s['name'],ENT_QUOTES) ?>','<?= $s['start_time'] ?>','<?= $s['end_time'] ?>','<?= $s['break_minutes'] ?>','<?= $s['is_overnight'] ?>')" class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto"> Edit</button>
                    <button type="button" onclick="openDeleteModal('<?= $s['shift_id'] ?>','<?= htmlspecialchars($s['name'],ENT_QUOTES) ?>')" class="bg-red-500 px-2 py-1 rounded text-white"> Delete</button>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>


<!-- EDIT MODAL -->
<div id="editModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50 transition-opacity duration-300">
  <div class="bg-white rounded-lg shadow-lg w-96 p-6 transform scale-95 opacity-0 transition-all duration-300">
    <h2 class="text-lg font-bold mb-4">Edit Shift</h2>
    <form id="editShiftForm" method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="shift_id" id="editShiftId">
        <div class="mb-2">
            <label class="font-medium">Shift Code</label>
            <input type="text" name="shift_code" id="editShiftCode" class="border rounded w-full p-2" required>
        </div>
        <div class="mb-2">
            <label class="font-medium">Shift Name</label>
            <input type="text" name="name" id="editShiftName" class="border rounded w-full p-2" required>
        </div>
        <div class="mb-2">
            <label class="font-medium">Start Time</label>
            <input type="time" name="start_time" id="editStartTime" class="border rounded w-full p-2" required>
        </div>
        <div class="mb-2">
            <label class="font-medium">End Time</label>
            <input type="time" name="end_time" id="editEndTime" class="border rounded w-full p-2" required>
        </div>
        <div class="mb-2">
            <label class="font-medium">Break Minutes</label>
            <input type="number" name="break_minutes" id="editBreak" class="border rounded w-full p-2">
        </div>
        <div class="flex items-center mb-4">
            <input type="checkbox" name="is_overnight" id="editOvernight" class="mr-2">
            <label class="font-medium">Overnight Shift</label>
        </div>
        <div class="flex justify-end space-x-2">
            <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 text-gray-700">Cancel</button>
            <button type="submit" class="px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white">Save</button>
        </div>
    </form>
  </div>
</div>

<!-- DELETE MODAL -->
<div id="deleteModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50 transition-opacity duration-300">
  <div class="bg-white rounded-lg shadow-lg w-96 p-6 transform scale-95 opacity-0 transition-all duration-300">
    <h2 class="text-lg font-bold mb-4">Are you Sure?</h2>
    <p id="deleteMessage" class="mb-6 text-gray-600"></p>
    <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="shift_id" id="deleteShiftId">
        <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 text-gray-700">No, Cancel</button>
            <button type="submit" class="px-4 py-2 rounded bg-red-600 hover:bg-red-700 text-white">Yes, Delete</button>
        </div>
    </form>
  </div>
</div>

<script>
function openEditModal(id, code, name, start, end, breakMinutes, overnight){
    document.getElementById('editShiftId').value = id;
    document.getElementById('editShiftCode').value = code;
    document.getElementById('editShiftName').value = name;
    document.getElementById('editStartTime').value = start;
    document.getElementById('editEndTime').value = end;
    document.getElementById('editBreak').value = breakMinutes;
    document.getElementById('editOvernight').checked = overnight==1;
    const modal = document.getElementById('editModal');
    modal.classList.remove('hidden');
    setTimeout(()=>{ modal.children[0].classList.add('scale-100','opacity-100'); },50);
}

function closeEditModal(){
    const modal = document.getElementById('editModal');
    modal.children[0].classList.remove('scale-100','opacity-100');
    setTimeout(()=> modal.classList.add('hidden'),300);
}

function openDeleteModal(id, name){
    document.getElementById('deleteMessage').innerText = `Shift "${name}" will be permanently deleted. Are you sure?`;
    document.getElementById('deleteShiftId').value = id;
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('hidden');
    setTimeout(()=>{ modal.children[0].classList.add('scale-100','opacity-100'); },50);
}

function closeDeleteModal(){
    const modal = document.getElementById('deleteModal');
    modal.children[0].classList.remove('scale-100','opacity-100');
    setTimeout(()=> modal.classList.add('hidden'),300);
}
</script>


</body>
</html>
