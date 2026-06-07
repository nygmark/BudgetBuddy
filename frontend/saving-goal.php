<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$err = isset($_GET['err']) ? $_GET['err'] : '';

$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_goal') {
    $gName = trim($_POST['goal_name'] ?? 'My Savings Goal');
    $gTarget = max(0, floatval($_POST['goal_target'] ?? 0));
    $gDeadline = $_POST['goal_deadline'] ?? null;
    if ($gDeadline === '') $gDeadline = null;

    $upd = $conn->prepare("UPDATE users SET goal_name = ?, goal_target = ?, goal_deadline = ? WHERE id = ?");
    $upd->bind_param("sdsi", $gName, $gTarget, $gDeadline, $userId);
    if ($upd->execute()) {
        $msg = "Saving goal updated.";
        $currentUser['goal_name'] = $gName;
        $currentUser['goal_target'] = $gTarget;
        $currentUser['goal_deadline'] = $gDeadline;
    } else {
        $err = "Failed to update goal.";
    }
    $upd->close();
    header("Location: saving-goal.php?msg=" . urlencode($msg) . "&err=" . urlencode($err));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_savings') {
    $sAmount = floatval($_POST['savings_amount'] ?? 0);
    $sNote = trim($_POST['savings_note'] ?? '');
    $sDate = $_POST['savings_date'] ?? $today;

    if ($sAmount > 0) {
        $ins = $conn->prepare("INSERT INTO savings (user_id, amount, note, savings_date) VALUES (?, ?, ?, ?)");
        $ins->bind_param("idss", $userId, $sAmount, $sNote, $sDate);
        if ($ins->execute()) {
            $msg = "Savings added. Your progress has been updated.";
        } else {
            $err = "Could not add savings.";
        }
        $ins->close();
    } else {
        $err = "Invalid savings amount.";
    }
    header("Location: saving-goal.php?msg=" . urlencode($msg) . "&err=" . urlencode($err));
    exit;
}

$goalName     = $currentUser['goal_name'] ?? 'My Savings Goal';
$goalTarget   = (float)($currentUser['goal_target'] ?? 10000);
$goalDeadline = $currentUser['goal_deadline'] ?? null;

$totalSavings = get_total_savings($conn, $userId);
$progress     = ($goalTarget > 0) ? min(100, round($totalSavings / $goalTarget * 100)) : 0;
$remaining    = max(0, $goalTarget - $totalSavings);

$recentSavings = get_recent_savings($conn, $userId, 10);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BudgetBuddy - Saving Goal</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>BudgetBuddy</h1>
            <p>Saving Goal</p>
        </div>
        <nav class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="expense-tracker.php">Expenses</a>
            <a href="budget-limits.php">Budget</a>
            <a href="saving-goal.php" class="active">Goals</a>
            <a href="../logout.php" class="nav-logout">Logout</a>
        </nav>
    </header>

    <div class="container">
        <?php if ($msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Current Goal: <?= htmlspecialchars($goalName) ?></h2>

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
                    <span class="goal-value">₱<?= number_format($remaining, 2) ?></span>
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
            <h2>Update Goal</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_goal">

                <div class="form-group">
                    <label for="goal_name">Goal Name</label>
                    <input type="text" id="goal_name" name="goal_name" value="<?= htmlspecialchars($goalName) ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="goal_target">Target Amount</label>
                        <input type="number" id="goal_target" step="0.01" name="goal_target" value="<?= htmlspecialchars($goalTarget) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="goal_deadline">Deadline</label>
                        <input type="date" id="goal_deadline" name="goal_deadline" value="<?= htmlspecialchars($goalDeadline ?? '') ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Update Goal</button>
            </form>
        </div>

        <div class="card">
            <h2>Add Savings</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_savings">

                <div class="form-row">
                    <div class="form-group">
                        <label for="savings_amount">Amount</label>
                        <input type="number" id="savings_amount" step="0.01" name="savings_amount" required placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label for="savings_date">Date</label>
                        <input type="date" id="savings_date" name="savings_date" value="<?= $today ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="savings_note">Note</label>
                    <input type="text" id="savings_note" name="savings_note" placeholder="Optional">
                </div>

                <button type="submit" class="btn btn-primary">Add to Savings</button>
            </form>
        </div>

        <div class="card">
            <h2>Recent Contributions</h2>

            <?php if (count($recentSavings) === 0): ?>
                <p style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                    No savings recorded yet. Start saving towards your goal!
                </p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSavings as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['savings_date']) ?></td>
                                <td><strong>₱<?= number_format($s['amount'], 2) ?></strong></td>
                                <td><?= htmlspecialchars($s['note'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>