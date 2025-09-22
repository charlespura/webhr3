



<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<?php
session_start();
include __DIR__ . '/dbconnection/mainDb.php';

// 🔹 Remove Remember Me token from DB and cookie if it exists
if (isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];

    // Remove token from database
    $stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();

    // Delete the cookie
    setcookie('remember_me', '', time() - 3600, "/", "", true, true);
}

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Optional: clear the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Redirect to login page after logout
header("Location: index.php");
exit;
?>
