<?php
session_start();
include("config/db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';

// Handle POST actions (add, delete, update profile)
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_expense') {
        $amount = floatval($_POST['amount'] ?? 0);
        $category = $_POST['category'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');

        if ($amount > 0 && in_array($category, ['food', 'transpo'])) {
            $stmt = $conn->prepare("INSERT INTO expenses (user_id, amount, category, description, expense_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("idsss", $userId, $amount, $category, $description, $expenseDate);
            if ($stmt->execute()) {
                $msg = "Expense added.";
            } else {
                $err = "Failed to add expense.";
            }
            $stmt->close();
        } else {
            $err = "Invalid expense data.";
        }
    }

    if ($action === 'add_savings') {
        $amount = floatval($_POST['savings_amount'] ?? 0);
        $note = trim($_POST['savings_note'] ?? '');
        $sDate = $_POST['savings_date'] ?? date('Y-m-d');

        if ($amount > 0) {
            $stmt = $conn->prepare("INSERT INTO savings (user_id, amount, note, savings_date) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("idss", $userId, $amount, $note, $sDate);
            if ($stmt->execute()) {
                $msg = "Savings added.";
            } else {
                $err = "Failed to add savings.";
            }
            $stmt->close();
        } else {
            $err = "Invalid savings amount.";
        }
    }

    if ($action === 'update_profile') {
        $budget = floatval($_POST['monthly_budget'] ?? 0);
        $goal = floatval($_POST['savings_goal'] ?? 0);
        if ($budget >= 0 && $goal >= 0) {
            $stmt = $conn->prepare("UPDATE users SET monthly_budget = ?, savings_goal = ? WHERE id = ?");
            $stmt->bind_param("ddi", $budget, $goal, $userId);
            $stmt->execute();
            $stmt->close();
            $msg = "Budget and goal updated.";
        }
    }

    if ($action === 'delete_expense') {
        $expId = intval($_POST['expense_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $expId, $userId);
        $stmt->execute();
        $stmt->close();
        $msg = "Expense deleted.";
    }

    // Refresh by redirect to avoid resubmit
    header("Location: dashboard.php?msg=" . urlencode($msg) . "&err=" . urlencode($err));
    exit;
}

// Get flash msgs
if (isset($_GET['msg'])) $msg = $_GET['msg'];
if (isset($_GET['err']) && $_GET['err']) $err = $_GET['err'];

// Get user profile (budget/goal)
$stmt = $conn->prepare("SELECT monthly_budget, savings_goal FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$monthlyBudget = $user['monthly_budget'] ?? 0;
$savingsGoal = $user['savings_goal'] ?? 0;

// Current month range
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

// Queries for dashboard summaries (current month)
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?");
$stmt->bind_param("iss", $userId, $monthStart, $monthEnd);
$stmt->execute();
$monthExpensesRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$monthExpensesTotal = $monthExpensesRow['total'] ?? 0;

$stmt = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ? AND category='food'");
$stmt->bind_param("iss", $userId, $monthStart, $monthEnd);
$stmt->execute();
$foodRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$monthFood = $foodRow['total'] ?? 0;

$stmt = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ? AND category='transpo'");
$stmt->bind_param("iss", $userId, $monthStart, $monthEnd);
$stmt->execute();
$transpoRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$monthTranspo = $transpoRow['total'] ?? 0;

$remainingBudget = max(0, $monthlyBudget - $monthExpensesTotal);

// Total savings (all time)
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM savings WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$savingsRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$totalSavings = $savingsRow['total'] ?? 0;

// Load ALL expenses for history (client side filter)
$stmt = $conn->prepare("SELECT id, amount, category, description, expense_date FROM expenses WHERE user_id = ? ORDER BY expense_date DESC, id DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$allExpenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Load recent savings for display (optional)
$stmt = $conn->prepare("SELECT amount, note, savings_date FROM savings WHERE user_id = ? ORDER BY savings_date DESC LIMIT 5");
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentSavings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// For notifications logic
$today = date('Y-m-d');
$hasLoggedToday = false;
foreach ($allExpenses as $e) {
    if ($e['expense_date'] === $today) {
        $hasLoggedToday = true;
        break;
    }
}
$budgetUsedPercent = ($monthlyBudget > 0) ? ($monthExpensesTotal / $monthlyBudget * 100) : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>BudgetBuddy - Dashboard</title>
    <style>
        /* Minimal inline-ish styles only for functionality - NO design */
        body { font-family: sans-serif; margin: 20px; }
        h1, h2, h3 { margin-top: 30px; }
        section { margin-bottom: 40px; border: 1px solid #ccc; padding: 15px; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; }
        form { margin: 10px 0; }
        label { display: inline-block; min-width: 120px; }
        .summary { display: inline-block; margin-right: 30px; padding: 10px; border: 1px solid #aaa; }
        .filter-bar { margin: 10px 0; }
        .alert { background: #fff3cd; padding: 8px; margin: 5px 0; border: 1px solid #ccc; }
        .progress-outer { width: 300px; height: 20px; border: 1px solid #000; display: inline-block; vertical-align: middle; }
        .progress-inner { height: 100%; background: #4caf50; text-align: center; color: white; font-size: 12px; line-height: 20px; }
        canvas { border: 1px solid #ccc; }
    </style>
</head>
<body>
    <h1>BudgetBuddy</h1>
    <p>Welcome, <?= htmlspecialchars($userName) ?> (<?= htmlspecialchars($userEmail) ?>) | <a href="?logout=1">Logout</a></p>

    <?php if ($msg): ?><p style="color:green;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <?php if ($err): ?><p style="color:red;"><?= htmlspecialchars($err) ?></p><?php endif; ?>

    <?php
    // Handle logout inline
    if (isset($_GET['logout'])) {
        session_destroy();
        header("Location: auth/login.php");
        exit;
    }
    ?>

    <!-- DASHBOARD OVERVIEW -->
    <section id="dashboard">
        <h2>DASHBOARD OVERVIEW</h2>

        <div class="summary">
            <strong>Total Expenses (this month)</strong><br>
            ₱ <?= number_format($monthExpensesTotal, 2) ?>
        </div>

        <div class="summary">
            <strong>Total Savings</strong><br>
            ₱ <?= number_format($totalSavings, 2) ?>
        </div>

        <div class="summary">
            <strong>Remaining Budget (this month)</strong><br>
            ₱ <?= number_format($remainingBudget, 2) ?> / ₱ <?= number_format($monthlyBudget, 2) ?>
        </div>

        <h3>Visuals</h3>

        <div style="display:inline-block; vertical-align:top; margin-right:40px;">
            <strong>Pie Chart: Food vs Transpo (this month)</strong><br>
            <canvas id="pieChart" width="220" height="220"></canvas>
            <div style="margin-top:5px; font-size:12px;">
                Food: ₱<?= number_format($monthFood,2) ?> &nbsp;&nbsp;
                Transpo: ₱<?= number_format($monthTranspo,2) ?>
            </div>
        </div>

        <div style="display:inline-block; vertical-align:top;">
            <strong>Progress Bar: Savings Goal</strong><br><br>
            <?php
                $progress = ($savingsGoal > 0) ? min(100, round($totalSavings / $savingsGoal * 100)) : 0;
            ?>
            <div class="progress-outer">
                <div class="progress-inner" style="width: <?= $progress ?>%;"><?= $progress ?>%</div>
            </div>
            <div style="font-size:12px; margin-top:3px;">
                ₱<?= number_format($totalSavings,2) ?> / ₱<?= number_format($savingsGoal,2) ?>
            </div>
        </div>
    </section>

    <!-- EXPENSE HISTORY -->
    <section id="history">
        <h2>EXPENSE HISTORY</h2>

        <h3>Add New Expense</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_expense">
            <label>Amount:</label>
            <input type="number" step="0.01" name="amount" required style="width:100px;">
            <label>Category:</label>
            <select name="category" required>
                <option value="food">Food</option>
                <option value="transpo">Transpo</option>
            </select>
            <label>Date:</label>
            <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
            <br><br>
            <label>Description (optional):</label>
            <input type="text" name="description" style="width:300px;">
            <button type="submit">Add Expense</button>
        </form>

        <h3>View Past Expenses</h3>
        <div class="filter-bar">
            <label>By Period:</label>
            <select id="periodFilter">
                <option value="all">All</option>
                <option value="day">Day (Today)</option>
                <option value="week">Week</option>
                <option value="month" selected>This Month</option>
            </select>

            <label>Category:</label>
            <select id="categoryFilter">
                <option value="all">All</option>
                <option value="food">Food</option>
                <option value="transpo">Transpo</option>
            </select>

            <button onclick="applyFilters()">Apply Filter</button>
            <button onclick="resetFilters()">Reset</button>
        </div>

        <table id="expensesTable" border="1">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="expensesBody">
                <!-- Populated by JS -->
            </tbody>
        </table>
        <p id="noResults" style="display:none;">No expenses match the filters.</p>
    </section>

    <!-- NOTIF / REMINDERS -->
    <section id="notifications">
        <h2>NOTIF / REMINDERS</h2>

        <?php if (!$hasLoggedToday): ?>
            <div class="alert">Reminder: Log your daily expense.</div>
        <?php endif; ?>

        <?php if ($totalSavings < $savingsGoal): ?>
            <div class="alert">Reminder: Save money for your goal.</div>
        <?php endif; ?>

        <?php if ($budgetUsedPercent >= 80): ?>
            <div class="alert">Alert: Budget is almost used (<?= round($budgetUsedPercent) ?>%).</div>
        <?php endif; ?>

        <?php if ($hasLoggedToday && $totalSavings >= $savingsGoal && $budgetUsedPercent < 80): ?>
            <div class="alert">All good! No reminders right now.</div>
        <?php endif; ?>

        <p><small>Reminders update automatically based on your data.</small></p>
    </section>

    <!-- ADD SAVINGS + SETTINGS (support for goal/budget) -->
    <section id="savings">
        <h2>SAVINGS &amp; SETTINGS</h2>

        <h3>Add to Savings (for your goal)</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_savings">
            <label>Amount:</label> <input type="number" step="0.01" name="savings_amount" required style="width:100px;">
            <label>Date:</label> <input type="date" name="savings_date" value="<?= date('Y-m-d') ?>">
            <label>Note:</label> <input type="text" name="savings_note" style="width:200px;">
            <button type="submit">Add Savings</button>
        </form>

        <h4>Recent Savings</h4>
        <table border="1" style="width:auto;">
            <tr><th>Date</th><th>Amount</th><th>Note</th></tr>
            <?php foreach ($recentSavings as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['savings_date']) ?></td>
                    <td>₱<?= number_format($s['amount'],2) ?></td>
                    <td><?= htmlspecialchars($s['note']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($recentSavings)): ?>
                <tr><td colspan="3">No savings yet.</td></tr>
            <?php endif; ?>
        </table>

        <h3>Update Budget &amp; Goal</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <label>Monthly Budget:</label>
            <input type="number" step="0.01" name="monthly_budget" value="<?= htmlspecialchars($monthlyBudget) ?>" required>
            <label>Savings Goal:</label>
            <input type="number" step="0.01" name="savings_goal" value="<?= htmlspecialchars($savingsGoal) ?>" required>
            <button type="submit">Update</button>
        </form>
    </section>

    <p><a href="auth/signup.php">Create another account</a> | <a href="sql/schema.sql" target="_blank">View SQL schema</a></p>

    <script>
        // Embed expenses data from PHP (no design, functional)
        const allExpenses = <?= json_encode($allExpenses) ?>;

        function formatDate(d) {
            return d;
        }

        function renderTable(data) {
            const tbody = document.getElementById('expensesBody');
            tbody.innerHTML = '';
            const noRes = document.getElementById('noResults');

            if (data.length === 0) {
                noRes.style.display = 'block';
                return;
            }
            noRes.style.display = 'none';

            data.forEach(function(exp) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${exp.expense_date}</td>
                    <td>${exp.category}</td>
                    <td>${exp.description ? exp.description : ''}</td>
                    <td>₱${parseFloat(exp.amount).toFixed(2)}</td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this expense?');">
                            <input type="hidden" name="action" value="delete_expense">
                            <input type="hidden" name="expense_id" value="${exp.id}">
                            <button type="submit" style="font-size:10px;">Delete</button>
                        </form>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function applyFilters() {
            const period = document.getElementById('periodFilter').value;
            const cat = document.getElementById('categoryFilter').value;

            let filtered = allExpenses.slice();

            // Period filter (client side)
            const today = new Date();
            const todayStr = today.toISOString().slice(0,10);

            if (period === 'day') {
                filtered = filtered.filter(e => e.expense_date === todayStr);
            } else if (period === 'week') {
                const weekAgo = new Date(today.getTime() - 7*24*60*60*1000);
                const weekAgoStr = weekAgo.toISOString().slice(0,10);
                filtered = filtered.filter(e => e.expense_date >= weekAgoStr);
            } else if (period === 'month') {
                const monthStart = todayStr.slice(0,7) + '-01';
                filtered = filtered.filter(e => e.expense_date >= monthStart);
            }
            // all = no change

            if (cat !== 'all') {
                filtered = filtered.filter(e => e.category === cat);
            }

            renderTable(filtered);
        }

        function resetFilters() {
            document.getElementById('periodFilter').value = 'all';
            document.getElementById('categoryFilter').value = 'all';
            renderTable(allExpenses);
        }

        // Initial render of full history table
        function initHistory() {
            // Default to "this month" filter on load to match dashboard
            document.getElementById('periodFilter').value = 'month';
            document.getElementById('categoryFilter').value = 'all';
            applyFilters();
        }

        // Simple canvas pie chart (Food vs Transpo this month) - no external libs
        function drawPieChart(food, transpo) {
            const canvas = document.getElementById('pieChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const w = canvas.width;
            const h = canvas.height;
            const cx = w / 2;
            const cy = h / 2;
            const r = Math.min(w, h) / 2 - 10;

            ctx.clearRect(0, 0, w, h);

            const total = food + transpo;
            if (total <= 0) {
                // Empty state
                ctx.beginPath();
                ctx.arc(cx, cy, r, 0, Math.PI * 2);
                ctx.strokeStyle = '#ccc';
                ctx.lineWidth = 2;
                ctx.stroke();
                ctx.fillStyle = '#666';
                ctx.font = '14px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('No data', cx, cy + 5);
                return;
            }

            let startAngle = -Math.PI / 2;
            const foodAngle = (food / total) * (Math.PI * 2);
            const transpoAngle = (transpo / total) * (Math.PI * 2);

            // Food slice (blue-ish)
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, r, startAngle, startAngle + foodAngle);
            ctx.closePath();
            ctx.fillStyle = '#4488ff';
            ctx.fill();
            ctx.strokeStyle = '#000';
            ctx.stroke();

            // Transpo slice (orange-ish)
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, r, startAngle + foodAngle, startAngle + foodAngle + transpoAngle);
            ctx.closePath();
            ctx.fillStyle = '#ffaa33';
            ctx.fill();
            ctx.stroke();

            // Legend dots
            ctx.fillStyle = '#4488ff';
            ctx.fillRect(10, h - 30, 12, 12);
            ctx.fillStyle = '#000';
            ctx.fillText('Food', 28, h - 20);

            ctx.fillStyle = '#ffaa33';
            ctx.fillRect(80, h - 30, 12, 12);
            ctx.fillStyle = '#000';
            ctx.fillText('Transpo', 98, h - 20);
        }

        // Boot
        window.onload = function() {
            initHistory();

            // Draw pie with server computed month values
            const food = <?= json_encode($monthFood) ?>;
            const transpo = <?= json_encode($monthTranspo) ?>;
            drawPieChart(parseFloat(food) || 0, parseFloat(transpo) || 0);

            // Allow pressing enter in filters? but buttons are there
        };

        // Bonus: allow re-apply on select change (convenience, no design needed)
        // (optional enhancement)
    </script>
</body>
</html>