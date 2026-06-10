<?php
  require_once __DIR__ . '/../includes/auth_check.php';
  require_once __DIR__ . '/../includes/helpers.php';

  $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
  setcookie('theme', $theme, time() + (86400 * 365), '/');

  $msg = isset($_GET['msg']) ? $_GET['msg'] : '';
  $err = isset($_GET['err']) ? $_GET['err'] : '';

  $today      = date('Y-m-d');
  $thisMonth  = date('Y-m');
  $weekStart  = date('Y-m-d', strtotime('-6 days'));

  // ── Handle save budget ─────────────────────────────────────────────────────
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_budget') {
      $budgetAmt    = max(0, floatval($_POST['budget_amount'] ?? 0));
      $budgetPeriod = in_array($_POST['budget_period'] ?? '', ['daily','weekly','monthly'])
                      ? $_POST['budget_period'] : 'monthly';

      $upd = $conn->prepare("UPDATE users SET monthly_budget = ?, budget_period = ? WHERE id = ?");
      $upd->bind_param("dsi", $budgetAmt, $budgetPeriod, $userId);
      if ($upd->execute()) {
          $msg = "Budget updated successfully.";
          $currentUser['monthly_budget']  = $budgetAmt;
          $currentUser['budget_period']   = $budgetPeriod;
      } else {
          $err = "Failed to update budget.";
      }
      $upd->close();
      header("Location: budget-limits.php?msg=" . urlencode($msg) . "&err=" . urlencode($err));
      exit;
  }

  // ── Read current budget settings ───────────────────────────────────────────
  $budgetAmount = (float)($currentUser['monthly_budget'] ?? 5000);
  $budgetPeriod = $currentUser['budget_period'] ?? 'monthly';

  // ── Compute spending for the current period ────────────────────────────────
  switch ($budgetPeriod) {
      case 'daily':
          $periodLabel = 'Today';
          $foodSpent   = get_daily_spent($conn, $userId, $today, 'food');
          $transSpent  = get_daily_spent($conn, $userId, $today, 'transpo');
          // Savings for today
          $savStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as s FROM savings WHERE user_id = ? AND savings_date = ?");
          $savStmt->bind_param("is", $userId, $today);
          break;
      case 'weekly':
          $periodLabel = 'This Week (last 7 days)';
          $foodSpent   = get_category_spent_for_period($conn, $userId, $weekStart, $today, 'food');
          $transSpent  = get_category_spent_for_period($conn, $userId, $weekStart, $today, 'transpo');
          $savStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as s FROM savings WHERE user_id = ? AND savings_date BETWEEN ? AND ?");
          $savStmt->bind_param("iss", $userId, $weekStart, $today);
          break;
      default: // monthly
          $periodLabel  = 'This Month (' . date('F Y') . ')';
          $foodSpent    = get_monthly_spent($conn, $userId) - get_category_spent_for_period($conn, $userId, date('Y-m-01'), $today, 'transpo');
          // Actually compute each separately for accuracy
          $foodSpent    = get_category_spent_for_period($conn, $userId, date('Y-m-01'), date('Y-m-t'), 'food');
          $transSpent   = get_category_spent_for_period($conn, $userId, date('Y-m-01'), date('Y-m-t'), 'transpo');
          $savStmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as s FROM savings WHERE user_id = ? AND DATE_FORMAT(savings_date,'%Y-%m') = ?");
          $savStmt->bind_param("is", $userId, $thisMonth);
          break;
  }
  $savStmt->execute();
  $savedAmount = (float)$savStmt->get_result()->fetch_assoc()['s'];
  $savStmt->close();

  $totalSpent   = get_weekly_spent($conn, $userId); // sums ALL categories
  // override per period:
  if ($budgetPeriod === 'daily') $totalSpent = get_daily_spent($conn, $userId, $today);
  elseif ($budgetPeriod === 'monthly') $totalSpent = get_monthly_spent($conn, $userId);
  $remaining    = max(0, $budgetAmount - $totalSpent);
  $isOver       = $totalSpent > $budgetAmount;
  $spentPct     = $budgetAmount > 0 ? min(100, round($totalSpent / $budgetAmount * 100)) : 0;

  // Pie chart values (food, transpo, savings, remaining budget)
  $pieFood   = $foodSpent;
  $pieTransp = $transSpent;
  $pieOthers = max(0, $totalSpent - $foodSpent - $transSpent);
  $pieSave   = $savedAmount;
  $pieRem    = max(0, $budgetAmount - $totalSpent);
  $pieTotal  = $pieFood + $pieTransp + $pieOthers + $pieSave + $pieRem;
  if ($pieTotal <= 0) $pieTotal = 1; // avoid division by zero
  ?>
  <!DOCTYPE html>
  <html data-theme="<?= $theme ?>">
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>BudgetBuddy - Budget</title>
      <link rel="stylesheet" href="styles.css">
      <style>
          .period-radio-group {
              display: flex;
              gap: 1rem;
              flex-wrap: wrap;
              margin-top: 0.5rem;
          }
          .period-radio-label {
              display: flex;
              align-items: center;
              gap: 0.5rem;
              cursor: pointer;
              font-weight: 600;
              padding: 0.55rem 1.2rem;
              border-radius: 10px;
              border: 2px solid var(--border-light);
              background: var(--bg-light);
              color: var(--text-dark);
              transition: all 0.25s ease;
              user-select: none;
          }
          .period-radio-label input[type="radio"] { display: none; }
          .period-radio-label.selected,
          .period-radio-label:has(input:checked) {
              border-color: var(--accent-emerald);
              background: linear-gradient(135deg, #50C878, #A8E6CF);
              color: white;
          }
          .pie-wrap {
              display: flex;
              flex-wrap: wrap;
              gap: 2rem;
              align-items: center;
              justify-content: center;
          }
          .pie-svg-container { position: relative; }
          .pie-center-label {
              position: absolute;
              inset: 0;
              display: flex;
              flex-direction: column;
              align-items: center;
              justify-content: center;
              pointer-events: none;
          }
          .pie-center-amount { font-size: 1.15rem; font-weight: 700; color: var(--text-dark); }
          .pie-center-sub    { font-size: 0.75rem; color: var(--text-secondary); }
          .pie-legend {
              display: flex;
              flex-direction: column;
              gap: 0.85rem;
              min-width: 210px;
          }
          .legend-row {
              display: flex;
              align-items: center;
              gap: 0.75rem;
          }
          .legend-dot {
              width: 14px; height: 14px;
              border-radius: 50%;
              flex-shrink: 0;
          }
          .legend-label { font-size: 0.88rem; color: var(--text-light); flex: 1; }
          .legend-value { font-size: 0.9rem; font-weight: 700; color: var(--text-dark); }
          .legend-pct   { font-size: 0.8rem; color: var(--text-secondary); min-width: 36px; text-align: right; }
          .empty-pie-msg { text-align: center; color: var(--text-secondary); padding: 2rem; }
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
                  <h1><a href="menu.php" style="color:inherit;text-decoration:none;">BudgetBuddy</a></h1>
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
              <a href="profile.php">Profile</a>
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

          <!-- ── Set Budget ──────────────────────────────────────────────── -->
          <div class="card">
              <h2>Set Your Budget</h2>
              <form method="POST" id="budgetForm">
                  <input type="hidden" name="action" value="update_budget">
                  <div class="form-row">
                      <div class="form-group">
                          <label for="budget_amount">Budget Amount (₱)</label>
                          <input type="number" id="budget_amount" step="0.01" min="0"
                                 name="budget_amount"
                                 value="<?= htmlspecialchars($budgetAmount) ?>" required
                                 placeholder="e.g. 5000">
                          <small>How much you plan to spend in the selected period.</small>
                      </div>
                      <div class="form-group">
                          <label>Budget Period</label>
                          <div class="period-radio-group" id="periodGroup">
                              <?php foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $val => $label): ?>
                              <label class="period-radio-label <?= $budgetPeriod === $val ? 'selected' : '' ?>">
                                  <input type="radio" name="budget_period" value="<?= $val ?>"
                                         <?= $budgetPeriod === $val ? 'checked' : '' ?>>
                                  <?= $label ?>
                              </label>
                              <?php endforeach; ?>
                          </div>
                          <small>Choose how often this budget resets.</small>
                      </div>
                  </div>
                  <button type="submit" class="btn btn-primary">Save Budget</button>
              </form>
          </div>

          <!-- ── Budget Usage ────────────────────────────────────────────── -->
          <div class="card">
              <h2>Budget Usage — <span style="font-weight:400;font-size:1rem;color:var(--text-secondary);"><?= htmlspecialchars($periodLabel) ?></span></h2>

              <div class="progress-bar" style="margin-bottom:1rem;">
                  <div class="progress-fill"
                       style="width:<?= $spentPct ?>%; background: linear-gradient(90deg, <?= $isOver ? '#FF6B6B, #FF9999' : '#50C878, #A8E6CF' ?>);">
                      <span class="progress-text"><?= $spentPct ?>%</span>
                  </div>
              </div>

              <div class="goal-details">
                  <div class="goal-item">
                      <span class="goal-label">Budget</span>
                      <span class="goal-value">₱<?= number_format($budgetAmount, 2) ?></span>
                  </div>
                  <div class="goal-item">
                      <span class="goal-label">Total Spent</span>
                      <span class="goal-value" style="color:<?= $isOver ? 'var(--danger-red)' : 'var(--text-dark)' ?>;">
                          ₱<?= number_format($totalSpent, 2) ?>
                      </span>
                  </div>
                  <div class="goal-item">
                      <span class="goal-label">Food</span>
                      <span class="goal-value">₱<?= number_format($foodSpent, 2) ?></span>
                  </div>
                  <div class="goal-item">
                      <span class="goal-label">Transport</span>
                      <span class="goal-value">₱<?= number_format($transSpent, 2) ?></span>
                  </div>
                  <div class="goal-item">
                      <span class="goal-label">Remaining</span>
                      <span class="goal-value" style="color:<?= $isOver ? 'var(--danger-red)' : 'var(--success-green)' ?>;">
                          <?= $isOver ? '-' : '' ?>₱<?= number_format($isOver ? ($totalSpent - $budgetAmount) : $remaining, 2) ?>
                      </span>
                  </div>
                  <div class="goal-item">
                      <span class="goal-label">Saved</span>
                      <span class="goal-value" style="color:var(--accent-emerald);">₱<?= number_format($savedAmount, 2) ?></span>
                  </div>
              </div>

              <?php if ($isOver): ?>
                  <div style="margin-top:1.5rem;padding:1rem;border-radius:12px;background:#F8D7DA;border-left:4px solid var(--danger-red);">
                      <strong style="color:var(--danger-red);">Over Budget</strong>
                      <p style="margin-top:0.5rem;color:var(--text-dark);">
                          You spent ₱<?= number_format($totalSpent - $budgetAmount, 2) ?> more than your <?= htmlspecialchars($budgetPeriod) ?> budget.
                      </p>
                  </div>
              <?php else: ?>
                  <div style="margin-top:1.5rem;padding:1rem;border-radius:12px;background:#D4EDDA;border-left:4px solid var(--success-green);">
                      <strong style="color:var(--success-green);">Within Budget</strong>
                      <p style="margin-top:0.5rem;color:var(--text-dark);">
                          You still have ₱<?= number_format($remaining, 2) ?> left in your <?= htmlspecialchars($budgetPeriod) ?> budget.
                      </p>
                  </div>
              <?php endif; ?>
          </div>

          <!-- ── Spending Breakdown Pie Chart ───────────────────────────── -->
          <div class="card">
              <h2>Spending Breakdown — <?= htmlspecialchars($periodLabel) ?></h2>
              <?php
              $hasData = ($pieFood + $pieTransp + $pieSave + $pieRem) > 0;
              ?>
              <?php if (!$hasData): ?>
                  <p class="empty-pie-msg">No transactions yet for this period. Add some expenses or savings to see the chart.</p>
              <?php else: ?>
              <div class="pie-wrap">
                  <!-- SVG Pie Chart (generated via JS below) -->
                  <div class="pie-svg-container" style="width:200px;height:200px;">
                      <svg id="pieChart" width="200" height="200" viewBox="0 0 200 200">
                          <g id="pieSlices"></g>
                          <!-- donut hole -->
                          <circle cx="100" cy="100" r="55" fill="var(--bg-white)"/>
                      </svg>
                      <div class="pie-center-label">
                          <span class="pie-center-amount">₱<?= number_format($totalSpent, 0) ?></span>
                          <span class="pie-center-sub">spent</span>
                      </div>
                  </div>

                  <div class="pie-legend">
                      <?php
                      $segments = [
                          ['label' => 'Food',         'value' => $pieFood,   'color' => '#50C878'],
                          ['label' => 'Transport',     'value' => $pieTransp, 'color' => '#4FACFE'],
                          ['label' => ['label' => 'Others',           'value' => $pieOthers,  'color' => '#9B59B6'],
                          'Savings',       'value' => $pieSave,   'color' => '#FFA94D'],
                          ['label' => 'Remaining Budget','value' => $pieRem,  'color' => '#E8EDF2'],
                      ];
                      foreach ($segments as $seg):
                          if ($seg['value'] <= 0) continue;
                          $pct = round($seg['value'] / $pieTotal * 100);
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
                  const segments = [
                      { value: <?= $pieFood ?>,   color: '#50C878' },
                      { value: <?= $pieTransp ?>, color: '#4FACFE' },
                      { value: <?= $pieSave ?>,   color: '#FFA94D' },
                      { value: <?= $pieRem ?>,    color: '#E8EDF2' },
                  ];
                  const total = <?= $pieTotal ?>;
                  const cx = 100, cy = 100, r = 90;
                  let startAngle = -Math.PI / 2;
                  const g = document.getElementById('pieSlices');

                  segments.forEach(function(seg) {
                      if (seg.value <= 0) return;
                      const slice = seg.value / total;
                      const endAngle = startAngle + slice * 2 * Math.PI;

                      const x1 = cx + r * Math.cos(startAngle);
                      const y1 = cy + r * Math.sin(startAngle);
                      const x2 = cx + r * Math.cos(endAngle);
                      const y2 = cy + r * Math.sin(endAngle);
                      const largeArc = slice > 0.5 ? 1 : 0;

                      const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                      path.setAttribute('d',
                          `M ${cx} ${cy} L ${x1.toFixed(2)} ${y1.toFixed(2)} A ${r} ${r} 0 ${largeArc} 1 ${x2.toFixed(2)} ${y2.toFixed(2)} Z`
                      );
                      path.setAttribute('fill', seg.color);
                      path.setAttribute('stroke', 'var(--bg-white)');
                      path.setAttribute('stroke-width', '2');
                      g.appendChild(path);
                      startAngle = endAngle;
                  });
              })();
              </script>
              <?php endif; ?>
          </div>

      </div><!-- /container -->

      <script>
      // Highlight selected period radio button
      document.querySelectorAll('.period-radio-label input[type="radio"]').forEach(function(radio) {
          radio.addEventListener('change', function() {
              document.querySelectorAll('.period-radio-label').forEach(function(lbl) {
                  lbl.classList.remove('selected');
              });
              radio.closest('.period-radio-label').classList.add('selected');
          });
      });

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
  