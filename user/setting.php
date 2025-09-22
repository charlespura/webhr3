<?php
session_start();
include __DIR__ . '/../dbconnection/mainDB.php';
require __DIR__ . '/../config/GoogleAuthenticator-master/PHPGangsta/GoogleAuthenticator.php';

$ga = new PHPGangsta_GoogleAuthenticator();

// ---------------- SESSION & USER VALIDATION ----------------
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user safely
$stmt = $conn->prepare("SELECT user_id, email, two_factor_enabled, two_factor_secret FROM users WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;

// Ensure $user is always an array
if (!is_array($user)) {
    $user = [
        'email' => '',
        'two_factor_enabled' => 0,
        'two_factor_secret' => ''
    ];
}

$email = $user['email'] ?? '';
$enabled = $user['two_factor_enabled'] ?? 0;
$secret = $user['two_factor_secret'] ?? '';
$qrCodeUrl = '';

// ---------------- 2FA LOGIC ----------------

// Step 1: Enable 2FA (generate secret + QR)
if (isset($_POST['enable_2fa'])) {
    $secret = $ga->createSecret();

    $stmt = $conn->prepare("UPDATE users SET two_factor_secret = ?, two_factor_enabled = 0 WHERE user_id = ?");
    $stmt->bind_param("ss", $secret, $user_id);
    $stmt->execute();

    $user['two_factor_secret'] = $secret;
    $user['two_factor_enabled'] = 0;

    // Generate QR code immediately
    if (!empty($email)) {
        $qrCodeUrl = $ga->getQRCodeGoogleUrl('HR3System', $secret, $email);
    }
}

// Step 2: Verify 2FA code
if (isset($_POST['verify_2fa'])) {
    $code = $_POST['2fa_code'] ?? '';
    $secret = $user['two_factor_secret'] ?? '';

    if (!empty($secret) && $ga->verifyCode($secret, $code, 2)) {
        $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 1 WHERE user_id = ?");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();

        $user['two_factor_enabled'] = 1;
        $enabled = 1;
        $message = "Two-factor authentication enabled successfully!";
        $messageType = "success";
    } else {
        $message = "Invalid code. Please try again.";
        $messageType = "error";
        if (!empty($email)) {
            $qrCodeUrl = $ga->getQRCodeGoogleUrl('HR3System', $secret, $email);
        }
    }
}

// Step 3: Disable 2FA
if (isset($_POST['disable_2fa'])) {
    $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();

    $user['two_factor_enabled'] = 0;
    $user['two_factor_secret'] = '';
    $enabled = 0;
    $qrCodeUrl = '';
    $message = "Two-factor authentication disabled.";
    $messageType = "success";
}

// ---------------- SESSION INACTIVITY CHECK ----------------
$timeout = 900; // 15 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: ../index.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = time();

// Optional user info
$roles = $_SESSION['roles'] ?? 'Employee';
$loggedInUserId = $_SESSION['employee_id'] ?? null;
$loggedInUserName = $_SESSION['user_name'] ?? 'Guest';

if (!$loggedInUserId) {
    header("Location: ../index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>2FA Settings</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="icon" type="image/png" href="../picture/logo2.png" />
</head>
<body class="h-screen overflow-hidden">
<div class="flex h-full">
    <?php include '../sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-y-auto">
        <main class="p-6 space-y-4">
            <div class="flex items-center justify-between border-b py-6">
                <h2 class="text-xl font-semibold text-gray-800">Settings</h2>
                <?php include '../profile.php'; ?>
            </div>

            
<div class="bg-white shadow-md rounded-2xl p-10 w-full mx-auto mt-10 mb-10 space-y-6">
     
   <h2 class="text-2xl font-bold text-gray-800 text-center">Two-Factor Authentication (2FA)</h2>
 
    <?php if (!empty($message)): ?>
        <div class="p-4 mb-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-red-100 text-red-700 border border-red-300' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($enabled == 0 && empty($secret)): ?>
        <!-- Info Note -->



        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg text-blue-800">

        
            <p class="font-medium">What is 2FA?</p>
            <p class="text-sm mt-1">Two-factor authentication adds an extra layer of security. 
                You will need your password <strong>and</strong> a code from Google Authenticator.</p>
        </div>

        <!-- Step 1 -->
        <div class="p-4 bg-gray-50 border rounded-lg space-y-2">
            <p class="font-medium">Step 1: Enable 2FA</p>
            <p class="text-sm">Click the button below to generate your QR code.</p>
            <form method="POST">
                <button type="submit" name="enable_2fa" 
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow">
                    Generate QR Code
                </button>
            </form>
        </div>

    <?php elseif ($enabled == 0 && !empty($secret)): ?>
        <!-- Step 2 -->
        <div class="p-4 bg-gray-50 border rounded-lg space-y-2">
            <p class="font-medium">Step 2: Scan QR Code</p>
            <p class="text-sm">Open the <strong>Google Authenticator</strong> app, tap <em>+</em> and scan this QR code.</p>
            <?php if (!empty($qrCodeUrl)): ?>
                <img src="<?= $qrCodeUrl ?>" alt="QR Code" class="my-4 mx-auto border p-2 rounded">
            <?php endif; ?>
        </div>

        <!-- Step 3 -->
        <div class="p-4 bg-gray-50 border rounded-lg space-y-2">
            <p class="font-medium">Step 3: Verify Code</p>
            <p class="text-sm">Enter the 6-digit code shown in your authenticator app.</p>
            <form method="POST" class="space-y-2">
                <input type="text" name="2fa_code" placeholder="Enter 6-digit code" 
                       class="border rounded-lg w-full px-3 py-2 focus:ring focus:ring-blue-300" required>
                <button type="submit" name="verify_2fa" 
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow">
                    Verify & Enable 2FA
                </button>
            </form>
        </div>

    <?php else: ?>
        <!-- Enabled State -->
        <div class="p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
            <p class="font-medium">2FA is enabled</p>
            <p class="text-sm mt-1">Your account is protected with two-factor authentication.</p>
        </div>

        <div class="p-4 bg-gray-50 border rounded-lg space-y-2">
            <p class="font-medium text-red-600">Disable 2FA</p>
            <p class="text-sm">If you disable 2FA, your account will only be protected by your password.</p>
            <form method="POST">
                <button type="submit" name="disable_2fa" 
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg shadow">
                    Disable 2FA
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="bg-white shadow-md rounded-2xl p-10 w-full mx-auto mt-10 mb-10 space-y-6">
 

    <div class="bg-white shadow-lg rounded-2xl p-8 space-y-6">
        <h2 class="text-2xl font-bold text-gray-800 text-center">Connect Your Discord</h2>
        <p class="text-gray-500 text-center">
            Register your Discord account to receive your shift updates directly in Discord.
        </p>

        <?php
        if (session_status() === PHP_SESSION_NONE) session_start();
        $employee_id = $_SESSION['employee_id'] ?? null;
        if (!$employee_id) die("Error: employee_id not found in session.");
        include __DIR__ . '/../dbconnection/mainDB.php';

        $stmt = $conn->prepare("SELECT discord_id FROM employee_discord WHERE employee_id=?");
        $stmt->bind_param("s", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $discord_data = $result->fetch_assoc();
        $stmt->close();

        $connected = !empty($discord_data['discord_id']);

        $client_id = '1418948681310404670';
        $redirect_uri = 'https://yourdomain.com/shift/discord_callback.php';
        $discord_link = 'https://discord.com/api/oauth2/authorize?client_id=' . $client_id
                       . '&redirect_uri=' . urlencode($redirect_uri)
                       . '&response_type=code'
                       . '&scope=identify'
                       . '&state=' . urlencode($employee_id);
        ?>

        <a href="<?= htmlspecialchars($discord_link) ?>" target="_blank" class="discord-btn w-full flex justify-center items-center gap-3 py-3 rounded-xl text-white font-semibold text-lg transition hover:bg-indigo-700 shadow-md" id="discordButton">
            <img src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons/icons/discord.svg" alt="Discord Logo" class="w-6 h-6">
            <span id="buttonText"><?= $connected ? "Connected ✅" : "Register with Discord" ?></span>
        </a>

        <script>
        let isConnected = <?= $connected ? 'true' : 'false' ?>;
        if (isConnected) {
            let btn = document.getElementById('discordButton');
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.7';
        }
        </script>

        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg text-blue-800 text-center">
            Once connected, you will receive your shift schedules directly on Discord.
        </div>
    </div>
</div>

<style>
.discord-btn {
    background-color: #5865F2;
}
.discord-btn:hover {
    background-color: #4752c4;
}
</style>

        </main>
    </div>
</div>
</body>
</html>
