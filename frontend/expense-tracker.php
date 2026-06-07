<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$err = isset($_GET['err']) ? $_GET['err'] : '';

$today = date('Y-m-d');
$foodLimit = (float)($currentUser['food_daily_limit'] ?? 150);
$transpoLimit = (float)($currentUser['transpo_daily_limit'] ?? 100);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_expense') {
    $amount = floatval($_POST['amount'] ?? 0);
    $category = $_POST['category'] ?? '';
    $expenseDate = $_POST['expense_date'] ?? $today;
    $notes = trim($_POST['notes'] ?? '');

    if ($amount > 0 && in_array($category, ['food','transpo'])) {
        $stmt = $conn->prepare("INSERT INTO expenses (user_id, amount, category, description, expense_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("idsss", $userId, $amount, $category, $notes, $expenseDate);
        if ($stmt->execute()) {
            $spentAfter = get_daily_spent($conn, $userId, $expenseDate, $category);
            $limit = ($category === 'food') ? $foodLimit : $transpoLimit;
            $overMsg = '';
            if ($spentAfter > $limit) {
                $overMsg = " You exceeded your $category limit for $expenseDate.";
            }
            $msg = "Expense added successfully." . $overMsg;
        } else {
            $err = "Failed to add expense.";
        }
        $stmt->close();
    } else {
        $err = "Invalid expense data.";
    }
    header("Location: expense-tracker.php?msg=" . urlencode($msg) . "&err=" . urlencode($err));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_expense') {
    $eid = intval($_POST['expense_id'] ?? 0);
    $del = $conn->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
    $del->bind_param("ii", $eid, $userId);
    $del->execute();
    $del->close();
    header("Location: expense-tracker.php?msg=" . urlencode("Expense deleted."));
    exit;
}

$todayTotal = get_daily_spent($conn, $userId, $today);
$weekTotal  = get_weekly_spent($conn, $userId);
$monthTotal = get_monthly_spent($conn, $userId);

$todayFood = get_daily_spent($conn, $userId, $today, 'food');
$todayTrans = get_daily_spent($conn, $userId, $today, 'transpo');

$allExpenses = get_all_expenses($conn, $userId);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BudgetBuddy - Expenses</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>BudgetBuddy</h1>
            <p>Expense Tracker</p>
        </div>
        <nav class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="expense-tracker.php" class="active">Expenses</a>
            <a href="budget-limits.php">Budget</a>
            <a href="saving-goal.php">Goals</a>
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
            <h2>Add Expense</h2>
            <form method="POST" class="form-inline">
                <input type="hidden" name="action" value="add_expense">

                <div class="form-row">
                    <div class="form-group">
                        <label for="amount">Amount</label>
                        <input type="number" id="amount" step="0.01" name="amount" required placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" required>
                            <option value="">Select category</option>
                            <option value="food">Food</option>
                            <option value="transpo">Transportation</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="expense_date">Date</label>
                        <input type="date" id="expense_date" name="expense_date" value="<?= $today ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <input type="text" id="notes" name="notes" placeholder="Optional">
                </div>

                <button type="submit" class="btn btn-primary">Add Expense</button>
            </form>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Today</div>
                <div class="stat-value">₱<?= number_format($todayTotal, 2) ?></div>
                <small>Food: ₱<?= number_format($todayFood, 2) ?> | Transport: ₱<?= number_format($todayTrans, 2) ?></small>
            </div>
            <div class="stat-card">
                <div class="stat-label">This Week</div>
                <div class="stat-value">₱<?= number_format($weekTotal, 2) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">This Month</div>
                <div class="stat-value">₱<?= number_format($monthTotal, 2) ?></div>
            </div>
        </div>

        <div class="card">
            <h2>Expense History</h2>

            <div class="filter-bar">
                <select id="periodFilter">
                    <option value="all">All Time</option>
                    <option value="day" selected>Today</option>
                    <option value="week">Last 7 Days</option>
                    <option value="month">This Month</option>
                </select>

                <select id="categoryFilter">
                    <option value="all">All Categories</option>
                    <option value="food">Food</option>
                    <option value="transpo">Transportation</option>
                </select>

                <button onclick="applyFilters()" class="btn btn-primary">Apply</button>
                <button onclick="resetFilters()" class="btn btn-secondary">Reset</button>
            </div>

            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="expensesBody"></tbody>
                </table>
            </div>
            <p id="noResults" style="display:none; text-align: center; padding: 2rem; color: var(--text-secondary);">
                No expenses found.
            </p>
        </div>
    </div>

    <script>
        const allExpenses = <?= json_encode($allExpenses) ?>;

        function renderTable(data) {
            const tbody = document.getElementById('expensesBody');
            tbody.innerHTML = '';
            const noResults = document.getElementById('noResults');

            if (data.length === 0) {
                noResults.style.display = 'block';
                return;
            }

            noResults.style.display = 'none';

            data.forEach(function(exp) {
                const categoryClass = exp.category === 'food' ? 'category-food' : 'category-transpo';
                const categoryLabel = exp.category === 'food' ? 'Food' : 'Transportation';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${exp.expense_date}</td>
                    <td class="${categoryClass}">${categoryLabel}</td>
                    <td>${exp.description || '-'}</td>
                    <td>₱${parseFloat(exp.amount).toFixed(2)}</td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this expense?');">
                            <input type="hidden" name="action" value="delete_expense">
                            <input type="hidden" name="expense_id" value="${exp.id}">
                            <button type="submit" class="btn btn-small btn-danger">Delete</button>
                        </form>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function applyFilters() {
            const period = document.getElementById('periodFilter').value;
            const category = document.getElementById('categoryFilter').value;
            let filtered = allExpenses.slice();

            const today = new Date().toISOString().slice(0, 10);

            if (period === 'day') {
                filtered = filtered.filter(e => e.expense_date === today);
            } else if (period === 'week') {
                const weekAgo = new Date(new Date().getTime() - 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
                filtered = filtered.filter(e => e.expense_date >= weekAgo);
            } else if (period === 'month') {
                const monthStart = today.slice(0, 7) + '-01';
                filtered = filtered.filter(e => e.expense_date >= monthStart);
            }

            if (category !== 'all') {
                filtered = filtered.filter(e => e.category === category);
            }

            renderTable(filtered);
        }

        function resetFilters() {
            document.getElementById('periodFilter').value = 'day';
            document.getElementById('categoryFilter').value = 'all';
            applyFilters();
        }

        window.addEventListener('load', applyFilters);
    </script>
</body>
</html>