<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB Connection
include __DIR__ . '/../dbconnection/mainDB.php';
$conn = $conn; // main DB

$message = '';
$messageType = '';

// ADD Leave Type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_leave'])) {
    $name = trim($_POST['leave_name']);
    $description = trim($_POST['description']);
    $max_days = intval($_POST['max_days_per_year']);

    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO leave_types (leave_name, description, max_days_per_year) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $description, $max_days);
        if ($stmt->execute()) {
            $message = "Leave type added successfully!";
            $messageType = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $messageType = "error";
        }
    } else {
        $message = "Leave name is required.";
        $messageType = "error";
    }
}

// UPDATE Leave Type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_leave'])) {
    $id = intval($_POST['leave_type_id']);
    $name = trim($_POST['leave_name']);
    $description = trim($_POST['description']);
    $max_days = intval($_POST['max_days_per_year']);

    $stmt = $conn->prepare("UPDATE leave_types SET leave_name=?, description=?, max_days_per_year=? WHERE leave_type_id=?");
    $stmt->bind_param("ssii", $name, $description, $max_days, $id);
    if ($stmt->execute()) {
        $message = "Leave type updated successfully!";
        $messageType = "success";
    } else {
        $message = "Error: " . $stmt->error;
        $messageType = "error";
    }
}

// DELETE Leave Type
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM leave_types WHERE leave_type_id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Leave type deleted successfully!";
        $messageType = "success";
    } else {
        $message = "Error: " . $stmt->error;
        $messageType = "error";
    }
}

// Fetch leave types
$result = $conn->query("SELECT * FROM leave_types ORDER BY leave_type_id ASC");
$leaveTypes = $result->fetch_all(MYSQLI_ASSOC);
?>





<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Leave Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="icon" type="image/png" href="../picture/logo2.png" />
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      lucide.createIcons();
    });

    // Open Modals
    function openDeleteModal(id) {
      document.getElementById("deleteModal").classList.remove("hidden");
      document.getElementById("confirmDeleteBtn").href = "?delete_id=" + id;
    }
    function closeDeleteModal() {
      document.getElementById("deleteModal").classList.add("hidden");
    }

    function openEditModal(id, name, description, maxDays) {
      document.getElementById("editModal").classList.remove("hidden");
      document.getElementById("edit_leave_type_id").value = id;
      document.getElementById("edit_leave_name").value = name;
      document.getElementById("edit_description").value = description;
      document.getElementById("edit_max_days").value = maxDays;
    }
    function closeEditModal() {
      document.getElementById("editModal").classList.add("hidden");
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

        <!-- Display Message -->
        <?php if ($message): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-white <?= $messageType === 'success' ? 'bg-green-500' : 'bg-red-500' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <!-- Add Leave Type Form -->
        <h3 class="text-lg font-semibold mb-4">Add New Leave Type</h3>
        <form method="POST" class="space-y-4 mb-8">
          <div>
            <label class="block">Leave Name</label>
            <input type="text" name="leave_name" class="w-full border p-2 rounded" required>
          </div>
          <div>
            <label class="block">Description</label>
            <textarea name="description" class="w-full border p-2 rounded"></textarea>
          </div>
          <div>
            <label class="block">Max Days Per Year</label>
            <input type="number" name="max_days_per_year" class="w-full border p-2 rounded" min="0">
          </div>
          <button type="submit" name="add_leave" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Add Leave Type</button>
        </form>

        <!-- Table -->
        <h3 class="text-lg font-semibold mb-4">Leave Types List</h3>
        <div class="overflow-x-auto">
          <table class="min-w-full border border-gray-200 rounded-lg">
            <thead class="bg-gray-100">
              <tr>
                <th class="py-2 px-4 border">ID</th>
                <th class="py-2 px-4 border">Leave Name</th>
                <th class="py-2 px-4 border">Description</th>
                <th class="py-2 px-4 border">Max Days</th>
                <th class="py-2 px-4 border">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($leaveTypes as $row): ?>
              <tr class="hover:bg-gray-50">
                <td class="py-2 px-4 border"><?= $row['leave_type_id'] ?></td>
                <td class="py-2 px-4 border"><?= htmlspecialchars($row['leave_name']) ?></td>
                <td class="py-2 px-4 border"><?= htmlspecialchars($row['description']) ?></td>
                <td class="py-2 px-4 border"><?= $row['max_days_per_year'] ?></td>
                <td class="py-2 px-4 border text-center space-x-2">
                  <button onclick="openEditModal(<?= $row['leave_type_id'] ?>,'<?= htmlspecialchars($row['leave_name'], ENT_QUOTES) ?>','<?= htmlspecialchars($row['description'], ENT_QUOTES) ?>',<?= $row['max_days_per_year'] ?>)" class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto">Edit</button>
                  <button onclick="openDeleteModal(<?= $row['leave_type_id'] ?>)" class="bg-red-500 px-2 py-1 rounded text-white">Delete</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div>
    </main>
  </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50 transition-opacity duration-300">
  <div id="deleteModalContent" class="bg-white rounded-lg shadow-lg w-96 p-6 animate-fadeIn">
    <h2 class="text-lg font-bold mb-4">Are you Sure?</h2>
    <p class="mb-6 text-gray-600">The selected leave type will be permanently deleted. Continue?</p>
    <div class="flex justify-end space-x-3">
      <button onclick="closeDeleteModal()" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 text-gray-700">Cancel</button>
      <a id="confirmDeleteBtn" href="#" class="px-4 py-2 rounded bg-red-600 hover:bg-red-700 text-white">Delete</a>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50 transition duration-300">
  <div class="bg-white rounded-lg shadow-lg w-96 p-6 animate-fadeIn">
    <h2 class="text-lg font-bold mb-4">Edit Leave Type</h2>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="leave_type_id" id="edit_leave_type_id">
      <div>
        <label class="block">Leave Name</label>
        <input type="text" name="leave_name" id="edit_leave_name" class="w-full border p-2 rounded" required>
      </div>
      <div>
        <label class="block">Description</label>
        <textarea name="description" id="edit_description" class="w-full border p-2 rounded"></textarea>
      </div>
      <div>
        <label class="block">Max Days Per Year</label>
        <input type="number" name="max_days_per_year" id="edit_max_days" class="w-full border p-2 rounded" min="0">
      </div>
      <div class="flex justify-end space-x-2 pt-3">
        <button type="button" onclick="closeEditModal()" class="px-3 py-1 rounded bg-gray-300 hover:bg-gray-400">Cancel</button>
        <button type="submit" name="update_leave" class="px-3 py-1 rounded bg-green-500 text-white hover:bg-green-600">Save</button>
      </div>
    </form>
  </div>
</div>

<style>
  .animate-fadeIn { animation: fadeIn 0.3s ease-in-out; }
  @keyframes fadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
  }
</style>

</body>
</html>
