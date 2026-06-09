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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

$input = $_POST;
if (empty($input)) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

$fields = [];
$types = '';
$values = [];

if (isset($input['goal_name'])) {
    $fields[] = 'goal_name = ?';
    $types .= 's';
    $values[] = trim($input['goal_name']);
}
if (isset($input['goal_target'])) {
    $fields[] = 'goal_target = ?';
    $types .= 'd';
    $values[] = floatval($input['goal_target']);
}
if (isset($input['goal_deadline'])) {
    $fields[] = 'goal_deadline = ?';
    $types .= 's';
    $values[] = $input['goal_deadline'] ?: null;
}

if (empty($fields)) {
    echo json_encode(['success' => false, 'error' => 'Nothing to update']);
    exit;
}

$values[] = $userId;
$types .= 'i';

$sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$values);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok, 'message' => $ok ? 'Updated' : 'Update failed']);
?>