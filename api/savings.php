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

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $conn->prepare("SELECT id, amount, note, savings_date as date FROM savings 
                            WHERE user_id = ? ORDER BY savings_date DESC, id DESC LIMIT 50");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $savings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Also return total for convenience
    $totalStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total FROM savings WHERE user_id = ?");
    $totalStmt->bind_param("i", $userId);
    $totalStmt->execute();
    $total = (float)$totalStmt->get_result()->fetch_assoc()['total'];
    $totalStmt->close();

    echo json_encode(['success' => true, 'savings' => $savings, 'total_savings' => $total]);
    exit;
}

if ($method === 'POST') {
    $input = $_POST;
    if (empty($input)) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    $amount = floatval($input['amount'] ?? 0);
    $date = $input['date'] ?? date('Y-m-d');
    $note = trim($input['note'] ?? '');

    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid amount']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO savings (user_id, amount, note, savings_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("idss", $userId, $amount, $note, $date);
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Savings added',
            'saving' => ['amount' => $amount, 'date' => $date, 'note' => $note]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to add savings']);
    }
    $stmt->close();
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
?>