<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$err = isset($_GET['err']) ? $_GET['err'] : '';

$today = date('Y-m-d');

// Update goal
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

// Add savings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_savings') {
    $sAmount = floatval($_POST['savings_amount'] ?? 0);
    $sNote = trim($_POST['savings_note'] ?? '');
    $sDate = $_POST['savings_date'] ?? $today;

    if ($sAmount > 0) {
        $ins = $conn->prepare("INSERT INTO savings (user_id, amount, note, savings_date) VALUES (?, ?, ?, ?)");
        $ins->bind_param("idss", $userId, $sAmount, $sNote, $sDate);
        if ($ins->execute()) {
            $msg = "Savings added! Your progress has been updated.";
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
    <title>BudgetBuddy - Saving Goal</title>
</head>
<body>
    <h1>SAVING GOAL</h1>
    <?php include __DIR__ . '/nav.php'; ?>

    <?php if ($msg): ?><p style="color:green;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <?php if ($err): ?><p style="color:red;"><?= htmlspecialchars($err) ?></p><?php endif; ?>

    <h2>Current Goal: <?= htmlspecialchars($goalName) ?></h2>

    <div style="width: 380px; height: 26px; border: 1px solid #000; background:#f0f0f0; margin: 8px 0;">
        <div style="width: <?= $progress ?>%; height: 100%; background: #4caf50; color: white; text-align: center; font-size: 14px; line-height: 26px;">
            <?= $progress ?>% complete
        </div>
    </div>

    <p>
        <strong>Saved so far:</strong> ₱ <?= number_format($totalSavings, 2) ?><br>
        <strong>Target:</strong> ₱ <?= number_format($goalTarget, 2) ?><br>
        <strong>Remaining to goal:</strong> ₱ <?= number_format($remaining, 2) ?>
        <?php if ($goalDeadline): ?><br><strong>Deadline:</strong> <?= htmlspecialchars($goalDeadline) ?><?php endif; ?>
    </p>

    <h3>Update Your Goal</h3>
    <form method="POST">
        <input type="hidden" name="action" value="update_goal">

        <label>Goal name:</label>
        <input type="text" name="goal_name" value="<?= htmlspecialchars($goalName) ?>" style="width:220px;" required><br><br>

        <label>Target amount (₱):</label>
        <input type="number" step="0.01" name="goal_target" value="<?= htmlspecialchars($goalTarget) ?>" required style="width:140px;"><br><br>

        <label>Deadline (optional):</label>
        <input type="date" name="goal_deadline" value="<?= htmlspecialchars($goalDeadline ?? '') ?>">

        <button type="submit">Update Goal</button>
    </form>

    <hr>

    <h2>Add Savings (contributes to your goal)</h2>
    <form method="POST">
        <input type="hidden" name="action" value="add_savings">

        <label>Amount:</label> <input type="number" step="0.01" name="savings_amount" required style="width:110px;">
        <label>Date:</label> <input type="date" name="savings_date" value="<?= $today ?>">
        <label>Note:</label> <input type="text" name="savings_note" style="width:200px;" placeholder="e.g. from allowance">

        <button type="submit">Add to Savings</button>
    </form>

    <h4>Recent Savings Contributions</h4>
    <table border="1" style="width:auto;">
        <tr><th>Date</th><th>Amount</th><th>Note</th></tr>
        <?php if (count($recentSavings) === 0): ?>
            <tr><td colspan="3">No savings recorded yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($recentSavings as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['savings_date']) ?></td>
                <td>₱ <?= number_format($s['amount'], 2) ?></td>
                <td><?= htmlspecialchars($s['note']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <p><small>Frontend page stored in one folder (with PHP + backend logic).</small></p>
</body>
</html>