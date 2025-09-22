

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<?php
// Enable MySQLi exceptions instead of fatal errors
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Include DB connections
include __DIR__ . '/../dbconnection/dbEmployee.php'; // hr3_system
$empConn = $conn;

include __DIR__ . '/../dbconnection/mainDB.php'; // hr3_maindb
$mainConn = $conn;

// Face++ credentials
$apiKey = "mK15o6e5bp8DgIJOdTKHDOfq9pP4a8C1";
$apiSecret = "EuNwJz7niOZTLTZ-eA1FuSFIm7Q1NZ8q";

$message = '';
$messageType = ''; // success / error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employee_id = $_POST['employee_id'];
    $username    = $_POST['username'];
    $password    = $_POST['password'];
    $role_id     = $_POST['role_id'];

    $user_id = uniqid();
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // 🔹 Fetch email from employees.personal_email
    $stmt = $empConn->prepare("SELECT personal_email FROM employees WHERE employee_id = ?");
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $stmt->bind_result($employee_email);
    $stmt->fetch();
    $stmt->close();

    $email = $employee_email ?: ''; // fallback empty string if null

    // Handle image upload
    $reference_image = null;
    $faceToken = null;
    if (isset($_FILES['reference_image']) && $_FILES['reference_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/reference_image/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $ext = pathinfo($_FILES['reference_image']['name'], PATHINFO_EXTENSION);
        $filename = $user_id . '.' . $ext;
        $filePath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['reference_image']['tmp_name'], $filePath)) {
            $reference_image = 'uploads/reference_image/' . $filename;

            // 🔹 Call Face++ Detect API to get face_token
            $ch = curl_init();
            $data = [
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'image_file' => new CURLFile($filePath)
            ];
            curl_setopt($ch, CURLOPT_URL, "https://api-us.faceplusplus.com/facepp/v3/detect");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $result = curl_exec($ch);
            curl_close($ch);

            $response = json_decode($result, true);
            $faceToken = $response['faces'][0]['face_token'] ?? null;
        }
    }

    try {
        // Insert into MySQL users
        $firebase_uid = $_POST['firebase_uid'] ?? null;

        $stmt = $mainConn->prepare("
            INSERT INTO users (user_id, username, email, password_hash, reference_image, face_token, firebase_uid, is_active, is_verified)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0)
        ");
        $stmt->bind_param("sssssss", $user_id, $username, $email, $password_hash, $reference_image, $faceToken, $firebase_uid);
        $stmt->execute();

        // Insert into user_profiles
        $stmt = $mainConn->prepare("
            INSERT INTO user_profiles (user_id, first_name, last_name)
            SELECT ?, first_name, last_name FROM hr3_system.employees WHERE employee_id = ?
        ");
        $stmt->bind_param("ss", $user_id, $employee_id);
        $stmt->execute();

        // Assign role
        $assigned_by = null;
        $stmt = $mainConn->prepare("
            INSERT INTO user_roles (user_id, role_id, assigned_by)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("sss", $user_id, $role_id, $assigned_by);
        $stmt->execute();

        // Update employee record
        $stmt = $empConn->prepare("
            UPDATE employees SET user_id = ? WHERE employee_id = ?
        ");
        $stmt->bind_param("ss", $user_id, $employee_id);
        $stmt->execute();

        $message = "User account created successfully. Firebase Auth pending verification.";
        if ($faceToken === null) {
            $message .= " ⚠️ Face++ did not detect a face.";
        }
        $messageType = 'success';

    } catch (mysqli_sql_exception $e) {
        $message = " Error: " . $e->getMessage();
        $messageType = 'error';
    }
}
?>






<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Time and Attendance</title>
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
          <h2 class="text-xl font-semibold text-gray-800" id="main-content-title">User Management</h2>


          <!-- ito yung profile ng may login wag kalimutan lagyan ng session yung profile.php para madetect nya if may login or wala -->
<?php include '../profile.php'; ?>

        </div>
<!-- Second Header: Submodules -->


<?php 
include 'userNavbar.php'; ?>





                                  <!-- ADMIN AREA  -->

                


<?php
// Assume $roles contains the role of the currently logged-in user
// e.g., $roles = $_SESSION['user_role'];

if (in_array($roles, ['Admin', 'Manager'])): 
?>
<div class="bg-white shadow-md rounded-2xl p-10 w-full mx-auto mt-10 mb-10">

<!-- Display Message -->
<?php if ($message): ?>
<div class="mb-4 px-4 py-3 rounded-lg text-white <?= $messageType === 'success' ? 'bg-green-500' : 'bg-red-500' ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<h2 class="text-2xl font-bold mb-6">Create Employee Account</h2>

<!-- Full Page Center Wrapper -->
<div class="min-h-screen flex items-center justify-center bg-gray-100">
 
<form action="" method="POST" enctype="multipart/form-data" class="space-y-6 w-full">

    <!-- ✅ Employee + Username + Email in ONE ROW -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Employee -->
        <div>
          <label class="block text-gray-700 font-semibold mb-1">Employee</label>
          <input type="text" id="employeeInput"
            class="w-full border p-2 rounded focus:ring-2 focus:ring-blue-400"
            placeholder="Type employee name..." autocomplete="off" required>
          <input type="hidden" id="employee_id" name="employee_id">
          <div id="suggestions"
               class="border rounded mt-1 max-h-40 overflow-auto hidden bg-white shadow"></div>
        </div>

        <!-- Username -->
        <div>
          <label for="username" class="block text-gray-700 font-semibold mb-1">Username</label>
          <input type="text" id="username" name="username" required
            class="w-full border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-blue-400"
            placeholder="Enter username">
        </div>

        <!-- Email -->
        <div>
          <label for="email" class="block text-gray-700 font-semibold mb-1">Email</label>
          <input type="email" id="email" name="email" required readonly
            class="w-full border-gray-300 rounded-lg p-2 bg-gray-100"
            placeholder="Employee email will auto-fill">
        </div>
    </div>


    <!-- Password Section -->
    <div class="relative">
      <label for="password" class="block text-gray-700 font-semibold mb-1">Password</label>
      <div class="flex items-center relative">
        <input type="password" id="password" name="password" required
          class="w-full border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-blue-400"
          placeholder="Enter password">
        <button type="button" id="togglePassword"
          class="absolute right-3 text-gray-600 hover:text-gray-800">
          <i data-lucide="eye" class="w-5 h-5"></i>
        </button>
      </div>

      <!-- Generate button -->
      <button type="button" id="generatePassword"
        class="mt-2 bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-4 py-2 rounded w-full sm:w-auto">
        Generate Strong Password
      </button>

      <!-- Strength Meter -->
      <div id="strengthWrapper" class="mt-3">
        <div class="w-full bg-gray-200 rounded-full h-2">
          <div id="strengthBar" class="h-2 rounded-full bg-red-500 w-0"></div>
        </div>
        <p id="strengthText" class="mt-1 text-sm font-medium text-gray-700"></p>
      </div>

      <!-- Tips -->
      <div class="mt-3 text-sm text-gray-600 bg-gray-100 p-3 rounded-lg">
        <p class="font-semibold text-gray-800 mb-1">Tips for a good password:</p>
        <ul class="list-disc pl-5 space-y-1">
          <li>Use both <span class="font-medium">uppercase</span> and <span class="font-medium">lowercase</span> letters</li>
          <li>Include at least one <span class="font-medium">symbol</span> (# $ ! % &amp; etc...)</li>
          <li>Don’t use <span class="font-medium">dictionary words</span></li>
          <li>Make it at least <span class="font-medium">12+ characters</span> long</li>
        </ul>
      </div>
    </div>


    <!-- Role Selection -->
    <div>
      <label for="role_id" class="block text-gray-700 font-semibold mb-1">Assign Role</label>
      <select id="role_id" name="role_id" required
        class="w-full border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-blue-400">
        <?php
        $rolesRes = $mainConn->query("SELECT role_id, name FROM roles");
        while($role = $rolesRes->fetch_assoc()) {
            echo "<option value='{$role['role_id']}'>{$role['name']}</option>";
        }
        ?>
      </select>
    </div>


    <!-- Profile Image -->
    <div class="mb-4 relative">
      <label class="block text-gray-700 font-semibold mb-2 flex items-center">
        Profile Image
        <button type="button" id="profileInfoBtn" 
                class="ml-5 text-gray-400 hover:text-gray-600 focus:outline-none"
                aria-label="Info">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
               viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M12 20.5C6.201 20.5 1.5 15.799 1.5 10S6.201-0.5 12-0.5 22.5 4.201 22.5 10 17.799 20.5 12 20.5z" />
          </svg>
        </button>
      </label>

      <!-- Tooltip -->
      <div id="profileInfoNote"
           class="hidden absolute top-6 left-6 bg-gray-100 border border-gray-300 text-gray-700 p-2 rounded shadow-md text-sm w-64 z-10">
        This profile image will also be used for clock-in and clock-out purposes.
      </div>

      <!-- Image Preview -->
      <div class="mb-2">
        <img id="profile_preview" src="/public_html/picture/placeholder.jpg"
             class="w-32 h-32 rounded-full object-cover border"
             alt="Profile Preview">
      </div>

      <!-- File Input -->
      <input type="file" id="reference_image" name="reference_image" accept="image/*"
             class="w-full border-gray-300 rounded-lg p-2 focus:ring-2 focus:ring-blue-400 mb-2">
    </div>


    <!-- Camera Button -->
    <button type="button" id="openCamera" 
            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition mb-2">
      Capture from Camera
    </button>

    <!-- Camera Modal -->
    <div id="cameraModal"
         class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50">
      <div class="bg-white rounded-2xl p-6 w-96 relative">
        <h3 class="text-lg font-bold mb-2">Capture & Crop Image</h3>
        <video id="cameraVideo" autoplay class="w-full rounded-lg mb-2"></video>
        <canvas id="cameraCanvas" class="hidden"></canvas>

        <div class="flex justify-between mt-2">
          <button type="button" id="snapBtn" 
                  class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600">
            Take Photo
          </button>
          <button type="button" id="closeCamera" 
                  class="px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">
            Cancel
          </button>
        </div>

        <!-- Cropper Container -->
        <div class="mt-4 hidden" id="cropContainer">
          <img id="cropImage" class="w-full rounded-lg">
          <div class="flex justify-end mt-2">
            <button type="button" id="cropBtn" 
                    class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
              Crop & Use
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Submit Button -->
    <div class="text-center mt-4">
      <button type="submit" 
              class="bg-gray-800 hover:bg-gray-900 text-white hover:text-yellow-500 px-6 py-2 rounded w-full sm:w-auto">
        Create Account
      </button>
    </div>

</form>
</div>
</div>



<script>
  const infoBtn = document.getElementById('profileInfoBtn');
  const infoNote = document.getElementById('profileInfoNote');

  infoBtn.addEventListener('click', () => {
    infoNote.classList.toggle('hidden');
  });

  // Optional: hide note if clicking outside
  document.addEventListener('click', (e) => {
    if (!infoBtn.contains(e.target) && !infoNote.contains(e.target)) {
      infoNote.classList.add('hidden');
    }
  });
</script>
<script>
const employees = [
  <?php
  $res = $empConn->query("SELECT employee_id, first_name, last_name, employee_code, personal_email 
                          FROM employees WHERE user_id IS NULL");
  $empData = [];
  while($row = $res->fetch_assoc()) {
      $empData[] = [
          'id' => $row['employee_id'],
          'name' => $row['first_name'] . ' ' . $row['last_name'],
          'code' => $row['employee_code'],
          'email' => $row['personal_email'] ?? ''
      ];
  }
  echo json_encode($empData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  ?>
][0]; // fix JSON inline output
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const input = document.getElementById("employeeInput");
  const suggestions = document.getElementById("suggestions");
  const hiddenId = document.getElementById("employee_id");
  const emailField = document.getElementById("email");

  input.addEventListener("input", function() {
    const query = this.value.toLowerCase();
    suggestions.innerHTML = "";
    if (!query) {
      suggestions.classList.add("hidden");
      return;
    }

    const matches = employees.filter(e => e.name.toLowerCase().includes(query));
    if (matches.length === 0) {
      suggestions.classList.add("hidden");
      return;
    }

    matches.forEach(emp => {
      const div = document.createElement("div");
      div.textContent = `${emp.code} - ${emp.name}`;
      div.className = "p-2 hover:bg-blue-100 cursor-pointer";
      div.addEventListener("click", () => {
        input.value = `${emp.code} - ${emp.name}`;
        hiddenId.value = emp.id;
        emailField.value = emp.email;
        suggestions.classList.add("hidden");
      });
      suggestions.appendChild(div);
    });

    suggestions.classList.remove("hidden");
  });

  // Hide suggestions if clicked outside
  document.addEventListener("click", (e) => {
    if (!suggestions.contains(e.target) && e.target !== input) {
      suggestions.classList.add("hidden");
    }
  });
});
</script>

<script>
  // Password input and buttons
  const passwordInput = document.getElementById("password");
  const togglePasswordBtn = document.getElementById("togglePassword");
  const generatePasswordBtn = document.getElementById("generatePassword");
  const eyeIcon = togglePasswordBtn.querySelector("i");

  const strengthBar = document.getElementById("strengthBar");
  const strengthText = document.getElementById("strengthText");

  // Toggle password visibility
  togglePasswordBtn.addEventListener("click", () => {
    const isPassword = passwordInput.type === "password";
    passwordInput.type = isPassword ? "text" : "password";
    eyeIcon.setAttribute("data-lucide", isPassword ? "eye-off" : "eye");
    lucide.createIcons(); // refresh icons
  });

  // Generate strong password
  generatePasswordBtn.addEventListener("click", () => {
    const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+";
    let newPassword = "";
    for (let i = 0; i < 14; i++) {
      newPassword += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    passwordInput.value = newPassword;
    passwordInput.type = "text"; // Show it when generated
    eyeIcon.setAttribute("data-lucide", "eye-off");
    lucide.createIcons();
    checkStrength(newPassword);
  });

  // Check password strength
  passwordInput.addEventListener("input", (e) => {
    checkStrength(e.target.value);
  });

  function checkStrength(password) {
    let strength = 0;

    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    let strengthPercent = (strength / 6) * 100;
    strengthBar.style.width = strengthPercent + "%";

    if (strength <= 2) {
      strengthBar.className = "h-2 rounded-full bg-red-500";
      strengthText.textContent = "Weak";
      strengthText.className = "mt-1 text-sm font-medium text-red-600";
    } else if (strength <= 4) {
      strengthBar.className = "h-2 rounded-full bg-yellow-500";
      strengthText.textContent = "Medium";
      strengthText.className = "mt-1 text-sm font-medium text-yellow-600";
    } else {
      strengthBar.className = "h-2 rounded-full bg-green-600";
      strengthText.textContent = "Strong";
      strengthText.className = "mt-1 text-sm font-medium text-green-600";
    }
  }
</script>
<script>
document.getElementById('employee_id').addEventListener('change', function() {
    let selected = this.options[this.selectedIndex];
    let email = selected.getAttribute('data-email');
    document.getElementById('email').value = email || '';
});
</script>
<!-- Cropper.js -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<script>
const openCamera = document.getElementById('openCamera');
const cameraModal = document.getElementById('cameraModal');
const video = document.getElementById('cameraVideo');
const canvas = document.getElementById('cameraCanvas');
const snapBtn = document.getElementById('snapBtn');
const closeCamera = document.getElementById('closeCamera');
const cropContainer = document.getElementById('cropContainer');
const cropImage = document.getElementById('cropImage');
const cropBtn = document.getElementById('cropBtn');
const profilePreview = document.getElementById('profile_preview');
const fileInput = document.getElementById('reference_image');
let stream;
let cropper;

// Update preview when user selects a file
fileInput.addEventListener('change', () => {
  if (fileInput.files && fileInput.files[0]) {
    const reader = new FileReader();
    reader.onload = e => profilePreview.src = e.target.result;
    reader.readAsDataURL(fileInput.files[0]);
  }
});

// Open camera modal
openCamera.addEventListener('click', async () => {
  cameraModal.classList.remove('hidden');
  cropContainer.classList.add('hidden');
  video.classList.remove('hidden');
  if (stream) stream.getTracks().forEach(track => track.stop());
  stream = await navigator.mediaDevices.getUserMedia({ video: true });
  video.srcObject = stream;
});

// Close camera modal
closeCamera.addEventListener('click', () => {
  cameraModal.classList.add('hidden');
  if (stream) stream.getTracks().forEach(track => track.stop());
});

// Take photo
snapBtn.addEventListener('click', () => {
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  canvas.getContext('2d').drawImage(video, 0, 0);
  cropImage.src = canvas.toDataURL('image/png');

  cropContainer.classList.remove('hidden');
  video.classList.add('hidden');

  if (cropper) cropper.destroy();
  cropper = new Cropper(cropImage, {
    aspectRatio: 1,
    viewMode: 1,
    autoCropArea: 1,
  });
});

// Crop and use
cropBtn.addEventListener('click', () => {
  const croppedCanvas = cropper.getCroppedCanvas({ width: 300, height: 300 });
  const croppedDataURL = croppedCanvas.toDataURL('image/png');

  profilePreview.src = croppedDataURL;

  // Replace file input content with cropped image
  fetch(croppedDataURL)
    .then(res => res.blob())
    .then(blob => {
      const file = new File([blob], "profile.png", { type: "image/png" });
      const dt = new DataTransfer();
      dt.items.add(file);
      fileInput.files = dt.files;
    });

  // Close modal
  cameraModal.classList.add('hidden');
  cropContainer.classList.add('hidden');
  video.classList.remove('hidden');
  if (stream) stream.getTracks().forEach(track => track.stop());
});
</script>



<script type="module">
  import { initializeApp } from "https://www.gstatic.com/firebasejs/12.2.1/firebase-app.js";
  import { getAuth, createUserWithEmailAndPassword, sendEmailVerification } 
    from "https://www.gstatic.com/firebasejs/12.2.1/firebase-auth.js";

  async function initFirebase() {
    const res = await fetch("/public_html/firebase-config.php");
    const firebaseConfig = await res.json();

    const app = initializeApp(firebaseConfig);
    const auth = getAuth(app);

    document.querySelector("form").addEventListener("submit", async (e) => {
      e.preventDefault();
      const form = e.target;
      const email = form.email.value;
      const password = form.password.value;

      try {
        const userCredential = await createUserWithEmailAndPassword(auth, email, password);
        const user = userCredential.user;

        await sendEmailVerification(user);

        let uidInput = form.querySelector("input[name='firebase_uid']");
        if (!uidInput) {
          uidInput = document.createElement("input");
          uidInput.type = "hidden";
          uidInput.name = "firebase_uid";
          form.appendChild(uidInput);
        }
        uidInput.value = user.uid;

        form.submit();
      } catch (error) {
        alert("Firebase Auth Error: " + error.message);
      }
    });
  }

  initFirebase();
</script>



<?php 
else: 
  
endif; 
?>







                                        <!-- EMPLOYEE AREA  -->






 <?php
// Assume session stores the logged-in user's ID and role
include __DIR__ . '/../dbconnection/mainDB.php'; // hr3_maindb
$mainConn = $conn;

$user_id = $_SESSION['user_id'];
$roles = $_SESSION['roles']; // 'Admin', 'Manager', or 'Employee'

// Connect to mainDB
include __DIR__ . '/../dbconnection/mainDB.php';

// Only allow employees to view this page
if ($roles !== 'Employee') {
  
    exit;
}

// Fetch employee profile using their user_id
$stmt = $mainConn->prepare("
    SELECT u.username, u.email, up.first_name, up.last_name
    FROM users u
    JOIN user_profiles up ON u.user_id = up.user_id
    WHERE u.user_id = ?
");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
?>

<div class="bg-white shadow-md rounded-2xl p-10 w-full mx-auto mt-10 mb-10 max-w-lg">
    <h2 class="text-2xl font-bold mb-6 text-center">My Account</h2>

    <?php if ($employee): ?>
    <div class="space-y-4">
        <div>
            <label class="block text-gray-700 font-semibold mb-1">Username</label>
            <p class="p-2 border rounded bg-gray-50"><?php echo htmlspecialchars($employee['username']); ?></p>
        </div>
        <div>
            <label class="block text-gray-700 font-semibold mb-1">Email</label>
            <p class="p-2 border rounded bg-gray-50"><?php echo htmlspecialchars($employee['email']); ?></p>
        </div>
        <div>
            <label class="block text-gray-700 font-semibold mb-1">First Name</label>
            <p class="p-2 border rounded bg-gray-50"><?php echo htmlspecialchars($employee['first_name']); ?></p>
        </div>
        <div>
            <label class="block text-gray-700 font-semibold mb-1">Last Name</label>
            <p class="p-2 border rounded bg-gray-50"><?php echo htmlspecialchars($employee['last_name']); ?></p>
        </div>
    </div>
    <?php else: ?>
        <p class="text-center text-red-500">Your account information could not be found.</p>
    <?php endif; ?>
</div>






</body>
</html>

