
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<?php
session_start();

// Include your DB connection
include __DIR__ . '/../dbconnection/mainDB.php';

// Config
$client_id = '1418948681310404670';
$client_secret = 'oVFjNWBVgMBNYTpCf53VrFyZU7QpU9Rk';
$redirect_uri = 'https://localhost/public_html/shift/discord_callback.php'; // Must match Discord portal
$token_url = 'https://discord.com/api/oauth2/token';
$user_url  = 'https://discord.com/api/users/@me';

// 1️⃣ Get OAuth parameters
$employee_id = $_GET['state'] ?? null;  // state carries your employee_id
$code        = $_GET['code'] ?? null;

if (!$employee_id || !$code) {
    die("Error: Missing employee_id or OAuth code.");
}

// 2️⃣ Exchange code for access token
$data = [
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'redirect_uri'  => $redirect_uri,
    'scope'         => 'identify'
];

$options = [
    'http' => [
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data),
    ],
];

$context = stream_context_create($options);
$response = file_get_contents($token_url, false, $context);
if ($response === FALSE) {
    die("Error fetching access token from Discord.");
}

$tokenData = json_decode($response, true);
$access_token = $tokenData['access_token'] ?? null;
if (!$access_token) {
    die("Error: Invalid access token response.");
}

// 3️⃣ Fetch Discord user info
$options = [
    'http' => [
        'header' => "Authorization: Bearer $access_token\r\n",
        'method' => 'GET'
    ]
];
$context = stream_context_create($options);
$userResponse = file_get_contents($user_url, false, $context);
if ($userResponse === FALSE) {
    die("Error fetching Discord user info.");
}

$userData = json_decode($userResponse, true);
$discord_id = $userData['id'] ?? null;
$username   = $userData['username'] ?? null;
$discriminator = $userData['discriminator'] ?? null;
$full_username = $username ? $username . '#' . $discriminator : null;

if (!$discord_id) {
    die("Error: Discord ID not found.");
}

// 4️⃣ Save to database
$stmt = $conn->prepare("
    INSERT INTO employee_discord (employee_id, discord_id, username)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE
        username = VALUES(username),
        updated_at = NOW()
");
$stmt->bind_param("sss", $employee_id, $discord_id, $full_username);
if (!$stmt->execute()) {
    die("DB Error: " . $stmt->error);
}
$stmt->close();

// 5️⃣ Success message
echo "Discord linked successfully for employee ID: $employee_id!<br>";
echo "Discord username: $full_username";
?>
