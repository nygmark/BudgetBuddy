<?php
// Shared: require login + load basic user info
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . "/../config/db.php");

if (!isset($_SESSION['user_id'])) {
    // Redirect to the frontend login (all UI now lives in frontend/ folder)
    header("Location: frontend/login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Load fresh user data (for limits, goal, name etc.)
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$currentUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$currentUser) {
    // Session user no longer exists
    session_destroy();
    header("Location: frontend/login.php");
    exit;
}

$userName = $currentUser['first_name'] . ' ' . $currentUser['last_name'];
?>