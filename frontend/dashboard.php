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
      <style>
          .pie-wrap {
              display: flex;
              flex-wrap: wrap;
              gap: 2rem;
              align-items: center;
              justify-content: center;
          }
          .pie-svg-container { position: relative; width: 200px; height: 200px; }
          .pie-center-label {
              position: absolute;
              inset: 0;
              display: flex;
              flex-direction: column;
              align-items: center;
              justify-content: center;
              pointer-events: none;
          }
          .pie-center-amount { font-size: 1.1rem; font-weight: 700; color: var(--text-dark); }
          .pie-center-sub    { font-size: 0.75rem; color: var(--text-secondary); }
          .pie-legend {
              display: flex;
              flex-direction: column;
              gap: 0.9rem;
              min-width: 210px;
          }
          .legend-row { display: flex; align-items: center; gap: 0.75rem; }
          .legend-dot  { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; }
          .legend-label { font-size: 0.88rem; color: var(--text-light); flex: 1; }
          .legend-value { font-size: 0.9rem; font-weight: 700; color: var(--text-dark); }
          .legend-pct   { font-size: 0.8rem; color: var(--text-secondary); min-width: 38px; text-align: right; }
          .pie-period-tabs {
              display: flex;
              gap: 0.5rem;
              margin-bottom: 1.5rem;
              flex-wrap: wrap;
          }
          .pie-tab {
              padding: 0.45rem 1.1rem;
              border-radius: 8px;
              border: 2px solid var(--border-light);
              background: var(--bg-light);
              color: var(--text-dark);
              font-weight: 600;
              font-size: 0.85rem;
              cursor: pointer;
              transition: all 0.25s ease;
          }
          .pie-tab.active {
              border-color: var(--accent-emerald);
              background: linear-gradient(135deg, #50C878, #A8E6CF);
              color: white;
          }
          .pie-view { display: none; }
          .pie-view.active { display: block; }
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
          $today      = date('Y-m-d');
          $weekStart  = date('Y-m-d', strtotime('-6 days'));
          $monthStart = date('Y-m-01');
          $monthEnd   = date('Y-m-t');
          $thisMonth  = date('Y-m');

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

          // ── Pie chart data ─────────────────────────────────────────────────
          // Today
          $pieFoodDay   = $foodToday;
          $pieTransDay  = $transpoToday;
          $savDay = $conn->prepare("SELECT COALESCE(SUM(amount),0) as s FROM savings WHERE user_id = ? AND savings_date = ?");
          $savDay->bind_param("is", $userId, $today);
          $savDay->execute();
          $pieSaveDay = (float)$savDay->get_result()->fetch_assoc()['s'];
          $savDay->close();

          // This week
          $pieFoodWeek  = get_category_spent_for_period($conn, $userId, $weekStart, $today, 'food');
          $pieTransWeek = get_category_spent_for_period($conn, $userId, $weekStart, $today, 'transpo');
          $savWeek = $conn->prepare("SELECT COALESCE(SUM(amount),0) as s FROM savings WHERE user_id = ? AND savings_date BETWEEN ? AND ?");
          $savWeek->bind_param("iss", $userId, $weekStart, $today);
          $savWeek->execute();
          $pieSaveWeek = (float)$savWeek->get_result()->fetch_assoc()['s'];
          $savWeek->close();

          // This month
          $pieFoodMonth  = get_category_spent_for_period($conn, $userId, $monthStart, $monthEnd, 'food');
          $pieTransMonth = get_category_spent_for_period($conn, $userId, $monthStart, $monthEnd, 'transpo');
          $savMonth = $conn->prepare("SELECT COALESCE(SUM(amount),0) as s FROM savings WHERE user_id = ? AND DATE_FORMAT(savings_date,'%Y-%m') = ?");
          $savMonth->bind_param("is", $userId, $thisMonth);
          $savMonth->execute();
          $pieSaveMonth = (float)$savMonth->get_result()->fetch_assoc()['s'];
          $savMonth->close();
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

          <!-- ── Spending Breakdown Pie Chart ──────────────────────────── -->
          <div class="card">
              <h2>Spending Breakdown</h2>

              <div class="pie-period-tabs">
                  <button class="pie-tab active" onclick="switchPie('day', this)">Today</button>
                  <button class="pie-tab"        onclick="switchPie('week', this)">This Week</button>
                  <button class="pie-tab"        onclick="switchPie('month', this)">This Month</button>
              </div>

              <?php
              $piePeriods = [
                  'day'   => ['food' => $pieFoodDay,   'transpo' => $pieTransDay,  'savings' => $pieSaveDay],
                  'week'  => ['food' => $pieFoodWeek,  'transpo' => $pieTransWeek, 'savings' => $pieSaveWeek],
                  'month' => ['food' => $pieFoodMonth, 'transpo' => $pieTransMonth,'savings' => $pieSaveMonth],
              ];
              foreach ($piePeriods as $period => $vals):
                  $pFood = $vals['food'];
                  $pTrans= $vals['transpo'];
                  $pSave = $vals['savings'];
                  $pTotal = $pFood + $pTrans + $pSave;
                  if ($pTotal <= 0) $pTotal = 1;
                  $hasData = ($pFood + $pTrans + $pSave) > 0;
              ?>
              <div class="pie-view <?= $period === 'day' ? 'active' : '' ?>" id="pie-<?= $period ?>">
                  <?php if (!$hasData): ?>
                      <p style="text-align:center;color:var(--text-secondary);padding:2rem 0;">
                          No transactions recorded for this period yet.
                      </p>
                  <?php else: ?>
                  <div class="pie-wrap">
                      <div class="pie-svg-container">
                          <svg width="200" height="200" viewBox="0 0 200 200" id="svg-<?= $period ?>">
                              <g id="slices-<?= $period ?>"></g>
                              <circle cx="100" cy="100" r="55" fill="var(--bg-white)"/>
                          </svg>
                          <div class="pie-center-label">
                              <span class="pie-center-amount">₱<?= number_format($pFood + $pTrans, 0) ?></span>
                              <span class="pie-center-sub">spent</span>
                          </div>
                      </div>
                      <div class="pie-legend">
                          <?php
                          $segments = [
                              ['label' => 'Food',         'value' => $pFood,  'color' => '#50C878'],
                              ['label' => 'Transport',    'value' => $pTrans, 'color' => '#4FACFE'],
                              ['label' => 'Savings',      'value' => $pSave,  'color' => '#FFA94D'],
                          ];
                          foreach ($segments as $seg):
                              if ($seg['value'] <= 0) continue;
                              $pct = round($seg['value'] / $pTotal * 100);
                          ?>
                          <div class="legend-row">
                              <span class="legend-dot" style="background:<?= $seg['color'] ?>;"></span>
                              <span class="legend-label"><?= $seg['label'] ?></span>
                              <span class="legend-value">₱<?= number_format($seg['value'], 2) ?></span>
                              <span class="legend-pct"><?= $pct ?>%</span>
                          </div>
                          <?php endforeach; ?>
                      </div>
                  </div>
                  <script>
                  (function() {
                      var segs = [
                          { value: <?= $pFood ?>,  color: '#50C878' },
                          { value: <?= $pTrans ?>, color: '#4FACFE' },
                          { value: <?= $pSave ?>,  color: '#FFA94D' },
                      ];
                      var total = <?= $pTotal ?>;
                      var cx = 100, cy = 100, r = 90;
                      var start = -Math.PI / 2;
                      var g = document.getElementById('slices-<?= $period ?>');
                      segs.forEach(function(s) {
                          if (s.value <= 0) return;
                          var slice = s.value / total;
                          var end = start + slice * 2 * Math.PI;
                          var x1 = cx + r * Math.cos(start), y1 = cy + r * Math.sin(start);
                          var x2 = cx + r * Math.cos(end),   y2 = cy + r * Math.sin(end);
                          var large = slice > 0.5 ? 1 : 0;
                          var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                          path.setAttribute('d',
                              'M ' + cx + ' ' + cy +
                              ' L ' + x1.toFixed(2) + ' ' + y1.toFixed(2) +
                              ' A ' + r + ' ' + r + ' 0 ' + large + ' 1 ' +
                              x2.toFixed(2) + ' ' + y2.toFixed(2) + ' Z');
                          path.setAttribute('fill', s.color);
                          path.setAttribute('stroke', 'var(--bg-white)');
                          path.setAttribute('stroke-width', '2');
                          g.appendChild(path);
                          start = end;
                      });
                  })();
                  </script>
                  <?php endif; ?>
              </div>
              <?php endforeach; ?>
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
              <h2>Alerts &amp; Notifications</h2>
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
      function switchPie(period, btn) {
          document.querySelectorAll('.pie-view').forEach(function(v) { v.classList.remove('active'); });
          document.querySelectorAll('.pie-tab').forEach(function(b) { b.classList.remove('active'); });
          document.getElementById('pie-' + period).classList.add('active');
          btn.classList.add('active');
      }

      function toggleTheme() {
          var html = document.documentElement;
          var newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
          html.setAttribute('data-theme', newTheme);
          document.cookie = 'theme=' + newTheme + '; path=/; max-age=31536000';
          document.querySelector('.theme-toggle').textContent = newTheme === 'dark' ? 'Light Mode' : 'Dark Mode';
      }

      window.addEventListener('load', function() {
          var theme = document.documentElement.getAttribute('data-theme') || 'light';
          document.querySelector('.theme-toggle').textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
      });
      </script>
  </body>
  </html>
  