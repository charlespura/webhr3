<?php
session_start();
include __DIR__ . '/../dbconnection/mainDB.php';
include __DIR__ . '/public_html/config/firebase.php';
$FIREBASE_API_KEY = "AIzaSyCQg9yf_oWKyDAE_WApgRnG3q-BEDL6bSc";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$currentPassword || !$newPassword || !$confirmPassword) {
        $message = " All fields are required";
        $messageType = "error";
    } elseif ($newPassword !== $confirmPassword) {
        $message = " New password and confirmation do not match";
        $messageType = "error";
    } elseif (strlen($newPassword) < 8) {
        $message = " Password must be at least 8 characters long";
        $messageType = "error";
    } else {
        $user_id = $_SESSION['user_id'];

        // Fetch current password & email
        $stmt = $conn->prepare("SELECT password_hash, email FROM users WHERE user_id = ?");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            $message = " Current password is incorrect";
            $messageType = "error";
        } else {
            $email = $row['email'];

            // Step 1: Login to Firebase
            $loginPayload = json_encode([
                "email" => $email,
                "password" => $currentPassword,
                "returnSecureToken" => true
            ]);
            $ch = curl_init("https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=$FIREBASE_API_KEY");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $loginPayload);
            $firebaseLogin = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (!isset($firebaseLogin['idToken'])) {
                $message = " Firebase login failed";
                $messageType = "error";
            } else {
                $idToken = $firebaseLogin['idToken'];

                // Step 2: Update Firebase password
                $updatePayload = json_encode([
                    "idToken" => $idToken,
                    "password" => $newPassword,
                    "returnSecureToken" => true
                ]);
                $ch = curl_init("https://identitytoolkit.googleapis.com/v1/accounts:update?key=$FIREBASE_API_KEY");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $updatePayload);
                $updateResp = json_decode(curl_exec($ch), true);
                curl_close($ch);

                if (isset($updateResp['error'])) {
                    $message = " Firebase password update failed: " . $updateResp['error']['message'];
                    $messageType = "error";
                } else {
                 if (!isset($updateResp['error'])) {
    // Update MySQL password
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $stmt->bind_param("ss", $newHash, $user_id);
    $stmt->execute();

   
}
                    $message = "Password changed successfully. You will be logged out in 3 seconds.";
                    $messageType = "success";

                    // Use JavaScript to destroy session after 3 seconds
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = '/public_html/logout.php?logout=1';
                        }, 5000);
                    </script>";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Change Password</title>
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
  <div class="flex h-full">
    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-y-auto">
      <main class="p-6 space-y-4">
        <div class="flex items-center justify-between border-b py-6">
          <h2 class="text-xl font-semibold text-gray-800">Change Password</h2>
          <?php include '../profile.php'; ?>
        </div>

        <?php include 'userNavbar.php'; ?>

        <div class="bg-white shadow-md rounded-2xl p-10 w-full mx-auto mt-10 mb-10">
          <?php if ($message): ?>
            <div class="p-4 mb-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
              <?= htmlspecialchars($message) ?>
            </div>
          <?php endif; ?>

       <form method="POST" action="">
  <!-- Current Password -->
  <label class="block mb-2">Current Password:</label>
  <input type="password" id="current_password" name="current_password" placeholder="Current Password" required class="border rounded p-2 w-full mb-4">

  <!-- New Password -->
  <label class="block mb-2">New Password:</label>
  <input type="password" id="new_password" name="new_password" placeholder="New Password" required class="border rounded p-2 w-full mb-4">

  <!-- Confirm New Password -->
  <label class="block mb-2">Confirm New Password:</label>
  <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required class="border rounded p-2 w-full mb-6">

  <!-- Toggle All Passwords -->
  <div class="flex items-center mb-6 cursor-pointer" onclick="toggleAllPasswords()">
    <i id="toggleEye" data-lucide="eye" class="mr-2"></i>
    <span>Show/Hide Passwords</span>
  </div>

  <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto">
    Change Password
  </button>
</form>

<script>
  function toggleAllPasswords() {
    const fields = ['current_password', 'new_password', 'confirm_password'];
    const eyeIcon = document.getElementById('toggleEye');
    let anyHidden = fields.some(id => document.getElementById(id).type === 'password');

    fields.forEach(id => {
      document.getElementById(id).type = anyHidden ? 'text' : 'password';
    });

    // Toggle icon
    eyeIcon.setAttribute('data-lucide', anyHidden ? 'eye-off' : 'eye');
    lucide.createIcons(); // refresh icons
  }
</script>

        </div>
      </main>
    </div>
  </div>
</body>
</html>
