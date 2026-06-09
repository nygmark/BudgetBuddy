<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';

$input = $_POST;
if (empty($input)) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

$firstName = trim($input['firstName'] ?? '');
$lastName  = trim($input['lastName'] ?? '');
$email     = trim($input['email'] ?? '');
$password  = $input['password'] ?? '';
$confirm   = $input['confirm'] ?? '';

$errors = [];
if (!$firstName || !$lastName) $errors[] = "First and last name required";
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email required";
if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
if ($password !== $confirm) $errors[] = "Passwords do not match";

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
    exit;
}

// Check duplicate
$check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Email already registered']);
    exit;
}
$check->close();

$hashed = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, food_daily_limit, transpo_daily_limit, goal_name, goal_target) 
                        VALUES (?, ?, ?, ?, 150.00, 100.00, 'My Savings Goal', 10000.00)");
$stmt->bind_param("ssss", $firstName, $lastName, $email, $hashed);

if ($stmt->execute()) {
    $newId = $stmt->insert_id;
    $_SESSION['user_id'] = $newId;
    $_SESSION['user_email'] = $email;

    // Return basic user (fetch fresh or construct)
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $newId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'food_daily_limit' => 150.00,
            'transpo_daily_limit' => 100.00,
            'goal_name' => 'My Savings Goal',
            'goal_target' => 10000.00,
            'goal_deadline' => null,
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Registration failed']);
}
$stmt->close();
?>