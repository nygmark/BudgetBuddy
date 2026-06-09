<?php
// Shared helper functions for expense totals, budget checks, savings etc.
// Include after db connection is available.

function get_total_savings($conn, $userId) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM savings WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($row['total'] ?? 0);
}

function get_daily_spent($conn, $userId, $date, $category = null) {
    if ($category) {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
                                WHERE user_id = ? AND expense_date = ? AND category = ?");
        $stmt->bind_param("iss", $userId, $date, $category);
    } else {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
                                WHERE user_id = ? AND expense_date = ?");
        $stmt->bind_param("is", $userId, $date);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($row['total'] ?? 0);
}

function get_weekly_spent($conn, $userId, $endDate = null) {
    if (!$endDate) $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime($endDate . ' -6 days')); // last 7 days incl end
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
                            WHERE user_id = ? AND expense_date BETWEEN ? AND ?");
    $stmt->bind_param("iss", $userId, $startDate, $endDate);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($row['total'] ?? 0);
}

function get_monthly_spent($conn, $userId, $yearMonth = null) {
    if (!$yearMonth) $yearMonth = date('Y-m');
    $start = $yearMonth . '-01';
    $end = date('Y-m-t', strtotime($start));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
                            WHERE user_id = ? AND expense_date BETWEEN ? AND ?");
    $stmt->bind_param("iss", $userId, $start, $end);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($row['total'] ?? 0);
}

function get_category_spent_for_period($conn, $userId, $startDate, $endDate, $category) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
                            WHERE user_id = ? AND expense_date BETWEEN ? AND ? AND category = ?");
    $stmt->bind_param("isss", $userId, $startDate, $endDate, $category);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($row['total'] ?? 0);
}

// Returns true if on the given date the category spend exceeds the user's daily limit for that cat
function is_over_daily_limit($conn, $userId, $date, $category, $userRow) {
    $spent = get_daily_spent($conn, $userId, $date, $category);
    $limit = ($category === 'food') 
        ? (float)($userRow['food_daily_limit'] ?? 150) 
        : (float)($userRow['transpo_daily_limit'] ?? 100);
    return $spent > $limit;
}

// Get all expenses for a user (for lists/filters)
function get_all_expenses($conn, $userId) {
    $stmt = $conn->prepare("SELECT id, amount, category, description, expense_date 
                            FROM expenses WHERE user_id = ? ORDER BY expense_date DESC, id DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Get recent savings contributions
function get_recent_savings($conn, $userId, $limit = 8) {
    $stmt = $conn->prepare("SELECT amount, note, savings_date FROM savings 
                            WHERE user_id = ? ORDER BY savings_date DESC, id DESC LIMIT ?");
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}
?>