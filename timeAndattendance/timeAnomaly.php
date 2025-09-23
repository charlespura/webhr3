

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../dbconnection/mainDB.php';
$result = $conn->query("SELECT * FROM attendance_anomalies ORDER BY created_at DESC");

$anomalies = [];
while ($row = $result->fetch_assoc()) {
    $row['anomalies'] = json_decode($row['anomalies'], true);
    $anomalies[] = $row;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance Anomalies</title>
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
          <h2 class="text-xl font-semibold text-gray-800" id="main-content-title">Attendance Anomalies </h2>

  <?php include '../profile.php'; ?>


        </div>

      <?php include 'timenavbar.php'; ?>
  
<div class="bg-white shadow-md rounded-2xl p-10 w-full mx-auto mt-10 mb-10">
  <!-- Add your content here -->
   <?php if (!empty($_SESSION['flash_message'])): ?>
  <div class="mb-4 p-3 rounded-lg 
    <?php echo strpos($_SESSION['flash_message'], '✅') !== false 
        ? 'bg-green-100 text-green-800' 
        : 'bg-red-100 text-red-800'; ?>">
    <?php 
      echo $_SESSION['flash_message']; 
      unset($_SESSION['flash_message']); 
    ?>
  </div>
<?php endif; ?>

    <h3 class="text-lg font-semibold mb-6">Detected Anomalies</h3>

  <form action="anomaly_checker.php" method="post" class="mb-6">
    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">Run Anomaly Check</button>
  </form>
 <div class="space-y-4">
    <?php foreach ($anomalies as $row): ?>
      <div class="bg-white shadow rounded p-4">
        <p><strong>Employee:</strong> <?= htmlspecialchars($row['employee_id']) ?></p>
        <p><strong>Date:</strong> <?= htmlspecialchars($row['work_date']) ?></p>
        <p><strong>Anomalies:</strong> <?= implode(", ", $row['anomalies']) ?></p>
        <p><strong>Explanation:</strong> <?= htmlspecialchars($row['explanation']) ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  
</div>




        </main>
        </div>
    </div>

</body>
</html>

