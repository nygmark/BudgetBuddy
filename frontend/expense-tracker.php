<?php
  require_once __DIR__ . '/../includes/auth_check.php';
  require_once __DIR__ . '/../includes/helpers.php';

  $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
  setcookie('theme', $theme, time() + (86400 * 365), '/');

  $msg    = isset($_GET['msg'])    ? $_GET['msg']    : '';
  $err    = isset($_GET['err'])    ? $_GET['err']    : '';
  $popup  = isset($_GET['popup'])  ? $_GET['popup']  : '';
  $pBudget = isset($_GET['budget']) ? $_GET['budget'] : '';
  $pSpent  = isset($_GET['spent'])  ? $_GET['spent']  : '';

  $today        = date('Y-m-d');
  $foodLimit    = (float)($currentUser['food_daily_limit']   ?? 150);
  $transpoLimit = (float)($currentUser['transpo_daily_limit'] ?? 100);

  $validCategories = ['food','transpo','shopping','health','entertainment','utilities','education','others'];

  // ── Helper: encode a category+notes into DB-safe values ──────────────────────
  // food/transpo go straight into the ENUM column (no change needed).
  // All other categories are encoded as "[category] notes" inside description
  // so the ENUM column stays valid without any schema migration.
  function encode_expense($category, $customCat, $notes) {
      if ($category === 'food' || $category === 'transpo') {
          return ['dbCat' => $category, 'dbDesc' => $notes];
      }
      $label  = ($category === 'others') ? $customCat : $category;
      $dbDesc = '[' . $label . ']' . ($notes !== '' ? ' ' . $notes : '');
      return ['dbCat' => 'food', 'dbDesc' => $dbDesc]; // 'food' is a valid ENUM placeholder
  }

  // ── Helper: decode a DB row back into display values ─────────────────────────
  function decode_expense(&$row) {
      if (preg_match('/^\[([^\]]+)\]\s*(.*)$/s', $row['description'] ?? '', $m)) {
          $row['display_category'] = $m[1];
          $row['description']      = trim($m[2]);
      } else {
          $row['display_category'] = $row['category'];
      }
  }

  // ── Handle add expense ────────────────────────────────────────────────────────
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_expense') {
      $amount      = floatval($_POST['amount'] ?? 0);
      $category    = trim($_POST['category'] ?? '');
      $expenseDate = trim($_POST['expense_date'] ?? $today);
      $customCat   = trim($_POST['custom_category'] ?? '');
      $notes       = trim($_POST['notes'] ?? '');

      if ($category === 'others' && $customCat === '') {
          $err = 'Please describe the expense when using "Others".';
          header('Location: expense-tracker.php?err=' . urlencode($err));
          exit;
      }

      if ($amount > 0 && in_array($category, $validCategories)) {
          $enc  = encode_expense($category, $customCat, $notes);
          $stmt = $conn->prepare('INSERT INTO expenses (user_id, amount, category, description, expense_date) VALUES (?, ?, ?, ?, ?)');
          $stmt->bind_param('idsss', $userId, $amount, $enc['dbCat'], $enc['dbDesc'], $expenseDate);
          if ($stmt->execute()) {
              $msg = 'Expense added successfully.';
              // Check if monthly budget is now exceeded
              $monthlyBudget  = (float)($currentUser['monthly_budget'] ?? 0);
              $newMonthTotal  = get_monthly_spent($conn, $userId);
              $popup = '';
              if ($monthlyBudget > 0 && $newMonthTotal > $monthlyBudget) {
                  $popup = '&popup=over_budget&budget=' . urlencode(number_format($monthlyBudget,2)) . '&spent=' . urlencode(number_format($newMonthTotal,2));
              }
          } else {
              $err = 'Failed to add expense.';
          }
          $stmt->close();
      } else {
          $err = 'Invalid expense data.';
      }
      header('Location: expense-tracker.php?msg=' . urlencode($msg) . '&err=' . urlencode($err) . ($popup ?? ''));
      exit;
  }

  // ── Handle delete expense ─────────────────────────────────────────────────────
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_expense') {
      $eid = intval($_POST['expense_id'] ?? 0);
      $del = $conn->prepare('DELETE FROM expenses WHERE id = ? AND user_id = ?');
      $del->bind_param('ii', $eid, $userId);
      $del->execute();
      $del->close();
      header('Location: expense-tracker.php?msg=' . urlencode('Expense deleted.'));
      exit;
  }

  // ── Fetch data ────────────────────────────────────────────────────────────────
  $todayTotal  = get_daily_spent($conn, $userId, $today);
  $weekTotal   = get_weekly_spent($conn, $userId);
  $monthTotal  = get_monthly_spent($conn, $userId);
  $todayFood   = get_daily_spent($conn, $userId, $today, 'food');

  $allExpenses = get_all_expenses($conn, $userId);
  foreach ($allExpenses as &$row) decode_expense($row);
  unset($row);
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
                  <img id="logoImg" src="logo.png" alt="Logo"
                       onerror="this.style.display='none'; document.querySelector('.logo-placeholder').style.display='flex';">
                  <div class="logo-placeholder" id="logoPlaceholder" style="display:none;">BB</div>
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

      <div style="padding:0 2rem;">
          <nav class="nav">
              <a href="dashboard.php">Dashboard</a>
              <a href="expense-tracker.php" class="active">Expenses</a>
              <a href="budget-limits.php">Budget</a>
              <a href="saving-goal.php">Goals</a>
              <a href="profile.php">Profile</a>
            <a href="logout.php" class="nav-logout">Logout</a>
          </nav>
      </div>

      <div class="container">
          <?php if ($msg): ?>
              <div class="alert alert-success"><strong>Success:</strong> <?= htmlspecialchars($msg) ?></div>
          <?php endif; ?>
          <?php if ($err): ?>
              <div class="alert alert-error"><strong>Error:</strong> <?= htmlspecialchars($err) ?></div>
          <?php endif; ?>

          <div class="card">
              <h2>Add New Expense</h2>
              <form method="POST" class="form-inline">
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
                                     placeholder="Describe the expense (e.g. Birthday gift)" maxlength="100">
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
              <p id="noResults" style="display:none;text-align:center;padding:2rem;color:var(--text-secondary);">
                  No expenses found.
              </p>
          </div>
      </div>

      <script>
      var allExpenses = <?= json_encode(array_values($allExpenses)) ?>;

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
          var tbody = document.getElementById('expensesBody');
          var noRes = document.getElementById('noResults');
          tbody.innerHTML = '';
          if (!data.length) { noRes.style.display = 'block'; return; }
          noRes.style.display = 'none';

          data.forEach(function(exp) {
              var cat      = (exp.display_category || exp.category || '').trim();
              var cssClass = catClasses[cat] || 'category-others';
              var label    = catLabels[cat]  || (cat || '—');
              var tr = document.createElement('tr');
              tr.innerHTML =
                  '<td>' + (exp.expense_date || '') + '</td>' +
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
              var wAgo = new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10);
              filtered = filtered.filter(function(e) { return e.expense_date >= wAgo; });
          } else if (period === 'month') {
              var ms = today.slice(0, 7) + '-01';
              filtered = filtered.filter(function(e) { return e.expense_date >= ms; });
          }

          if (category !== 'all') {
              filtered = filtered.filter(function(e) {
                  return (e.display_category || e.category) === category;
              });
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
          <?php if ($popup === 'over_budget' && $pBudget && $pSpent): ?>
          showNotif('warning',
              '⚠️ Monthly Budget Exceeded!',
              'You have spent ₱<?= htmlspecialchars($pSpent) ?> this month, which is over your ₱<?= htmlspecialchars($pBudget) ?> budget.'
          );
          <?php endif; ?>
      });

      /* ── Popup notification ─────────────────────────────── */
      function showNotif(type, title, message) {
          var colors = { warning: '#E67E22', success: '#27AE60', info: '#2980B9' };
          var overlay = document.getElementById('notif-overlay');
          var popup   = document.getElementById('notif-popup');
          var bar     = document.getElementById('notif-bar');
          document.getElementById('notif-title').textContent   = title;
          document.getElementById('notif-message').textContent = message;
          bar.style.background = colors[type] || colors.info;
          overlay.style.display = 'flex';
          popup.style.display   = 'block';
      }
      function closeNotif() {
          document.getElementById('notif-overlay').style.display = 'none';
          document.getElementById('notif-popup').style.display   = 'none';
      }
      document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeNotif(); });
      </script>

  <!-- Popup notification modal -->
  <div id="notif-overlay" onclick="closeNotif()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9998;align-items:center;justify-content:center;"></div>
  <div id="notif-popup" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999;background:var(--card-bg,#1a2332);border:1px solid rgba(255,255,255,0.12);border-radius:1.25rem;width:min(420px,92vw);overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,0.5);">
      <div id="notif-bar" style="height:5px;width:100%;"></div>
      <div style="padding:2rem 2rem 1.75rem;text-align:center;">
          <h3 id="notif-title"  style="margin:0 0 0.75rem;font-size:1.15rem;color:var(--text-dark,#fff);"></h3>
          <p  id="notif-message" style="margin:0 0 1.5rem;color:var(--text-secondary,#aaa);font-size:0.95rem;line-height:1.5;"></p>
          <button onclick="closeNotif()" class="btn btn-primary" style="min-width:120px;">OK, Got it!</button>
      </div>
  </div>

  </body>
  </html>
  