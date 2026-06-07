<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
setcookie('theme', $theme, time() + (86400 * 365), '/');

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$err = isset($_GET['err']) ? $_GET['err'] : '';

$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_limits') {
    $foodLim = max(0, floatval($_POST['food_daily_limit'] ?? 0));
    $transLim = max(0, floatval($_POST['transpo_daily_limit'] ?? 0));

    $upd = $conn->prepare("UPDATE users SET food_daily_limit = ?, transpo_daily_limit = ? WHERE id = ?");
    $upd->bind_param("ddi", $foodLim, $transLim, $userId);
    if ($upd->execute()) {
        $msg = "Budget limits updated successfully.";
        $currentUser['food_daily_limit'] = $foodLim;
        $currentUser['transpo_daily_limit'] = $transLim;
    } else {
        $err = "Failed to update limits.";
    }
    $upd->close();
    header("Location: budget-limits.php?msg=" . urlencode($msg) . "&err=" . urlencode($err));
    exit;
}

$checkDate = $today;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check_date') {
    $checkDate = $_POST['check_date'] ?? $today;
}

$foodLimit = (float)($currentUser['food_daily_limit'] ?? 150);
$transpoLimit = (float)($currentUser['transpo_daily_limit'] ?? 100);

$foodSpent = get_daily_spent($conn, $userId, $checkDate, 'food');
$transpoSpent = get_daily_spent($conn, $userId, $checkDate, 'transpo');

$overFood = $foodSpent > $foodLimit;
$overTrans = $transpoSpent > $transpoLimit;
?>
<!DOCTYPE html>
<html data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BudgetBuddy - Budget Limits</title>
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
                <p>Manage Your Budget</p>
            </div>
        </div>
        <div class="header-right">
            <button class="theme-toggle" onclick="toggleTheme()">Dark Mode</button>
        </div>
    </header>

    <div style="padding: 0 2rem;">
        <nav class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="expense-tracker.php">Expenses</a>
            <a href="budget-limits.php" class="active">Budget</a>
            <a href="saving-goal.php">Goals</a>
            <a href="logout.php" class="nav-logout">Logout</a>
        </nav>
    </div>

    <div class="container">
        <?php if ($msg): ?>
            <div class="alert alert-success">
                <strong>Success:</strong> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="alert alert-error">
                <strong>Error:</strong> <?= htmlspecialchars($err) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Set Your Daily Limits</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_limits">

                <div class="form-row">
                    <div class="form-group">
                        <label for="food_daily_limit">Food Daily Limit</label>
                        <input type="number" id="food_daily_limit" step="0.01" name="food_daily_limit" value="<?= htmlspecialchars($foodLimit) ?>" required>
                        <small>Recommended: 150 - 300</small>
                    </div>

                    <div class="form-group">
                        <label for="transpo_daily_limit">Transportation Daily Limit</label>
                        <input type="number" id="transpo_daily_limit" step="0.01" name="transpo_daily_limit" value="<?= htmlspecialchars($transpoLimit) ?>" required>
                        <small>Recommended: 100 - 200</small>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save Limits</button>
            </form>
        </div>

        <div class="card">
            <h2>Check Budget Usage</h2>

            <form method="POST" style="margin-bottom: 2rem;">
                <input type="hidden" name="action" value="check_date">
                <div style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
                        <label for="check_date">Select Date</label>
                        <input type="date" id="check_date" name="check_date" value="<?= htmlspecialchars($checkDate) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Check</button>
                </div>
            </form>

            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Daily Limit</th>
                            <th>Spent on <?= htmlspecialchars($checkDate) ?></th>
                            <th>Remaining</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="category-food">Food</td>
                            <td>₱<?= number_format($foodLimit, 2) ?></td>
                            <td>₱<?= number_format($foodSpent, 2) ?></td>
                            <td>
                                <?php if ($overFood): ?>
                                    <span style="font-weight: 600; color: var(--danger-red);">-₱<?= number_format($foodSpent - $foodLimit, 2) ?></span>
                                <?php else: ?>
                                    <span style="font-weight: 600; color: var(--success-green);">₱<?= number_format($foodLimit - $foodSpent, 2) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status <?= $overFood ? 'status-exceeded' : 'status-ok' ?>">
                                    <?= $overFood ? 'Exceeded' : 'OK' ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="category-transpo">Transportation</td>
                            <td>₱<?= number_format($transpoLimit, 2) ?></td>
                            <td>₱<?= number_format($transpoSpent, 2) ?></td>
                            <td>
                                <?php if ($overTrans): ?>
                                    <span style="font-weight: 600; color: var(--danger-red);">-₱<?= number_format($transpoSpent - $transpoLimit, 2) ?></span>
                                <?php else: ?>
                                    <span style="font-weight: 600; color: var(--success-green);">₱<?= number_format($transpoLimit - $transpoSpent, 2) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status <?= $overTrans ? 'status-exceeded' : 'status-ok' ?>">
                                    <?= $overTrans ? 'Exceeded' : 'OK' ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if ($overFood || $overTrans): ?>
                <div style="margin-top: 1.5rem; padding: 1rem; border-radius: 12px; background: #F8D7DA; border-left: 4px solid var(--danger-red);">
                    <strong style="color: var(--danger-red);">Alert</strong>
                    <p style="margin-top: 0.5rem; color: var(--text-dark);">You have exceeded your daily limit on <?= htmlspecialchars($checkDate) ?></p>
                </div>
            <?php else: ?>
                <div style="margin-top: 1.5rem; padding: 1rem; border-radius: 12px; background: #D4EDDA; border-left: 4px solid var(--success-green);">
                    <strong style="color: var(--success-green);">Good News</strong>
                    <p style="margin-top: 0.5rem; color: var(--text-dark);">You are within budget on <?= htmlspecialchars($checkDate) ?></p>
                </div>
            <?php endif; ?>
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