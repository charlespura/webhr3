
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
      <main class="p-6">
        <!-- Header -->
        <div class="flex items-center justify-between border-b py-6">
          <!-- Left: Title -->
          <h2 class="text-xl font-semibold text-gray-800" id="main-content-title">Shift and Schedule</h2>


          <!-- ito yung profile ng may login wag kalimutan lagyan ng session yung profile.php para madetect nya if may login or wala -->
<?php include '../profile.php'; ?>

        </div>

<?php
include 'shiftnavbar.php';
include __DIR__ . '/../dbconnection/mainDB.php';
$shiftConn = $conn;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $status_id = $_POST['status_id'] ?? '';
    $status_name = $_POST['status_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($action === 'add') {
        $stmt = $shiftConn->prepare("INSERT INTO request_statuses (status_id, status_name, description, is_active, created_at, updated_at) VALUES (UUID(), ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("ssi", $status_name, $description, $is_active);
        $stmt->execute();
    } elseif ($action === 'edit' && $status_id) {
        $stmt = $shiftConn->prepare("UPDATE request_statuses SET status_name=?, description=?, is_active=?, updated_at=NOW() WHERE status_id=?");
        $stmt->bind_param("ssis", $status_name, $description, $is_active, $status_id);
        $stmt->execute();
    } elseif ($action === 'delete' && $status_id) {
        $stmt = $shiftConn->prepare("DELETE FROM request_statuses WHERE status_id=?");
        $stmt->bind_param("s", $status_id);
        $stmt->execute();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch statuses
$result = $shiftConn->query("SELECT * FROM request_statuses ORDER BY created_at DESC");
?>

<div class="container mx-auto p-6">
    <h2 class="text-2xl font-bold mb-4">Manage Request Statuses</h2>

    <!-- Add / Edit Form -->
    <form method="post" class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <input type="hidden" name="action" value="add" id="form_action">
        <input type="hidden" name="status_id" id="status_id">
        
        <div>
            <label class="block mb-1 font-medium">Status Name</label>
            <input type="text" name="status_name" id="status_name" required class="border rounded w-full p-2">
        </div>
        <div>
            <label class="block mb-1 font-medium">Description</label>
            <input type="text" name="description" id="description" class="border rounded w-full p-2">
        </div>
        <div class="flex items-center">
            <input type="checkbox" name="is_active" id="is_active" checked class="mr-2">
            <label for="is_active">Active</label>
        </div>
        <div>
            <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto" id="form_submit">Add</button>
        </div>
    </form>

    <!-- Status Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200 rounded-lg">
            <thead>
                <tr class="bg-gray-100 text-left">
                    <th class="px-4 py-2 border">Status Name</th>
                    <th class="px-4 py-2 border">Description</th>
                    <th class="px-4 py-2 border">Active</th>
                    <th class="px-4 py-2 border">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 border"><?= htmlspecialchars($row['status_name']) ?></td>
                    <td class="px-4 py-2 border"><?= htmlspecialchars($row['description']) ?></td>
                    <td class="px-4 py-2 border"><?= $row['is_active'] ? 'Yes' : 'No' ?></td>
                    <td class="px-4 py-2 border space-x-2">
                        <button type="button" class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto editBtn"
                            data-id="<?= $row['status_id'] ?>"
                            data-name="<?= htmlspecialchars($row['status_name']) ?>"
                            data-desc="<?= htmlspecialchars($row['description']) ?>"
                            data-active="<?= $row['is_active'] ?>"
                        >Edit</button>
                        <button type="button" class="bg-red-500 px-2 py-1 rounded text-white deleteBtn"
                                data-id="<?= $row['status_id'] ?>"
                                data-name="<?= htmlspecialchars($row['status_name']) ?>">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
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
      The selected record will be permanently deleted. Are you sure you want to continue?
    </p>
    
    <div class="flex justify-end space-x-3">
      <button onclick="closeDeleteModal()" 
              class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 text-gray-700">
        No, Cancel
      </button>
      <form method="post" id="deleteForm">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="status_id" id="delete_status_id">
        <button type="submit" 
                class="px-4 py-2 rounded bg-red-600 hover:bg-red-700 text-white">
          Yes, Delete
        </button>
      </form>
    </div>
  </div>
</div>

<script>
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteStatusId = document.getElementById('delete_status_id');
const deleteMessage = document.getElementById('deleteMessage');

document.querySelectorAll('.deleteBtn').forEach(btn => {
    btn.addEventListener('click', () => {
        const statusId = btn.getAttribute('data-id');
        const statusName = btn.getAttribute('data-name');
        deleteStatusId.value = statusId;
        deleteMessage.textContent = `The status "${statusName}" will be permanently deleted. Are you sure?`;
        deleteModal.classList.remove('hidden');
        setTimeout(() => {
            document.getElementById('deleteModalContent').classList.remove('scale-95', 'opacity-0');
        }, 10);
    });
});

function closeDeleteModal() {
    const content = document.getElementById('deleteModalContent');
    content.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        deleteModal.classList.add('hidden');
    }, 200);
}

// Edit button functionality
document.querySelectorAll('.editBtn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('form_action').value = 'edit';
        document.getElementById('status_id').value = btn.dataset.id;
        document.getElementById('status_name').value = btn.dataset.name;
        document.getElementById('description').value = btn.dataset.desc;
        document.getElementById('is_active').checked = btn.dataset.active == 1;
        document.getElementById('form_submit').textContent = 'Update';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
</script>





</body>
</html>
