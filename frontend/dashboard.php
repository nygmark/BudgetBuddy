<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BudgetBuddy - Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>BudgetBuddy</h1>
            <p>Welcome back, <?= htmlspecialchars($userName) ?></p>
        </div>
        <?php include __DIR__ . '/nav.php'; ?>
    </header>

    <div class="container">
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
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Today</div>
                <div class="stat-value">₱<?= number_format($todayTotal, 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">This Week</div>
                <div class="stat-value">₱<?= number_format($weekTotal, 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">This Month</div>
                <div class="stat-value">₱<?= number_format($monthTotal, 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Savings</div>
                <div class="stat-value">₱<?= number_format($totalSavings, 2) ?></div>
            </div>
        </div>

        <div class="card">
            <h2>Today's Budget</h2>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Spent</th>
                            <th>Limit</th>
                            <th>Remaining</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="category-food">Food</td>
                            <td>₱<?= number_format($foodToday, 2) ?></td>
                            <td>₱<?= number_format($foodLimit, 2) ?></td>
                            <td>₱<?= number_format(max(0, $foodLimit - $foodToday), 2) ?></td>
                            <td>
                                <span class="status <?= $overFood ? 'status-exceeded' : 'status-ok' ?>">
                                    <?= $overFood ? 'Exceeded' : 'OK' ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="category-transpo">Transportation</td>
                            <td>₱<?= number_format($transpoToday, 2) ?></td>
                            <td>₱<?= number_format($transpoLimit, 2) ?></td>
                            <td>₱<?= number_format(max(0, $transpoLimit - $transpoToday), 2) ?></td>
                            <td>
                                <span class="status <?= $overTranspo ? 'status-exceeded' : 'status-ok' ?>">
                                    <?= $overTranspo ? 'Exceeded' : 'OK' ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Savings Goal: <?= htmlspecialchars($goalName) ?></h2>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $progress ?>%;">
                    <span class="progress-text"><?= $progress ?>%</span>
                </div>
            </div>
            <div class="goal-details">
                <div class="goal-item">
                    <span class="goal-label">Saved</span>
                    <span class="goal-value">₱<?= number_format($totalSavings, 2) ?></span>
                </div>
                <div class="goal-item">
                    <span class="goal-label">Target</span>
                    <span class="goal-value">₱<?= number_format($goalTarget, 2) ?></span>
                </div>
                <div class="goal-item">
                    <span class="goal-label">Remaining</span>
                    <span class="goal-value">₱<?= number_format($remainingGoal, 2) ?></span>
                </div>
                <?php if ($goalDeadline): ?>
                <div class="goal-item">
                    <span class="goal-label">Deadline</span>
                    <span class="goal-value"><?= htmlspecialchars($goalDeadline) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2>Alerts</h2>
            <div class="alerts">
                <?php if ($overFood): ?>
                    <div class="alert alert-warning">
                        You exceeded your food limit today (₱<?= number_format($foodToday, 2) ?> / ₱<?= number_format($foodLimit, 2) ?>)
                    </div>
                <?php endif; ?>
                <?php if ($overTranspo): ?>
                    <div class="alert alert-warning">
                        You exceeded your transportation limit today (₱<?= number_format($transpoToday, 2) ?> / ₱<?= number_format($transpoLimit, 2) ?>)
                    </div>
                <?php endif; ?>
                <?php if (!$overFood && !$overTranspo && $totalSavings < $goalTarget): ?>
                    <div class="alert alert-info">
                        Keep saving towards your goal "<?= htmlspecialchars($goalName) ?>"
                    </div>
                <?php endif; ?>
                <?php if (!$overFood && !$overTranspo && $totalSavings >= $goalTarget): ?>
                    <div class="alert alert-success">
                        You've reached your savings goal!
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2>Quick Actions</h2>
            <div class="action-grid">
                <a href="expense-tracker.php" class="btn btn-primary">Add Expense</a>
                <a href="saving-goal.php" class="btn btn-secondary">Add Savings</a>
                <a href="budget-limits.php" class="btn btn-secondary">Set Limits</a>
            </div>
        </div>
    </div>
</body>
</html>