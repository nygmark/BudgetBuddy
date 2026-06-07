<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
setcookie('theme', $theme, time() + (86400 * 365), '/');
?>
<!DOCTYPE html>
<html data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BudgetBuddy - Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="header">
        <div class="header-left">
            <div class="logo-container">
                <img id="logoImg" src="logo.png" alt="Logo" onerror="this.style.display='none'; document.querySelector('.logo-placeholder').style.display='flex';">
                <div class="logo-placeholder" id="logoPlaceholder" style="display: none;">BB</div>
            </div>
            <div class="header-content">
                <h1>BudgetBuddy</h1>
                <p>Welcome back, <?= htmlspecialchars($userName) ?></p>
            </div>
        </div>
        <div class="header-right">
            <button class="theme-toggle" onclick="toggleTheme()">Dark Mode</button>
        </div>
    </header>

    <div style="padding: 0 2rem;">
        <nav class="nav">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="expense-tracker.php">Expenses</a>
            <a href="budget-limits.php">Budget</a>
            <a href="saving-goal.php">Goals</a>
            <a href="logout.php" class="nav-logout">Logout</a>
        </nav>
    </div>

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
                <div class="stat-label">Today's Spending</div>
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
            <h2>Today's Budget Status</h2>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Spent Today</th>
                            <th>Daily Limit</th>
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
                    <span class="goal-label">Saved So Far</span>
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
            <h2>Alerts & Notifications</h2>
            <div class="alerts">
                <?php if ($overFood): ?>
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> You exceeded your food limit today (₱<?= number_format($foodToday, 2) ?> / ₱<?= number_format($foodLimit, 2) ?>)
                    </div>
                <?php endif; ?>
                <?php if ($overTranspo): ?>
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> You exceeded your transportation limit today (₱<?= number_format($transpoToday, 2) ?> / ₱<?= number_format($transpoLimit, 2) ?>)
                    </div>
                <?php endif; ?>
                <?php if (!$overFood && !$overTranspo && $totalSavings < $goalTarget): ?>
                    <div class="alert alert-info">
                        <strong>Info:</strong> Keep saving towards your goal "<?= htmlspecialchars($goalName) ?>"
                    </div>
                <?php endif; ?>
                <?php if (!$overFood && !$overTranspo && $totalSavings >= $goalTarget): ?>
                    <div class="alert alert-success">
                        <strong>Success:</strong> You've reached your savings goal!
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2>Quick Actions</h2>
            <div class="action-grid">
                <a href="expense-tracker.php" class="btn btn-primary">Add Expense</a>
                <a href="saving-goal.php" class="btn btn-secondary">Add Savings</a>
                <a href="budget-limits.php" class="btn btn-secondary">Manage Budget</a>
            </div>
        </div>
    </div>

    <script>
    function toggleTheme() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        html.setAttribute('data-theme', newTheme);
        document.cookie = `theme=${newTheme}; path=/; max-age=31536000`;
        
        const btn = document.querySelector('.theme-toggle');
        btn.textContent = newTheme === 'dark' ? 'Light Mode' : 'Dark Mode';
    }

    window.addEventListener('load', function() {
        const theme = document.documentElement.getAttribute('data-theme') || 'light';
        const btn = document.querySelector('.theme-toggle');
        btn.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
    });
</script>
</body>
</html>