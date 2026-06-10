<?php
  require_once __DIR__ . '/auth_check.php'; // sets $adminUsername, $conn

  $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
  setcookie('theme', $theme, time() + (86400 * 365), '/');

  // ═══════════════════════════════════════════════════════════════════════
  // OLAP ANALYTICS QUERIES
  // ═══════════════════════════════════════════════════════════════════════

  // ── Overview counts ────────────────────────────────────────────────────
  $totalUsers     = (int)$conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
  $newUsersToday  = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'];
  $newUsersWeek   = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'];
  $newUsersMonth  = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['c'];

  $totalExpenses      = (int)$conn->query("SELECT COUNT(*) as c FROM expenses")->fetch_assoc()['c'];
  $totalExpenseAmount = (float)$conn->query("SELECT COALESCE(SUM(amount),0) as s FROM expenses")->fetch_assoc()['s'];
  $expensesToday      = (float)$conn->query("SELECT COALESCE(SUM(amount),0) as s FROM expenses WHERE expense_date = CURDATE()")->fetch_assoc()['s'];
  $expensesThisMonth  = (float)$conn->query("SELECT COALESCE(SUM(amount),0) as s FROM expenses WHERE DATE_FORMAT(expense_date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')")->fetch_assoc()['s'];

  $totalSavingsEntries = (int)$conn->query("SELECT COUNT(*) as c FROM savings")->fetch_assoc()['c'];
  $totalSavingsAmount  = (float)$conn->query("SELECT COALESCE(SUM(amount),0) as s FROM savings")->fetch_assoc()['s'];
  $savingsThisMonth    = (float)$conn->query("SELECT COALESCE(SUM(amount),0) as s FROM savings WHERE DATE_FORMAT(savings_date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')")->fetch_assoc()['s'];

  // ── Expense breakdown by category ──────────────────────────────────────
  $catBreakdown = [];
  $r = $conn->query("SELECT category, COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM expenses GROUP BY category");
  while ($row = $r->fetch_assoc()) $catBreakdown[$row['category']] = $row;

  $allCatDefs = [
      'food'          => ['label' => 'Food',             'bar' => 'bar-green'],
      'transpo'       => ['label' => 'Transport',        'bar' => 'bar-blue'],
      'shopping'      => ['label' => 'Shopping',         'bar' => 'bar-orange'],
      'health'        => ['label' => 'Health & Medical', 'bar' => 'bar-pink'],
      'entertainment' => ['label' => 'Entertainment',    'bar' => 'bar-yellow'],
      'utilities'     => ['label' => 'Utilities & Bills','bar' => 'bar-teal'],
      'education'     => ['label' => 'Education',        'bar' => 'bar-indigo'],
      'others'        => ['label' => 'Others',           'bar' => 'bar-gray'],
  ];
  $grandCatTotal = array_sum(array_column($catBreakdown, 'total')) ?: 1;

  // ── Monthly expense trend (last 6 months) ──────────────────────────────
  $monthlyExpenses = [];
  $r = $conn->query("
      SELECT DATE_FORMAT(expense_date,'%Y-%m') as month,
             DATE_FORMAT(expense_date,'%b %Y')  as label,
             COUNT(*) as cnt,
             COALESCE(SUM(amount),0) as total,
             COALESCE(SUM(CASE WHEN category='food'   THEN amount ELSE 0 END),0) as food_total,
             COALESCE(SUM(CASE WHEN category='transpo' THEN amount ELSE 0 END),0) as transpo_total
      FROM expenses
      WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
      GROUP BY month, label
      ORDER BY month ASC
  ");
  while ($row = $r->fetch_assoc()) $monthlyExpenses[] = $row;

  // ── Monthly savings trend (last 6 months) ──────────────────────────────
  $monthlySavings = [];
  $r = $conn->query("
      SELECT DATE_FORMAT(savings_date,'%Y-%m') as month,
             DATE_FORMAT(savings_date,'%b %Y')  as label,
             COUNT(*) as cnt,
             COALESCE(SUM(amount),0) as total
      FROM savings
      WHERE savings_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
      GROUP BY month, label
      ORDER BY month ASC
  ");
  while ($row = $r->fetch_assoc()) $monthlySavings[] = $row;

  // ── Monthly user registrations (last 6 months) ─────────────────────────
  $monthlyUsers = [];
  $r = $conn->query("
      SELECT DATE_FORMAT(created_at,'%Y-%m') as month,
             DATE_FORMAT(created_at,'%b %Y')  as label,
             COUNT(*) as cnt
      FROM users
      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
      GROUP BY month, label
      ORDER BY month ASC
  ");
  while ($row = $r->fetch_assoc()) $monthlyUsers[] = $row;

  // ── Per-user stats table ────────────────────────────────────────────────
  $userStats = [];
  $r = $conn->query("
      SELECT u.id, u.first_name, u.last_name, u.email,
             DATE_FORMAT(u.created_at,'%Y-%m-%d') as joined,
             u.goal_name, u.goal_target,
             COALESCE(e.expense_count, 0) as expense_count,
             COALESCE(e.total_expenses, 0) as total_expenses,
             COALESCE(s.savings_count, 0) as savings_count,
             COALESCE(s.total_savings, 0) as total_savings
      FROM users u
      LEFT JOIN (
          SELECT user_id, COUNT(*) as expense_count, SUM(amount) as total_expenses
          FROM expenses GROUP BY user_id
      ) e ON e.user_id = u.id
      LEFT JOIN (
          SELECT user_id, COUNT(*) as savings_count, SUM(amount) as total_savings
          FROM savings GROUP BY user_id
      ) s ON s.user_id = u.id
      ORDER BY u.created_at DESC
  ");
  while ($row = $r->fetch_assoc()) $userStats[] = $row;

  // ── Top 5 spenders ─────────────────────────────────────────────────────
  $topSpenders = [];
  $r = $conn->query("
      SELECT u.first_name, u.last_name, u.email,
             COUNT(e.id) as expense_count,
             COALESCE(SUM(e.amount),0) as total
      FROM users u
      JOIN expenses e ON e.user_id = u.id
      GROUP BY u.id, u.first_name, u.last_name, u.email
      ORDER BY total DESC
      LIMIT 5
  ");
  while ($row = $r->fetch_assoc()) $topSpenders[] = $row;

  // ── Goal completion rate ────────────────────────────────────────────────
  $usersWithGoalReached = 0;
  foreach ($userStats as $u) {
      if ((float)$u['goal_target'] > 0 && (float)$u['total_savings'] >= (float)$u['goal_target']) {
          $usersWithGoalReached++;
      }
  }
  $goalRate = $totalUsers > 0 ? round($usersWithGoalReached / $totalUsers * 100) : 0;
  $avgExpPerUser = $totalUsers > 0 ? round($totalExpenseAmount / $totalUsers, 2) : 0;
  $avgSavePerUser = $totalUsers > 0 ? round($totalSavingsAmount / $totalUsers, 2) : 0;
  ?>
  <!DOCTYPE html>
  <html data-theme="<?= $theme ?>">
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>BudgetBuddy - Admin Dashboard</title>
      <link rel="stylesheet" href="../frontend/styles.css">
      <style>
          .admin-badge {
              background: linear-gradient(135deg, #FF6B6B, #FFA94D);
              color: white;
              padding: 0.25rem 0.75rem;
              border-radius: 20px;
              font-size: 0.8rem;
              font-weight: 700;
              letter-spacing: 0.5px;
              text-transform: uppercase;
          }
          .section-title {
              font-size: 1.1rem;
              font-weight: 700;
              color: var(--text-dark);
              margin-bottom: 1.5rem;
              padding-bottom: 0.75rem;
              border-bottom: 2px solid var(--border-light);
              display: flex;
              align-items: center;
              gap: 0.5rem;
          }
          .stats-grid-6 {
              display: grid;
              grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
              gap: 1rem;
              margin-bottom: 2rem;
          }
          .mini-stat {
              background: var(--bg-white);
              border: 1px solid var(--border-light);
              border-radius: 12px;
              padding: 1.25rem;
              text-align: center;
              transition: all 0.3s ease;
          }
          .mini-stat:hover {
              transform: translateY(-3px);
              box-shadow: 0 6px 20px rgba(0,0,0,0.1);
          }
          .mini-stat .ms-label {
              font-size: 0.78rem;
              color: var(--text-secondary);
              font-weight: 600;
              text-transform: uppercase;
              letter-spacing: 0.4px;
          }
          .mini-stat .ms-value {
              font-size: 1.6rem;
              font-weight: 700;
              color: var(--text-dark);
              margin-top: 0.4rem;
              line-height: 1;
          }
          .mini-stat .ms-sub {
              font-size: 0.75rem;
              color: var(--text-secondary);
              margin-top: 0.3rem;
          }
          .chart-bar-wrap {
              display: flex;
              flex-direction: column;
              gap: 0.75rem;
          }
          .chart-bar-row {
              display: flex;
              align-items: center;
              gap: 0.75rem;
              font-size: 0.9rem;
          }
          .chart-bar-label {
              width: 80px;
              text-align: right;
              color: var(--text-light);
              font-size: 0.8rem;
              flex-shrink: 0;
          }
          .chart-bar-track {
              flex: 1;
              background: var(--bg-light);
              border-radius: 6px;
              height: 22px;
              overflow: hidden;
              border: 1px solid var(--border-light);
          }
          .chart-bar-fill {
              height: 100%;
              border-radius: 6px;
              display: flex;
              align-items: center;
              padding-left: 0.5rem;
              font-size: 0.75rem;
              font-weight: 700;
              color: white;
              white-space: nowrap;
              transition: width 0.6s ease;
          }
          .bar-green  { background: linear-gradient(90deg, #50C878, #A8E6CF); }
          .bar-blue   { background: linear-gradient(90deg, #4FACFE, #00F2FE); }
          .bar-orange { background: linear-gradient(90deg, #FFA94D, #FFD700); }
          .bar-pink   { background: linear-gradient(90deg, #FF6B6B, #FFAAA5); }
          .bar-yellow { background: linear-gradient(90deg, #F39C12, #F7DC6F); }
          .bar-teal   { background: linear-gradient(90deg, #1ABC9C, #76D7C4); }
          .bar-indigo { background: linear-gradient(90deg, #3498DB, #85C1E9); }
          .bar-gray   { background: linear-gradient(90deg, #95A5A6, #BDC3C7); }
          .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
          @media (max-width: 700px) { .two-col { grid-template-columns: 1fr; } }
          .pill-food   { background: #D4EDDA; color: #155724; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
          .pill-transpo{ background: #D1ECF1; color: #0C5460; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
          .goal-pill-ok  { background: #D4EDDA; color: #155724; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
          .goal-pill-no  { background: #F8D7DA; color: #721C24; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
          .user-table-wrap { overflow-x: auto; }
      </style>
  </head>
  <body>
  <header class="header">
      <div class="header-left">
          <div class="logo-container">
              <img src="../frontend/logo.png" alt="Logo"
                   onerror="this.style.display='none';document.querySelector('.logo-placeholder').style.display='flex';">
              <div class="logo-placeholder" style="display:none;">BB</div>
          </div>
          <div class="header-content">
              <h1>BudgetBuddy <span class="admin-badge">Admin</span></h1>
              <p>Welcome, <?= htmlspecialchars($adminUsername) ?></p>
          </div>
      </div>
      <div class="header-right">
          <button class="theme-toggle" onclick="toggleTheme()">Dark Mode</button>
      </div>
  </header>

  <div style="padding: 0 2rem;">
      <nav class="nav">
          <a href="dashboard.php" class="active">Analytics</a>
          <a href="users.php">Users</a>
          <a href="logout.php" class="nav-logout">Logout</a>
      </nav>
  </div>

  <div class="container">

      <!-- ── Overview Stats ─────────────────────────────────────────── -->
      <div class="stats-grid">
          <div class="stat-card">
              <div class="stat-label">Total Users</div>
              <div class="stat-value"><?= $totalUsers ?></div>
          </div>
          <div class="stat-card">
              <div class="stat-label">Total Expenses</div>
              <div class="stat-value">₱<?= number_format($totalExpenseAmount, 0) ?></div>
          </div>
          <div class="stat-card">
              <div class="stat-label">Total Savings</div>
              <div class="stat-value">₱<?= number_format($totalSavingsAmount, 0) ?></div>
          </div>
          <div class="stat-card">
              <div class="stat-label">Goals Reached</div>
              <div class="stat-value"><?= $usersWithGoalReached ?> <small style="font-size:1rem;">(<?= $goalRate ?>%)</small></div>
          </div>
      </div>

      <!-- ── Secondary KPIs ─────────────────────────────────────────── -->
      <div class="stats-grid-6">
          <div class="mini-stat">
              <div class="ms-label">New Today</div>
              <div class="ms-value"><?= $newUsersToday ?></div>
              <div class="ms-sub">users</div>
          </div>
          <div class="mini-stat">
              <div class="ms-label">New This Week</div>
              <div class="ms-value"><?= $newUsersWeek ?></div>
              <div class="ms-sub">users</div>
          </div>
          <div class="mini-stat">
              <div class="ms-label">New This Month</div>
              <div class="ms-value"><?= $newUsersMonth ?></div>
              <div class="ms-sub">users</div>
          </div>
          <div class="mini-stat">
              <div class="ms-label">Expenses Today</div>
              <div class="ms-value">₱<?= number_format($expensesToday, 0) ?></div>
              <div class="ms-sub"><?= $totalExpenses ?> records total</div>
          </div>
          <div class="mini-stat">
              <div class="ms-label">Expenses/Month</div>
              <div class="ms-value">₱<?= number_format($expensesThisMonth, 0) ?></div>
              <div class="ms-sub">current month</div>
          </div>
          <div class="mini-stat">
              <div class="ms-label">Savings/Month</div>
              <div class="ms-value">₱<?= number_format($savingsThisMonth, 0) ?></div>
              <div class="ms-sub">current month</div>
          </div>
          <div class="mini-stat">
              <div class="ms-label">Avg Exp / User</div>
              <div class="ms-value">₱<?= number_format($avgExpPerUser, 0) ?></div>
              <div class="ms-sub">lifetime</div>
          </div>
          <div class="mini-stat">
              <div class="ms-label">Avg Savings / User</div>
              <div class="ms-value">₱<?= number_format($avgSavePerUser, 0) ?></div>
              <div class="ms-sub">lifetime</div>
          </div>
      </div>

      <div class="two-col">
          <!-- ── Expense Category Breakdown ─────────────────────────── -->
          <div class="card">
              <div class="section-title">Expenses by Category</div>
              <div class="chart-bar-wrap">
                  <?php foreach ($allCatDefs as $catKey => $catDef):
                      $catTotal = (float)($catBreakdown[$catKey]['total'] ?? 0);
                      $catCount = (int)($catBreakdown[$catKey]['cnt']   ?? 0);
                      if ($catTotal <= 0) continue;
                      $catPct = round($catTotal / $grandCatTotal * 100);
                  ?>
                  <div class="chart-bar-row">
                      <div class="chart-bar-label"><?= htmlspecialchars($catDef['label']) ?></div>
                      <div class="chart-bar-track">
                          <div class="chart-bar-fill <?= $catDef['bar'] ?>" style="width:<?= $catPct ?>%">
                              <?= $catPct > 10 ? $catPct.'%' : '' ?>
                          </div>
                      </div>
                      <span style="font-size:0.85rem;font-weight:600;min-width:80px;">₱<?= number_format($catTotal,0) ?> (<?= $catCount ?>)</span>
                  </div>
                  <?php endforeach; ?>
              </div>
              <div style="margin-top:1.5rem;font-size:0.9rem;color:var(--text-secondary);">
                  Total expense records: <strong style="color:var(--text-dark);"><?= $totalExpenses ?></strong>
              </div>
          </div>

          <!-- ── Top 5 Spenders ─────────────────────────────────────── -->
          <div class="card">
              <div class="section-title">Top Spenders</div>
              <?php if (empty($topSpenders)): ?>
                  <p style="color:var(--text-secondary);">No expense data yet.</p>
              <?php else:
                  $maxSpend = (float)$topSpenders[0]['total'];
              ?>
              <div class="chart-bar-wrap">
                  <?php foreach ($topSpenders as $i => $sp):
                      $pct = $maxSpend > 0 ? round((float)$sp['total'] / $maxSpend * 100) : 0;
                      $barClasses = ['bar-green','bar-blue','bar-orange','bar-pink','bar-green'];
                  ?>
                  <div class="chart-bar-row">
                      <div class="chart-bar-label" title="<?= htmlspecialchars($sp['email']) ?>">
                          <?= htmlspecialchars($sp['first_name']) ?>
                      </div>
                      <div class="chart-bar-track">
                          <div class="chart-bar-fill <?= $barClasses[$i] ?>" style="width:<?= $pct ?>%">
                              <?= $pct > 15 ? '₱'.number_format((float)$sp['total'],0) : '' ?>
                          </div>
                      </div>
                      <span style="font-size:0.82rem;font-weight:600;min-width:80px;">₱<?= number_format((float)$sp['total'],0) ?></span>
                  </div>
                  <?php endforeach; ?>
              </div>
              <?php endif; ?>
          </div>
      </div>

      <!-- ── Monthly Expense Trend ───────────────────────────────────── -->
      <div class="card">
          <div class="section-title">Monthly Expense Trend (Last 6 Months)</div>
          <?php if (empty($monthlyExpenses)): ?>
              <p style="color:var(--text-secondary);">No expense data in the last 6 months.</p>
          <?php else:
              $maxMonthly = max(array_column($monthlyExpenses,'total')) ?: 1;
          ?>
          <div class="table-wrapper">
              <table class="table">
                  <thead>
                      <tr>
                          <th>Month</th>
                          <th>Total Expenses</th>
                          <th>Food</th>
                          <th>Transport</th>
                          <th>Records</th>
                          <th>Visual</th>
                      </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($monthlyExpenses as $m):
                      $pct = round((float)$m['total'] / $maxMonthly * 100);
                  ?>
                      <tr>
                          <td><strong><?= htmlspecialchars($m['label']) ?></strong></td>
                          <td><strong>₱<?= number_format((float)$m['total'],2) ?></strong></td>
                          <td><span class="pill-food">₱<?= number_format((float)$m['food_total'],2) ?></span></td>
                          <td><span class="pill-transpo">₱<?= number_format((float)$m['transpo_total'],2) ?></span></td>
                          <td><?= (int)$m['cnt'] ?></td>
                          <td style="min-width:120px;">
                              <div class="chart-bar-track" style="height:16px;">
                                  <div class="chart-bar-fill bar-green" style="width:<?= $pct ?>%;"></div>
                              </div>
                          </td>
                      </tr>
                  <?php endforeach; ?>
                  </tbody>
              </table>
          </div>
          <?php endif; ?>
      </div>

      <div class="two-col">
          <!-- ── Monthly Savings Trend ───────────────────────────────── -->
          <div class="card">
              <div class="section-title">Monthly Savings (Last 6 Months)</div>
              <?php if (empty($monthlySavings)): ?>
                  <p style="color:var(--text-secondary);">No savings data yet.</p>
              <?php else:
                  $maxSave = max(array_column($monthlySavings,'total')) ?: 1;
              ?>
              <div class="chart-bar-wrap">
                  <?php foreach ($monthlySavings as $m):
                      $pct = round((float)$m['total'] / $maxSave * 100);
                  ?>
                  <div class="chart-bar-row">
                      <div class="chart-bar-label"><?= htmlspecialchars($m['label']) ?></div>
                      <div class="chart-bar-track">
                          <div class="chart-bar-fill bar-orange" style="width:<?= $pct ?>%">
                              <?= $pct > 20 ? '₱'.number_format((float)$m['total'],0) : '' ?>
                          </div>
                      </div>
                      <span style="font-size:0.82rem;font-weight:600;min-width:80px;">₱<?= number_format((float)$m['total'],0) ?></span>
                  </div>
                  <?php endforeach; ?>
              </div>
              <?php endif; ?>
          </div>

          <!-- ── Monthly User Registrations ─────────────────────────── -->
          <div class="card">
              <div class="section-title">User Registrations (Last 6 Months)</div>
              <?php if (empty($monthlyUsers)): ?>
                  <p style="color:var(--text-secondary);">No recent registrations.</p>
              <?php else:
                  $maxReg = max(array_column($monthlyUsers,'cnt')) ?: 1;
              ?>
              <div class="chart-bar-wrap">
                  <?php foreach ($monthlyUsers as $m):
                      $pct = round((int)$m['cnt'] / $maxReg * 100);
                  ?>
                  <div class="chart-bar-row">
                      <div class="chart-bar-label"><?= htmlspecialchars($m['label']) ?></div>
                      <div class="chart-bar-track">
                          <div class="chart-bar-fill bar-pink" style="width:<?= max($pct,3) ?>%">
                              <?= $pct > 20 ? $m['cnt'] : '' ?>
                          </div>
                      </div>
                      <span style="font-size:0.82rem;font-weight:600;min-width:50px;"><?= (int)$m['cnt'] ?> users</span>
                  </div>
                  <?php endforeach; ?>
              </div>
              <?php endif; ?>
          </div>
      </div>

      <!-- ── All Users Table ────────────────────────────────────────── -->
      <div class="card">
          <div class="section-title">All Users — Detail Report</div>
          <div class="user-table-wrap">
              <table class="table">
                  <thead>
                      <tr>
                          <th>#</th>
                          <th>Name</th>
                          <th>Email</th>
                          <th>Joined</th>
                          <th>Expenses</th>
                          <th>Total Spent</th>
                          <th>Savings</th>
                          <th>Total Saved</th>
                          <th>Goal</th>
                          <th>Goal Status</th>
                      </tr>
                  </thead>
                  <tbody>
                  <?php if (empty($userStats)): ?>
                      <tr><td colspan="10" style="text-align:center;color:var(--text-secondary);">No users found.</td></tr>
                  <?php else: ?>
                      <?php foreach ($userStats as $u):
                          $progress = (float)$u['goal_target'] > 0
                              ? min(100, round((float)$u['total_savings'] / (float)$u['goal_target'] * 100))
                              : 0;
                          $reached  = (float)$u['total_savings'] >= (float)$u['goal_target'] && (float)$u['goal_target'] > 0;
                      ?>
                      <tr>
                          <td><?= (int)$u['id'] ?></td>
                          <td><strong><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></strong></td>
                          <td style="font-size:0.85rem;color:var(--text-secondary);"><?= htmlspecialchars($u['email']) ?></td>
                          <td><?= htmlspecialchars($u['joined']) ?></td>
                          <td><?= (int)$u['expense_count'] ?></td>
                          <td>₱<?= number_format((float)$u['total_expenses'],2) ?></td>
                          <td><?= (int)$u['savings_count'] ?></td>
                          <td>₱<?= number_format((float)$u['total_savings'],2) ?></td>
                          <td style="font-size:0.85rem;">
                              <?= htmlspecialchars($u['goal_name']) ?><br>
                              <small style="color:var(--text-secondary);">Target: ₱<?= number_format((float)$u['goal_target'],0) ?></small>
                          </td>
                          <td>
                              <?php if ($reached): ?>
                                  <span class="goal-pill-ok">Reached ✓</span>
                              <?php else: ?>
                                  <span class="goal-pill-no"><?= $progress ?>%</span>
                              <?php endif; ?>
                          </td>
                      </tr>
                      <?php endforeach; ?>
                  <?php endif; ?>
                  </tbody>
              </table>
          </div>
      </div>

  </div><!-- /container -->

  <script>
  function toggleTheme() {
      const html = document.documentElement;
      const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', newTheme);
      document.cookie = `theme=${newTheme}; path=/; max-age=31536000`;
      document.querySelector('.theme-toggle').textContent = newTheme === 'dark' ? 'Light Mode' : 'Dark Mode';
  }
  window.addEventListener('load', function () {
      const theme = document.documentElement.getAttribute('data-theme') || 'light';
      document.querySelector('.theme-toggle').textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
  });
  </script>
  </body>
  </html>
  