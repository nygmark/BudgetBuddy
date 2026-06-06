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
    // Get current limits + optional usage for a date
    $stmt = $conn->prepare("SELECT food_daily_limit, transpo_daily_limit FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $limits = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $date = $_GET['date'] ?? date('Y-m-d');

    // Compute spent that day per category
    $stmt = $conn->prepare("SELECT 
        SUM(CASE WHEN category='food' THEN amount ELSE 0 END) as food_spent,
        SUM(CASE WHEN category='transpo' THEN amount ELSE 0 END) as transpo_spent
        FROM expenses WHERE user_id = ? AND expense_date = ?");
    $stmt->bind_param("is", $userId, $date);
    $stmt->execute();
    $spent = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $foodSpent = (float)($spent['food_spent'] ?? 0);
    $transpoSpent = (float)($spent['transpo_spent'] ?? 0);

    $foodLimit = (float)$limits['food_daily_limit'];
    $transpoLimit = (float)$limits['transpo_daily_limit'];

    echo json_encode([
        'success' => true,
        'limits' => [
            'food_daily_limit' => $foodLimit,
            'transpo_daily_limit' => $transpoLimit
        ],
        'usage' => [
            'date' => $date,
            'food_spent' => $foodSpent,
            'transpo_spent' => $transpoSpent,
            'food_over' => $foodSpent > $foodLimit,
            'transpo_over' => $transpoSpent > $transpoLimit,
            'food_remaining' => max(0, $foodLimit - $foodSpent),
            'transpo_remaining' => max(0, $transpoLimit - $transpoSpent)
        ]
    ]);
    exit;
}

if ($method === 'POST') {
    $input = $_POST;
    if (empty($input)) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    $foodLim = isset($input['food_daily_limit']) ? floatval($input['food_daily_limit']) : null;
    $transLim = isset($input['transpo_daily_limit']) ? floatval($input['transpo_daily_limit']) : null;

    if ($foodLim === null && $transLim === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No limits provided']);
        exit;
    }

    $sqlParts = [];
    $types = '';
    $vals = [];

    if ($foodLim !== null) {
        $sqlParts[] = "food_daily_limit = ?";
        $types .= 'd';
        $vals[] = $foodLim;
    }
    if ($transLim !== null) {
        $sqlParts[] = "transpo_daily_limit = ?";
        $types .= 'd';
        $vals[] = $transLim;
    }
    $vals[] = $userId;

    $sql = "UPDATE users SET " . implode(', ', $sqlParts) . " WHERE id = ?";
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$vals);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Limits updated']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Update failed']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
?>