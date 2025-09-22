

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>




<?php
include __DIR__ . '/../dbconnection/mainDB.php';
header('Content-Type: application/json');



// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line || strpos($line, '#') === 0) continue;

        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $value = trim($value);
            // Remove surrounding quotes if present
            $value = preg_replace('/^["\'](.*)["\']$/', '$1', $value);
            putenv(trim($name) . "=" . $value);
            $_ENV[trim($name)] = $value; // Optional: for $_ENV access
        }
    }
}
loadEnv(__DIR__ . '/../.env');


// Get POST data
$employee_id = $_POST['employee_id'] ?? '';
$work_date   = $_POST['work_date'] ?? '';
$shift_id    = $_POST['shift_id'] ?? null;

if (!$employee_id || !$work_date) {
    echo json_encode(["status"=>"error","message"=>"Missing data"]);
    exit;
}

// Normalize shift_id
if ($shift_id === "" || strtolower((string)$shift_id) === "null") {
    $shift_id = null;
}

// Function to send Discord DM
function sendDiscordDM($bot_token, $discord_id, $message) {
    // Create DM channel
    $url = "https://discord.com/api/v10/users/@me/channels";
    $data = ['recipient_id' => $discord_id];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bot $bot_token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);

    $res_data = json_decode($res, true);
    $channel_id = $res_data['id'] ?? null;

    if ($channel_id) {
        // Send message
        $url = "https://discord.com/api/v10/channels/$channel_id/messages";
        $data = ['content' => $message];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bot $bot_token",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}

$bot_token = getenv('DISCORD_BOT_TOKEN');


if (!$bot_token) {
    echo json_encode(["status"=>"error","message"=>"Missing Discord bot token"]);
    exit;
}
// --- Delete schedule if shift_id is null ---
if ($shift_id === null) {
    $sql = "DELETE FROM employee_schedules WHERE employee_id=? AND work_date=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $employee_id, $work_date);
    if ($stmt->execute()) {
        echo json_encode(["status"=>"success","message"=>"Schedule removed"]);
    } else {
        echo json_encode(["status"=>"error","message"=>$stmt->error]);
    }
    $stmt->close();

    // Send Discord notification
    $discord_stmt = $conn->prepare("SELECT discord_id FROM employee_discord WHERE employee_id=?");
    $discord_stmt->bind_param("s", $employee_id);
    $discord_stmt->execute();
    $discord_data = $discord_stmt->get_result()->fetch_assoc();
    $discord_stmt->close();

    $discord_id = $discord_data['discord_id'] ?? null;
    if ($discord_id) {
        $message = "⚠️ Your shift on $work_date has been **removed**.";
        sendDiscordDM($bot_token, $discord_id, $message);
    }

} else {
    // Insert or update schedule
    $status = 'scheduled';
    $sql = "
        INSERT INTO employee_schedules 
            (employee_id, work_date, shift_id, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
            shift_id   = VALUES(shift_id),
            status     = VALUES(status),
            updated_at = NOW()
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $employee_id, $work_date, $shift_id, $status);

    if ($stmt->execute()) {
        echo json_encode(["status"=>"success","message"=>"Schedule saved"]);

        // Fetch shift details
        $shift_stmt = $conn->prepare("
            SELECT shift_code, name AS shift_name, start_time, end_time
            FROM shifts
            WHERE shift_id = ?
        ");
        $shift_stmt->bind_param("s", $shift_id);
        $shift_stmt->execute();
        $shift_data = $shift_stmt->get_result()->fetch_assoc();
        $shift_stmt->close();

        // Build message
        if ($shift_data) {
            $message = "✅ Your shift for $work_date has been **added/updated**:\n";
            $message .= "⏰ Shift: {$shift_data['shift_code']} - {$shift_data['shift_name']}\n";
            $message .= "🕒 Time: {$shift_data['start_time']} - {$shift_data['end_time']}";
        } else {
            $message = "⚠️ Your shift on $work_date has changed, but shift info is unavailable.";
        }

        // Send Discord DM
        $discord_stmt = $conn->prepare("SELECT discord_id FROM employee_discord WHERE employee_id=?");
        $discord_stmt->bind_param("s", $employee_id);
        $discord_stmt->execute();
        $discord_data = $discord_stmt->get_result()->fetch_assoc();
        $discord_stmt->close();

        $discord_id = $discord_data['discord_id'] ?? null;
        if ($discord_id) {
            sendDiscordDM($bot_token, $discord_id, $message);
        }

    } else {
        echo json_encode(["status"=>"error","message"=>$stmt->error]);
    }

    $stmt->close();
}
?>
