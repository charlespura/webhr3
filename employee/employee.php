

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
  <script>
    function openFullscreen() {
      let elem = document.documentElement;
      if (elem.requestFullscreen) {
        elem.requestFullscreen();
      } else if (elem.mozRequestFullScreen) { // Firefox
        elem.mozRequestFullScreen();
      } else if (elem.webkitRequestFullscreen) { // Chrome, Safari, Opera
        elem.webkitRequestFullscreen();
      } else if (elem.msRequestFullscreen) { // IE/Edge
        elem.msRequestFullscreen();
      }
    }

    // Try to launch fullscreen as soon as page loads
    window.onload = openFullscreen;
  </script>
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


  <div class="bg-white shadow-md rounded-2xl p-10 w-full mx-auto ">

  
<?php
// Include the database connection file
include __DIR__ .'/../dbconnection/dbEmployee.php';

// Pagination setup
$limit = 20; // number of employees per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Build search conditions
$where = [];
$params = [];
$types  = '';

if (!empty($_GET['search_name'])) {
    $where[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.preferred_name LIKE ?)";
    $search = "%" . $_GET['search_name'] . "%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= 'sss';
}

if (!empty($_GET['department_id'])) {
    $where[] = "e.department_id = ?";
    $params[] = $_GET['department_id'];
    $types .= 's';  // use 's' because department_id is char(36)
}

if (!empty($_GET['position_id'])) {
    $where[] = "e.position_id = ?";
    $params[] = $_GET['position_id'];
    $types .= 's';  // use 's' because position_id is char(36)
}

// Main SQL query with LIMIT
$sql = "
    SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, 
           d.name AS department, p.title AS position
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN positions p ON e.position_id = p.position_id
";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY e.first_name, e.last_name LIMIT ? OFFSET ?";

// Add LIMIT and OFFSET to params
$params_with_limit = $params;
$types_with_limit = $types . "ii"; // two integers
$params_with_limit[] = $limit;
$params_with_limit[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types_with_limit, ...$params_with_limit);
$stmt->execute();
$result = $stmt->get_result();

// Count total rows for pagination
$count_sql = "
    SELECT COUNT(*) AS total
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN positions p ON e.position_id = p.position_id
";
if ($where) {
    $count_sql .= " WHERE " . implode(" AND ", $where);
}
$count_stmt = $conn->prepare($count_sql);
if ($where) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
?>
<?php
// Build query string for filters (except page)
$query_params = $_GET;
unset($query_params['page']); 
$base_url = '?' . http_build_query($query_params);
if ($base_url == '?') $base_url = ''; // avoid lone ?
?>



<!-- Search Form -->
<div class="bg-white shadow-md rounded-2xl p-6 w-full mx-auto mt-6 mb-6">
    <h2 class="text-xl font-bold mb-4">Search Employees</h2>
    <form method="get" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <label class="block mb-1 text-sm font-medium">Employee Name</label>
            <input type="text" name="search_name" 
                   value="<?php echo htmlspecialchars($_GET['search_name'] ?? '', ENT_QUOTES); ?>" 
                   class="border rounded w-full p-2" placeholder="Enter name...">
        </div>
        <div>
            <label class="block mb-1 text-sm font-medium">Department</label>
            <select name="department_id" class="border rounded w-full p-2">
                <option value="">-- All Departments --</option>
                <?php
                $departments = $conn->query("SELECT department_id, name FROM departments ORDER BY name");
                while ($d = $departments->fetch_assoc()): ?>
                    <option value="<?php echo $d['department_id']; ?>" 
                        <?php if(($_GET['department_id'] ?? '') == $d['department_id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($d['name'] ?? ''); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block mb-1 text-sm font-medium">Position</label>
            <select name="position_id" class="border rounded w-full p-2">
                <option value="">-- All Positions --</option>
                <?php
                $positions = $conn->query("SELECT position_id, title FROM positions ORDER BY title");
                while ($p = $positions->fetch_assoc()): ?>
                    <option value="<?php echo $p['position_id']; ?>" 
                        <?php if(($_GET['position_id'] ?? '') == $p['position_id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($p['title'] ?? ''); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="flex items-end">
      <button type="submit" 
    class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto">
    Search
</button>


        </div>
    </form>
</div>


<h2 class="text-xl font-bold mb-4 flex justify-between items-center">
  <span>
    Employee List 
    <span id="recordCount" class="text-gray-600 font-normal ml-2"></span>
  </span>
  <button id="deleteSelectedBtn" 
          onclick="openDeleteModalSelected()" 
          class="hidden flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white py-1 px-4 rounded">
    <!-- Trash Icon -->
    <svg xmlns="http://www.w3.org/2000/svg" 
         class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" 
            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
    </svg>
    Delete Selected
  </button>
</h2>



<div class="overflow-x-auto">
    <table class="table-auto w-full border border-gray-300 text-sm sm:text-base">
        <thead>
            <tr class="bg-gray-200">
                <th class="border px-4 py-2 text-center">
                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                </th>
                <th class="border px-4 py-2">Code</th>
                <th class="border px-4 py-2">Name</th>
                <th class="border px-4 py-2">Department</th>
                <th class="border px-4 py-2">Position</th>
                <th class="border px-4 py-2">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-100">
                        <td class="border px-4 py-2 text-center">
                            <input type="checkbox" class="rowCheckbox" value="<?php echo $row['employee_id']; ?>" onclick="toggleDeleteButton()">
                        </td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($row['employee_code'] ?? ''); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($row['department'] ?? ''); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($row['position'] ?? ''); ?></td>
                        <td class="border px-4 py-2 text-center">
                            <a href="viewPersonalDetails.php?id=<?php echo urlencode($row['employee_id']); ?>" 
                               class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 py-1 px-3 rounded block sm:inline-block mb-1 sm:mb-0">
                               Edit
                            </a>
                            <a href="javascript:void(0);" 
                               onclick="openDeleteModal('<?php echo $row['employee_id']; ?>')" 
                               class="bg-red-500 hover:bg-red-700 text-white py-1 px-3 rounded block sm:inline-block">
                               Delete
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="border px-4 py-2 text-center">No employees found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

    
  <div class="mt-4 flex justify-center space-x-2">
    <?php if ($page > 1): ?>
        <a href="<?php echo $base_url . ($base_url ? '&' : '?'); ?>page=<?php echo $page - 1; ?>" 
           class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">&lt;</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="<?php echo $base_url . ($base_url ? '&' : '?'); ?>page=<?php echo $i; ?>" 
           class="px-3 py-1 border rounded 
           <?php echo $i == $page ? 'bg-gray-800 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>">
           <?php echo $i; ?>
        </a>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
        <a href="<?php echo $base_url . ($base_url ? '&' : '?'); ?>page=<?php echo $page + 1; ?>" 
           class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">&gt;</a>
    <?php endif; ?>
</div>


</div>


<?php
$stmt->close();
$conn->close();
?>
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
      <a id="confirmDeleteBtn" href="#" 
         class="px-4 py-2 rounded bg-red-600 hover:bg-red-700 text-white">
        Yes, Delete
      </a>
    </div>
  </div>
</div>
<script>
  let deleteEmployeeId = null;
  let multipleDelete = false;

  // Show total record count on page load
  document.addEventListener("DOMContentLoaded", function() {
    updateRecordCount();
  });

  // Toggle all checkboxes
  function toggleSelectAll(source) {
    const checkboxes = document.querySelectorAll(".rowCheckbox");
    checkboxes.forEach(cb => cb.checked = source.checked);
    updateSelectionUI();
  }

  // Update delete button + count
  function toggleDeleteButton() {
    updateSelectionUI();
  }

  // Update record count & UI
  function updateSelectionUI() {
    const selected = document.querySelectorAll(".rowCheckbox:checked").length;
    const countSpan = document.getElementById("recordCount");
    const deleteBtn = document.getElementById("deleteSelectedBtn");
    const selectAll = document.getElementById("selectAll");
    const total = document.querySelectorAll(".rowCheckbox").length;

    if (selected > 0) {
      countSpan.textContent = `(${selected} Selected)`;
      deleteBtn.classList.remove("hidden");
    } else {
      countSpan.textContent = `(${total} Records)`;
      deleteBtn.classList.add("hidden");
    }

    // Keep select all checkbox in sync
    selectAll.checked = (selected === total && total > 0);
  }

  // Default record count
  function updateRecordCount() {
    const total = document.querySelectorAll(".rowCheckbox").length;
    document.getElementById("recordCount").textContent = `(${total} Records)`;
  }

  // Single delete modal
  function openDeleteModal(employeeId) {
    multipleDelete = false;
    deleteEmployeeId = employeeId;
    document.getElementById("deleteMessage").innerText = 
      "The selected record will be permanently deleted. Are you sure you want to continue?";
    showModal();
  }

  // Multiple delete modal
  function openDeleteModalSelected() {
    multipleDelete = true;
    document.getElementById("deleteMessage").innerText = 
      "The selected records will be permanently deleted. Are you sure you want to continue?";
    showModal();
  }

  // Show modal with animation
  function showModal() {
    const modal = document.getElementById("deleteModal");
    const modalContent = document.getElementById("deleteModalContent");
    modal.classList.remove("hidden");
    setTimeout(() => {
      modalContent.classList.remove("scale-95", "opacity-0");
      modalContent.classList.add("scale-100", "opacity-100");
    }, 50);
  }

  // Close modal
  function closeDeleteModal() {
    const modal = document.getElementById("deleteModal");
    const modalContent = document.getElementById("deleteModalContent");
    modalContent.classList.remove("scale-100", "opacity-100");
    modalContent.classList.add("scale-95", "opacity-0");
    setTimeout(() => {
      modal.classList.add("hidden");
    }, 200);
  }

  // Confirm delete
  document.getElementById("confirmDeleteBtn").addEventListener("click", function() {
    if (multipleDelete) {
      const ids = Array.from(document.querySelectorAll(".rowCheckbox:checked"))
                       .map(cb => cb.value);
      if (ids.length > 0) {
        window.location.href = "deleteEmployee.php?ids=" + ids.join(",") + "&confirm=yes";
      }
    } else if (deleteEmployeeId) {
      window.location.href = "deleteEmployee.php?id=" + deleteEmployeeId + "&confirm=yes";
    }
  });
</script>








</body>
</html>
