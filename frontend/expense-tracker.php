<?php
  require_once __DIR__ . '/../includes/auth_check.php';
  require_once __DIR__ . '/../includes/helpers.php';

  $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
  setcookie('theme', $theme, time() + (86400 * 365), '/');

  $msg = isset($_GET['msg']) ? $_GET['msg'] : '';
  $err = isset($_GET['err']) ? $_GET['err'] : '';

  $today = date('Y-m-d');
  $foodLimit    = (float)($currentUser['food_daily_limit']   ?? 150);
  $transpoLimit = (float)($currentUser['transpo_daily_limit'] ?? 100);

  $validCategories = ['food','transpo','shopping','health','entertainment','utilities','education','others'];

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_expense') {
      $amount      = floatval($_POST['amount'] ?? 0);
      $category    = $_POST['category'] ?? '';
      $expenseDate = $_POST['expense_date'] ?? $today;
      $customCat   = trim($_POST['custom_category'] ?? '');
      $notes       = trim($_POST['notes'] ?? '');

      // For "others", require the custom label; store it as the description
      if ($category === 'others' && $customCat === '') {
          $err = 'Please describe the expense when using "Others".';
          header("Location: expense-tracker.php?err=" . urlencode($err));
          exit;
      }

      // Merge custom label into description when category is others
      if ($category === 'others') {
          $description = $customCat . ($notes !== '' ? ' - ' . $notes : '');
      } else {
          $description = $notes;
      }

      if ($amount > 0 && in_array($category, $validCategories)) {
          $stmt = $conn->prepare("INSERT INTO expenses (user_id, amount, category, description, expense_date) VALUES (?, ?, ?, ?, ?)");
          $stmt->bind_param("idsss", $userId, $amount, $category, $description, $expenseDate);
          if ($stmt->execute()) {
              $overMsg = '';
              if (in_array($category, ['food','transpo'])) {
                  $limit      = ($category === 'food') ? $foodLimit : $transpoLimit;
                  $spentAfter = get_daily_spent($conn, $userId, $expenseDate, $category);
                  if ($spentAfter > $limit) $overMsg = " You exceeded your $category limit for $expenseDate.";
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

  $todayTotal  = get_daily_spent($conn, $userId, $today);
  $weekTotal   = get_weekly_spent($conn, $userId);
  $monthTotal  = get_monthly_spent($conn, $userId);
  $todayFood   = get_daily_spent($conn, $userId, $today, 'food');
  $todayTrans  = get_daily_spent($conn, $userId, $today, 'transpo');
  $allExpenses = get_all_expenses($conn, $userId);
  ?>
  <!DOCTYPE html>
  <html data-theme="<?= $theme ?>">
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>BudgetBuddy - Expense Tracker</title>
      <link rel="stylesheet" href="styles.css">
      <style>
          .category-shopping     { color: #9B59B6; font-weight: 600; }
          .category-health       { color: #E74C3C; font-weight: 600; }
          .category-entertainment{ color: #E67E22; font-weight: 600; }
          .category-utilities    { color: #2980B9; font-weight: 600; }
          .category-education    { color: #16A085; font-weight: 600; }
          .category-others       { color: var(--text-secondary); font-weight: 600; }
          #custom_category_wrap  { display: none; margin-top: 0.75rem; }
      </style>
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
                  <p>Track Your Expenses</p>
              </div>
          </div>
          <div class="header-right">
              <button class="theme-toggle" onclick="toggleTheme()">Dark Mode</button>
          </div>
      </header>

      <div style="padding: 0 2rem;">
          <nav class="nav">
              <a href="dashboard.php">Dashboard</a>
              <a href="expense-tracker.php" class="active">Expenses</a>
              <a href="budget-limits.php">Budget</a>
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
              <h2>Add New Expense</h2>
              <form method="POST" class="form-inline" id="expenseForm">
                  <input type="hidden" name="action" value="add_expense">

                  <div class="form-row">
                      <div class="form-group">
                          <label for="amount">Amount</label>
                          <input type="number" id="amount" step="0.01" name="amount" required placeholder="0.00">
                      </div>

                      <div class="form-group">
                          <label for="category">Category</label>
                          <select id="category" name="category" required onchange="handleCategoryChange(this.value)">
                              <option value="">Select category</option>
                              <option value="food">Food</option>
                              <option value="transpo">Transportation</option>
                              <option value="shopping">Shopping</option>
                              <option value="health">Health &amp; Medical</option>
                              <option value="entertainment">Entertainment</option>
                              <option value="utilities">Utilities &amp; Bills</option>
                              <option value="education">Education</option>
                              <option value="others">Others</option>
                          </select>
                          <div id="custom_category_wrap">
                              <input type="text" id="custom_category" name="custom_category"
                                     placeholder="Describe the expense (e.g. Birthday gift)"
                                     maxlength="100">
                          </div>
                      </div>

                      <div class="form-group">
                          <label for="expense_date">Date</label>
                          <input type="date" id="expense_date" name="expense_date" value="<?= $today ?>" required>
                      </div>
                  </div>

                  <div class="form-group">
                      <label for="notes">Notes</label>
                      <input type="text" id="notes" name="notes" placeholder="Optional description">
                  </div>

                  <button type="submit" class="btn btn-primary">Add Expense</button>
              </form>
          </div>

          <div class="stats-grid">
              <div class="stat-card">
                  <div class="stat-label">Today's Total</div>
                  <div class="stat-value">&#8369;<?= number_format($todayTotal, 2) ?></div>
                  <small>Food: &#8369;<?= number_format($todayFood, 2) ?></small>
              </div>
              <div class="stat-card">
                  <div class="stat-label">Weekly Total</div>
                  <div class="stat-value">&#8369;<?= number_format($weekTotal, 2) ?></div>
              </div>
              <div class="stat-card">
                  <div class="stat-label">Monthly Total</div>
                  <div class="stat-value">&#8369;<?= number_format($monthTotal, 2) ?></div>
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
                      <option value="shopping">Shopping</option>
                      <option value="health">Health &amp; Medical</option>
                      <option value="entertainment">Entertainment</option>
                      <option value="utilities">Utilities &amp; Bills</option>
                      <option value="education">Education</option>
                      <option value="others">Others</option>
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
              <p id="noResults" style="display:none; text-align:center; padding:2rem; color:var(--text-secondary);">
                  No expenses found.
              </p>
          </div>
      </div>

      <script>
          var allExpenses = <?= json_encode($allExpenses) ?>;

          var catLabels = {
              food:          'Food',
              transpo:       'Transportation',
              shopping:      'Shopping',
              health:        'Health & Medical',
              entertainment: 'Entertainment',
              utilities:     'Utilities & Bills',
              education:     'Education',
              others:        'Others'
          };

          var catClasses = {
              food:          'category-food',
              transpo:       'category-transpo',
              shopping:      'category-shopping',
              health:        'category-health',
              entertainment: 'category-entertainment',
              utilities:     'category-utilities',
              education:     'category-education',
              others:        'category-others'
          };

          function renderTable(data) {
              var tbody    = document.getElementById('expensesBody');
              var noRes    = document.getElementById('noResults');
              tbody.innerHTML = '';
              if (data.length === 0) { noRes.style.display = 'block'; return; }
              noRes.style.display = 'none';

              data.forEach(function(exp) {
                  var cssClass = catClasses[exp.category] || 'category-others';
                  var label    = catLabels[exp.category]  || exp.category;
                  var tr = document.createElement('tr');
                  tr.innerHTML =
                      '<td>' + exp.expense_date + '</td>' +
                      '<td class="' + cssClass + '">' + label + '</td>' +
                      '<td>' + (exp.description || '-') + '</td>' +
                      '<td>&#8369;' + parseFloat(exp.amount).toFixed(2) + '</td>' +
                      '<td>' +
                          '<form method="POST" style="display:inline;" onsubmit="return confirm(\'Delete this expense?\');">' +
                              '<input type="hidden" name="action" value="delete_expense">' +
                              '<input type="hidden" name="expense_id" value="' + exp.id + '">' +
                              '<button type="submit" class="btn btn-small btn-danger">Delete</button>' +
                          '</form>' +
                      '</td>';
                  tbody.appendChild(tr);
              });
          }

          function applyFilters() {
              var period   = document.getElementById('periodFilter').value;
              var category = document.getElementById('categoryFilter').value;
              var filtered = allExpenses.slice();
              var today    = new Date().toISOString().slice(0, 10);

              if (period === 'day') {
                  filtered = filtered.filter(function(e) { return e.expense_date === today; });
              } else if (period === 'week') {
                  var weekAgo = new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10);
                  filtered = filtered.filter(function(e) { return e.expense_date >= weekAgo; });
              } else if (period === 'month') {
                  var ms = today.slice(0, 7) + '-01';
                  filtered = filtered.filter(function(e) { return e.expense_date >= ms; });
              }

              if (category !== 'all') {
                  filtered = filtered.filter(function(e) { return e.category === category; });
              }

              renderTable(filtered);
          }

          function resetFilters() {
              document.getElementById('periodFilter').value   = 'day';
              document.getElementById('categoryFilter').value = 'all';
              applyFilters();
          }

          function handleCategoryChange(val) {
              var wrap  = document.getElementById('custom_category_wrap');
              var input = document.getElementById('custom_category');
              if (val === 'others') {
                  wrap.style.display = 'block';
                  input.required     = true;
              } else {
                  wrap.style.display = 'none';
                  input.required     = false;
                  input.value        = '';
              }
          }

          function toggleTheme() {
              var html     = document.documentElement;
              var newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
              html.setAttribute('data-theme', newTheme);
              document.cookie = 'theme=' + newTheme + '; path=/; max-age=31536000';
              document.querySelector('.theme-toggle').textContent = newTheme === 'dark' ? 'Light Mode' : 'Dark Mode';
          }

          window.addEventListener('load', function() {
              var theme = document.documentElement.getAttribute('data-theme') || 'light';
              document.querySelector('.theme-toggle').textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
              applyFilters();
          });
      </script>
  </body>
  </html>
  