<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>BudgetBuddy - Dashboard</title>
</head>
<body>
    <h1>BudgetBuddy - Dashboard (Overview)</h1>
    <?php include __DIR__ . '/nav.php'; // plain nav inside frontend folder ?>

    <p><strong>Welcome back, <?= htmlspecialchars($userName) ?>!</strong></p>

    <?php
    $today = date('Y-m-d');
    $todayTotal = get_daily_spent($conn, $userId, $today);
    $weekTotal  = get_weekly_spent($conn, $userId);
    $monthTotal = get_monthly_spent($conn, $userId);

    $totalSavings = get_total_savings($conn, $userId);
    $goalTarget   = (float)($currentUser['goal_target'] ?? 10000);
    $goalName     = $currentUser['goal_name'] ?? 'My Savings Goal';
    $goalDeadline = $currentUser['goal_deadline'] ?? null;
    $progress     = ($goalTarget > 0) ? min(100, round($totalSavings / $goalTarget * 100)) : 0;
    $remainingGoal = max(0, $goalTarget - $totalSavings);

    $foodLimit    = (float)($currentUser['food_daily_limit'] ?? 150);
    $transpoLimit = (float)($currentUser['transpo_daily_limit'] ?? 100);
    $foodToday    = get_daily_spent($conn, $userId, $today, 'food');
    $transpoToday = get_daily_spent($conn, $userId, $today, 'transpo');

    $overFood    = $foodToday > $foodLimit;
    $overTranspo = $transpoToday > $transpoLimit;

    $reminders = [];
    if (!$overFood && !$overTranspo && $todayTotal == 0) {
        $reminders[] = "Reminder: You have not logged any expenses today.";
    }
    if ($totalSavings < $goalTarget) {
        $reminders[] = "Reminder: Keep saving for your goal \"" . htmlspecialchars($goalName) . "\".";
    }
    if ($overFood) {
        $reminders[] = "ALERT: You exceeded your daily FOOD limit today (₱" . number_format($foodToday,2) . " / ₱" . number_format($foodLimit,2) . ").";
    }
    if ($overTranspo) {
        $reminders[] = "ALERT: You exceeded your daily TRANSPO limit today (₱" . number_format($transpoToday,2) . " / ₱" . number_format($transpoLimit,2) . ").";
    }
    if (empty($reminders)) {
        $reminders[] = "All good — no urgent reminders.";
    }
    ?>

    <!-- Quick Overview Numbers -->
    <h2>Quick Overview</h2>
    <table border="1" style="width:auto; border-collapse:collapse;">
        <tr><th>Metric</th><th>Amount</th></tr>
        <tr><td>Today's Total Expenses</td><td>₱ <?= number_format($todayTotal, 2) ?></td></tr>
        <tr><td>This Week's Total Expenses</td><td>₱ <?= number_format($weekTotal, 2) ?></td></tr>
        <tr><td>This Month's Total Expenses</td><td>₱ <?= number_format($monthTotal, 2) ?></td></tr>
        <tr><td>Current Savings</td><td>₱ <?= number_format($totalSavings, 2) ?></td></tr>
    </table>

    <!-- Daily Budget Status (today) -->
    <h2>Today's Budget Status</h2>
    <table border="1" style="width:auto;">
        <tr>
            <th>Category</th>
            <th>Spent Today</th>
            <th>Daily Limit</th>
            <th>Status</th>
        </tr>
        <tr>
            <td>Food</td>
            <td>₱ <?= number_format($foodToday, 2) ?></td>
            <td>₱ <?= number_format($foodLimit, 2) ?></td>
            <td><?= $overFood ? '<strong style="color:red;">EXCEEDED</strong>' : 'OK' ?></td>
        </tr>
        <tr>
            <td>Transpo</td>
            <td>₱ <?= number_format($transpoToday, 2) ?></td>
            <td>₱ <?= number_format($transpoLimit, 2) ?></td>
            <td><?= $overTranspo ? '<strong style="color:red;">EXCEEDED</strong>' : 'OK' ?></td>
        </tr>
    </table>

    <!-- Saving Goal Progress (current goal) -->
    <h2>Saving Goal: <?= htmlspecialchars($goalName) ?></h2>
    <div style="width:320px; height:22px; border:1px solid #000; margin:5px 0;">
        <div style="width:<?= $progress ?>%; height:100%; background:#4caf50; color:white; text-align:center; font-size:13px; line-height:22px;">
            <?= $progress ?>%
        </div>
    </div>
    <p>
        Saved: ₱ <?= number_format($totalSavings, 2) ?> &nbsp;&nbsp;
        Target: ₱ <?= number_format($goalTarget, 2) ?> &nbsp;&nbsp;
        Remaining: ₱ <?= number_format($remainingGoal, 2) ?>
        <?php if ($goalDeadline): ?> &nbsp;&nbsp; Deadline: <?= htmlspecialchars($goalDeadline) ?><?php endif; ?>
    </p>

    <!-- Reminders -->
    <h2>Reminders / Alerts</h2>
    <?php foreach ($reminders as $r): ?>
        <div style="margin:4px 0; padding:6px; border:1px solid #999; background:#ffffcc;">
            <?= htmlspecialchars($r) ?>
        </div>
    <?php endforeach; ?>

    <h2>Go to Features</h2>
    <ul>
        <li><a href="expense-tracker.php"><strong>EXPENSE TRACKER</strong></a> — Add daily expenses (Food/Transpo), see daily/weekly/monthly totals</li>
        <li><a href="budget-limits.php"><strong>BUDGET LIMITS</strong></a> — Set daily limits for Food &amp; Transpo, check for exceeds</li>
        <li><a href="saving-goal.php"><strong>SAVING GOAL</strong></a> — Set your goal name/target/deadline, add savings, view progress</li>
    </ul>

    <p><small>Plain pages — no design. All files for frontend are in this folder (with PHP for rendering + backend).</small></p>
</body>
</html>