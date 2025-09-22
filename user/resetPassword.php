<?php
session_start();
include __DIR__ . '/../dbconnection/mainDB.php';

$token = $_GET['token'] ?? '';
$message = '';
$messageType = '';

if (!$token) {
    die("Invalid password reset link.");
}

// Check if token is valid
$stmt = $conn->prepare("SELECT user_id, expires_at FROM password_resets WHERE token=?");
$stmt->bind_param("s", $token);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || strtotime($row['expires_at']) < time()) {
    die("This reset link has expired or is invalid.");
}

$user_id = $row['user_id'];

// Handle password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$newPassword || !$confirmPassword) {
        $message = " All fields are required.";
        $messageType = "error";
    } elseif ($newPassword !== $confirmPassword) {
        $message = " Passwords do not match.";
        $messageType = "error";
    } elseif (strlen($newPassword) < 8) {
        $message = " Password must be at least 8 characters long.";
        $messageType = "error";
    } else {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);

        // Update user password
        $stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE user_id=?");
        $stmt->bind_param("ss", $hash, $user_id);
        $stmt->execute();
        $stmt->close();

        // Delete the token
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE token=?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();

        $message = "Password updated successfully. You can now log in.";
        $messageType = "success";
    }
}
?>

<form method="POST">
    <input type="password" name="new_password" placeholder="New Password" required>
    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
    <button type="submit">Reset Password</button>
</form>

<?php if ($message): ?>
<p class="<?= $messageType ?>"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>
