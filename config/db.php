<?php
$conn = new mysqli("localhost", "root", "", "budget_buddy");

// Set charset for safety
if (!$conn->connect_error) {
    $conn->set_charset("utf8mb4");
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>