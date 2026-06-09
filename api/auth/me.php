<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id, first_name, last_name, email, food_daily_limit, transpo_daily_limit, goal_name, goal_target, goal_deadline FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'user' => [
        'id' => (int)$user['id'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'food_daily_limit' => (float)$user['food_daily_limit'],
        'transpo_daily_limit' => (float)$user['transpo_daily_limit'],
        'goal_name' => $user['goal_name'],
        'goal_target' => (float)$user['goal_target'],
        'goal_deadline' => $user['goal_deadline'],
    ]
]);
?>