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
    // Return all expenses for the user (frontend can filter by day/week/month/category in JS)
    $stmt = $conn->prepare("SELECT id, amount, category, description as notes, expense_date as date 
                            FROM expenses WHERE user_id = ? ORDER BY expense_date DESC, id DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'expenses' => $expenses]);
    exit;
}

if ($method === 'POST') {
    $input = $_POST;
    if (empty($input)) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    $action = $input['action'] ?? 'add';

    if ($action === 'delete') {
        $id = intval($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid id']);
            exit;
        }
        $del = $conn->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
        $del->bind_param("ii", $id, $userId);
        $del->execute();
        $del->close();
        echo json_encode(['success' => true, 'message' => 'Deleted']);
        exit;
    }

    // Default: add expense
    $amount = floatval($input['amount'] ?? 0);
    $category = $input['category'] ?? '';
    $date = $input['date'] ?? date('Y-m-d');
    $notes = trim($input['notes'] ?? $input['description'] ?? '');

    if ($amount <= 0 || !in_array($category, ['food', 'transpo'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid amount or category']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO expenses (user_id, amount, category, description, expense_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idsss", $userId, $amount, $category, $notes, $date);
    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $stmt->close();

        // Optional: return whether this caused daily limit exceed (frontend can also compute)
        echo json_encode([
            'success' => true,
            'message' => 'Expense added',
            'expense' => [
                'id' => $newId,
                'amount' => $amount,
                'category' => $category,
                'notes' => $notes,
                'date' => $date
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to add expense']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
?>