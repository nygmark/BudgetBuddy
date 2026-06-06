<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';

$input = $_POST;
if (empty($input)) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email and password required']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid password']);
    exit;
}

// Login success - set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];

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