<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$err = isset($_GET['err']) ? $_GET['err'] : '';

$today = date('Y-m-d');
$foodLimit = (float)($currentUser['food_daily_limit'] ?? 150);
$transpoLimit = (float)($currentUser['transpo_daily_limit'] ?? 100);

// Handle add expense
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
                $overMsg = " (ALERT: This made you exceed your daily $category limit of ₱" . number_format($limit,2) . " for $expenseDate)";
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

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_expense') {
    $eid = intval($_POST['expense_id'] ?? 0);
    $del = $conn->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
    $del->bind_param("ii", $eid, $userId);
    $del->execute();
    $del->close();
    header("Location: expense-tracker.php?msg=" . urlencode("Expense deleted."));
    exit;
}

// Current aggregates
$todayTotal = get_daily_spent($conn, $userId, $today);
$weekTotal  = get_weekly_spent($conn, $userId);
$monthTotal = get_monthly_spent($conn, $userId);

$todayFood = get_daily_spent($conn, $userId, $today, 'food');
$todayTrans = get_daily_spent($conn, $userId, $today, 'transpo');

// All expenses for client-side filtering
$allExpenses = get_all_expenses($conn, $userId);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>BudgetBuddy - Expense Tracker</title>
</head>
<body>
    <h1>EXPENSE TRACKER (DAILY SPENDING)</h1>
    <?php include __DIR__ . '/nav.php'; ?>

    <?php if ($msg): ?><p style="color:green;"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <?php if ($err): ?><p style="color:red;"><?= htmlspecialchars($err) ?></p><?php endif; ?>

    <!-- ADD EXPENSE -->
    <h2>Add Expense</h2>
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
        <input type="date" name="expense_date" value="<?= $today ?>" required>

        <br><br>
        <label>Notes (optional):</label>
        <input type="text" name="notes" style="width:300px;" placeholder="e.g. Lunch with friends">

        <button type="submit">Add Expense</button>
    </form>

    <!-- TOTALS -->
    <h2>Expense Totals</h2>
    <table border="1" style="width:auto; margin-bottom:15px;">
        <tr>
            <th>Period</th>
            <th>Total Spent</th>
            <th>Breakdown (today)</th>
        </tr>
        <tr>
            <td><strong>Daily (Today)</strong></td>
            <td>₱ <?= number_format($todayTotal, 2) ?></td>
            <td>Food: ₱ <?= number_format($todayFood,2) ?> &nbsp; Transpo: ₱ <?= number_format($todayTrans,2) ?></td>
        </tr>
        <tr>
            <td><strong>Weekly (last 7 days)</strong></td>
            <td>₱ <?= number_format($weekTotal, 2) ?></td>
            <td>—</td>
        </tr>
        <tr>
            <td><strong>Monthly (this month)</strong></td>
            <td>₱ <?= number_format($monthTotal, 2) ?></td>
            <td>—</td>
        </tr>
    </table>

    <!-- FILTERED HISTORY -->
    <h2>Your Expenses (filter by Day/Week/Month + Category)</h2>

    <div class="filter-bar">
        <label>Period:</label>
        <select id="periodFilter">
            <option value="all">All</option>
            <option value="day" selected>Day (Today)</option>
            <option value="week">Week</option>
            <option value="month">Month</option>
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

    <table id="expensesTable" border="1" style="width:100%; max-width:900px;">
        <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Notes</th>
                <th>Amount</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="expensesBody"></tbody>
    </table>
    <p id="noResults" style="display:none;">No expenses match the filters.</p>

    <script>
        const allExpenses = <?= json_encode($allExpenses) ?>;

        function renderTable(data) {
            const tbody = document.getElementById('expensesBody');
            tbody.innerHTML = '';
            const no = document.getElementById('noResults');
            if (data.length === 0) { no.style.display = 'block'; return; }
            no.style.display = 'none';

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

            const d = new Date();
            const todayStr = d.toISOString().slice(0,10);

            if (period === 'day') {
                filtered = filtered.filter(e => e.expense_date === todayStr);
            } else if (period === 'week') {
                const weekAgo = new Date(d.getTime() - 7*24*60*60*1000).toISOString().slice(0,10);
                filtered = filtered.filter(e => e.expense_date >= weekAgo);
            } else if (period === 'month') {
                const monthStart = todayStr.slice(0,7) + '-01';
                filtered = filtered.filter(e => e.expense_date >= monthStart);
            }

            if (cat !== 'all') {
                filtered = filtered.filter(e => e.category === cat);
            }
            renderTable(filtered);
        }

        function resetFilters() {
            document.getElementById('periodFilter').value = 'day';
            document.getElementById('categoryFilter').value = 'all';
            applyFilters();
        }

        window.onload = function() {
            document.getElementById('periodFilter').value = 'day';
            document.getElementById('categoryFilter').value = 'all';
            applyFilters();
        };
    </script>

    <p><small>Frontend page with PHP backend mixed (as requested).</small></p>
</body>
</html>