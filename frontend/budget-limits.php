<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$err = isset($_GET['err']) ? $_GET['err'] : '';

$today = date('Y-m-d');

// Update limits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_limits') {
    $foodLim = max(0, floatval($_POST['food_daily_limit'] ?? 0));
    $transLim = max(0, floatval($_POST['transpo_daily_limit'] ?? 0));

    $upd = $conn->prepare("UPDATE users SET food_daily_limit = ?, transpo_daily_limit = ? WHERE id = ?");
    $upd->bind_param("ddi", $foodLim, $transLim, $userId);
    if ($upd->execute()) {
        $msg = "Daily budget limits updated.";
        $currentUser['food_daily_limit'] = $foodLim;
        $currentUser['transpo_daily_limit'] = $transLim;
    } else {
        $err = "Failed to update limits.";
    }
    $upd->close();
    header("Location: budget-limits.php?msg=" . urlencode($msg) . "&err=" . urlencode($err));
    exit;
}

$foodLimit = (float)($currentUser['food_daily_limit'] ?? 150);
$transpoLimit = (float)($currentUser['transpo_daily_limit'] ?? 100);

// Check date usage
$checkDate = $today;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check_date') {
    $checkDate = $_POST['check_date'] ?? $today;
}

$foodSpent = get_daily_spent($conn, $userId, $checkDate, 'food');
$transpoSpent = get_daily_spent($conn, $userId, $checkDate, 'transpo');

$overFood = $foodSpent > $foodLimit;
$overTrans = $transpoSpent > $transpoLimit;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>BudgetBuddy - Budget Limits</title>
</head>
<body>
    <h1>BUDGET LIMIT</h1>
    <?php include __DIR__ . '/nav.php'; ?>

    <?php if ($msg): ?><p style="color:green;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <?php if ($err): ?><p style="color:red;"><?= htmlspecialchars($err) ?></p><?php endif; ?>

    <h2>Set Daily Budget Limits</h2>
    <p>Example: Food ₱150/day, Transpo ₱100/day</p>

    <form method="POST">
        <input type="hidden" name="action" value="update_limits">

        <label>Food daily limit:</label>
        <input type="number" step="0.01" name="food_daily_limit" value="<?= htmlspecialchars($foodLimit) ?>" required style="width:110px;">

        <label>Transpo daily limit:</label>
        <input type="number" step="0.01" name="transpo_daily_limit" value="<?= htmlspecialchars($transpoLimit) ?>" required style="width:110px;">

        <button type="submit">Save Limits</button>
    </form>

    <hr>

    <h2>Check Budget Usage &amp; Alerts</h2>

    <form method="POST" style="margin-bottom:10px;">
        <input type="hidden" name="action" value="check_date">
        <label>Check date:</label>
        <input type="date" name="check_date" value="<?= htmlspecialchars($checkDate) ?>">
        <button type="submit">Check this date</button>
        <small>(defaults to today)</small>
    </form>

    <table border="1" style="width:auto;">
        <tr>
            <th>Category</th>
            <th>Daily Limit</th>
            <th>Spent on <?= htmlspecialchars($checkDate) ?></th>
            <th>Status</th>
        </tr>
        <tr>
            <td><strong>Food</strong></td>
            <td>₱ <?= number_format($foodLimit, 2) ?></td>
            <td>₱ <?= number_format($foodSpent, 2) ?></td>
            <td>
                <?php if ($overFood): ?>
                    <span style="color:red; font-weight:bold;">EXCEEDED — ALERT!</span>
                <?php else: ?>
                    OK (₱<?= number_format($foodLimit - $foodSpent, 2) ?> left)
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td><strong>Transpo</strong></td>
            <td>₱ <?= number_format($transpoLimit, 2) ?></td>
            <td>₱ <?= number_format($transpoSpent, 2) ?></td>
            <td>
                <?php if ($overTrans): ?>
                    <span style="color:red; font-weight:bold;">EXCEEDED — ALERT!</span>
                <?php else: ?>
                    OK (₱<?= number_format($transpoLimit - $transpoSpent, 2) ?> left)
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <?php if ($overFood || $overTrans): ?>
        <div style="margin-top:12px; padding:10px; border:2px solid red; background:#ffeeee;">
            <strong>ALERT:</strong> You have exceeded your daily limit on <?= htmlspecialchars($checkDate) ?> 
            for the category/categories shown above.
        </div>
    <?php else: ?>
        <p style="margin-top:10px;">No exceed on the selected date for the current limits.</p>
    <?php endif; ?>

    <p><small>Frontend page (PHP + HTML mixed) stored in the frontend folder.</small></p>
</body>
</html>