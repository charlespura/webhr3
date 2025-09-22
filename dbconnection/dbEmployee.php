<?php
// dbShift.php

$host = "localhost";      // Database host
$user = "root";           // Your MySQL username hratier2
$pass = "";               // Your MySQL password ily
$db   = "hr3_system"; // Database name

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Optional: set charset to avoid encoding issues
$conn->set_charset("utf8mb4");
?>
