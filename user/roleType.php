

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<?php
// DB connection
include __DIR__ . '/../dbconnection/mainDb.php';

// Handle Add Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_role'])) {
    $role_id = uniqid(); // generate role_id
    $name = $_POST['name'];
    $description = $_POST['description'];

    $stmt = $conn->prepare("INSERT INTO roles (role_id, name, description, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $role_id, $name, $description);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Edit Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $role_id = $_POST['role_id'];
    $name = $_POST['name'];
    $description = $_POST['description'];

    $stmt = $conn->prepare("UPDATE roles SET name=?, description=? WHERE role_id=?");
    $stmt->bind_param("sss", $name, $description, $role_id);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Delete Role
if (isset($_GET['delete'])) {
    $deleteId = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM roles WHERE role_id=?");
    $stmt->bind_param("s", $deleteId);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch roles
$res = $conn->query("SELECT * FROM roles ORDER BY created_at DESC");
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
include 'userNavbar.php'; ?>


        <!-- Page Body -->
        <p class="text-gray-600"></p>
      </main>


  <div class="bg-white shadow-md rounded-2xl p-10 w-full mx-auto mt-10 mb-10">




  <div class="bg-white shadow-lg rounded-2xl p-6 mb-8">
    <h2 class="text-xl font-bold mb-4">Add Role</h2>
    <form method="POST" class="flex space-x-4">
      <input type="text" name="name" placeholder="Role Name" required
             class="border p-2 rounded w-1/3">
      <input type="text" name="description" placeholder="Description"
             class="border p-2 rounded flex-1">
      <button type="submit" name="add_role"
              class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto">
        Add
      </button>
    </form>
  </div>

  <div class="bg-white shadow-lg rounded-2xl p-6">
    <h2 class="text-xl font-bold mb-4">Roles List</h2>
    <table class="min-w-full border border-gray-300 rounded-lg overflow-hidden">
      <thead class="bg-gray-200">
        <tr>
          <th class="p-2 text-left">Role ID</th>
          <th class="p-2 text-left">Name</th>
          <th class="p-2 text-left">Description</th>
          <th class="p-2 text-left">Created At</th>
          <th class="p-2 text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $res->fetch_assoc()): ?>
          <tr class="border-t">
            <td class="p-2"><?= htmlspecialchars($row['role_id']) ?></td>
            <td class="p-2"><?= htmlspecialchars($row['name']) ?></td>
            <td class="p-2"><?= !empty($row['description']) ? htmlspecialchars($row['description']) : '-' ?></td>
            <td class="p-2"><?= htmlspecialchars($row['created_at']) ?></td>
            <td class="p-2 flex justify-center space-x-2">
              <button onclick='openEditModal(<?= json_encode($row) ?>)'
                      class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto">
                Edit
              </button>
              <button onclick="openDeleteModal('<?= $row['role_id'] ?>')"
                      class="px-3 py-1 rounded bg-red-500 text-white hover:bg-red-600">
                Delete
              </button>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Edit Modal -->
  <div id="editModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50">
    <div class="bg-white rounded-lg shadow-lg w-96 p-6">
      <h2 class="text-lg font-bold mb-4">Edit Role</h2>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="role_id" id="edit_role_id">
        <div>
          <label class="block">Name</label>
          <input type="text" name="name" id="edit_name" class="w-full border p-2 rounded" required>
        </div>
        <div>
          <label class="block">Description</label>
          <textarea name="description" id="edit_description" class="w-full border p-2 rounded"></textarea>
        </div>
        <div class="flex justify-end space-x-2 pt-3">
          <button type="button" onclick="closeEditModal()" class="px-3 py-1 rounded bg-gray-300 hover:bg-gray-400">Cancel</button>
          <button type="submit" name="update_role" class="px-3 py-1 rounded bg-green-500 text-white hover:bg-green-600">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Modal -->
  <div id="deleteModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50 transition-opacity duration-300">
    <div id="deleteModalContent" class="bg-white rounded-lg shadow-lg w-96 p-6">
      <h2 class="text-lg font-bold mb-4">Are you Sure?</h2>
      <p class="mb-6 text-gray-600">
        The selected role will be permanently deleted. Continue?
      </p>
      <div class="flex justify-end space-x-3">
        <button onclick="closeDeleteModal()" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 text-gray-700">Cancel</button>
        <a id="confirmDeleteBtn" href="#" class="px-4 py-2 rounded bg-red-600 hover:bg-red-700 text-white">Delete</a>
      </div>
    </div>
  </div>

<script>
function openEditModal(data) {
  document.getElementById("edit_role_id").value = data.role_id;
  document.getElementById("edit_name").value = data.name ?? "";
  document.getElementById("edit_description").value = data.description ?? "";
  document.getElementById("editModal").classList.remove("hidden");
}

function closeEditModal() {
  document.getElementById("editModal").classList.add("hidden");
}

function openDeleteModal(roleId) {
  document.getElementById("confirmDeleteBtn").href = "?delete=" + roleId;
  document.getElementById("deleteModal").classList.remove("hidden");
}

function closeDeleteModal() {
  document.getElementById("deleteModal").classList.add("hidden");
}
</script>








</body>
</html>
