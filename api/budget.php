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
        $stmt = $conn->prepare("SELECT monthly_budget, budget_period FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $budgetAmount = (float)($row['monthly_budget'] ?? 5000);
        $budgetPeriod = $row['budget_period'] ?? 'monthly';

        $today      = date('Y-m-d');
        $weekStart  = date('Y-m-d', strtotime('-6 days'));
        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');
        $thisMonth  = date('Y-m');

        switch ($budgetPeriod) {
            case 'daily':
                $sql = "SELECT
                    COALESCE(SUM(amount), 0) as total_spent,
                    SUM(CASE WHEN category='food'    THEN amount ELSE 0 END) as food_spent,
                    SUM(CASE WHEN category='transpo' THEN amount ELSE 0 END) as transpo_spent
                    FROM expenses WHERE user_id = ? AND expense_date = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("is", $userId, $today);
                $stmt->execute();
                $spent = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $savStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as s FROM savings WHERE user_id = ? AND savings_date = ?");
                $savStmt->bind_param("is", $userId, $today);
                break;

            case 'weekly':
                $sql = "SELECT
                    COALESCE(SUM(amount), 0) as total_spent,
                    SUM(CASE WHEN category='food'    THEN amount ELSE 0 END) as food_spent,
                    SUM(CASE WHEN category='transpo' THEN amount ELSE 0 END) as transpo_spent
                    FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $userId, $weekStart, $today);
                $stmt->execute();
                $spent = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $savStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as s FROM savings WHERE user_id = ? AND savings_date BETWEEN ? AND ?");
                $savStmt->bind_param("iss", $userId, $weekStart, $today);
                break;

            default: // monthly
                $sql = "SELECT
                    COALESCE(SUM(amount), 0) as total_spent,
                    SUM(CASE WHEN category='food'    THEN amount ELSE 0 END) as food_spent,
                    SUM(CASE WHEN category='transpo' THEN amount ELSE 0 END) as transpo_spent
                    FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $userId, $monthStart, $monthEnd);
                $stmt->execute();
                $spent = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $savStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as s FROM savings WHERE user_id = ? AND DATE_FORMAT(savings_date,'%Y-%m') = ?");
                $savStmt->bind_param("is", $userId, $thisMonth);
                break;
        }
        $savStmt->execute();
        $savedAmount = (float)$savStmt->get_result()->fetch_assoc()['s'];
        $savStmt->close();

        $totalSpent = (float)($spent['total_spent']   ?? 0);
        $foodSpent  = (float)($spent['food_spent']    ?? 0);
        $transSpent = (float)($spent['transpo_spent'] ?? 0);
        $remaining  = max(0, $budgetAmount - $totalSpent);

        echo json_encode([
            'success' => true,
            'budget' => [
                'amount' => $budgetAmount,
                'period' => $budgetPeriod,
            ],
            'usage' => [
                'food_spent'    => $foodSpent,
                'transpo_spent' => $transSpent,
                'total_spent'   => $totalSpent,
                'saved'         => $savedAmount,
                'remaining'     => $remaining,
                'is_over'       => $totalSpent > $budgetAmount,
            ]
        ]);
        exit;
    }

    if ($method === 'POST') {
        $input = $_POST;
        if (empty($input)) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
        }

        $budgetAmt    = isset($input['budget_amount']) ? floatval($input['budget_amount']) : null;
        $budgetPeriod = isset($input['budget_period']) ? $input['budget_period']           : null;

        if ($budgetAmt === null && $budgetPeriod === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No budget data provided']);
            exit;
        }

        $sqlParts = [];
        $types    = '';
        $vals     = [];

        if ($budgetAmt !== null) {
            $sqlParts[] = "monthly_budget = ?";
            $types .= 'd';
            $vals[]  = max(0, $budgetAmt);
        }
        if ($budgetPeriod !== null && in_array($budgetPeriod, ['daily','weekly','monthly'])) {
            $sqlParts[] = "budget_period = ?";
            $types .= 's';
            $vals[]  = $budgetPeriod;
        }

        if (empty($sqlParts)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid budget data']);
            exit;
        }

        $vals[] = $userId;
        $types .= 'i';

        $sql  = "UPDATE users SET " . implode(', ', $sqlParts) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$vals);
        $ok   = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $ok, 'message' => $ok ? 'Budget updated' : 'Update failed']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    ?>