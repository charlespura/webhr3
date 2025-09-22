<?php
// Load .env
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode("=", $line, 2);
        putenv(trim($name) . "=" . trim($value));
    }
}
loadEnv(__DIR__ . '/.env');

// Send safe config to frontend
header("Content-Type: application/json");
echo json_encode([
    "apiKey" => getenv("FIREBASE_API_KEY"),
    "authDomain" => getenv("FIREBASE_AUTH_DOMAIN"),
    "projectId" => getenv("FIREBASE_PROJECT_ID"),
    "storageBucket" => getenv("FIREBASE_STORAGE_BUCKET"),
    "messagingSenderId" => getenv("FIREBASE_MESSAGING_SENDER_ID"),
    "appId" => getenv("FIREBASE_APP_ID"),
    "measurementId" => getenv("FIREBASE_MEASUREMENT_ID")
]);
