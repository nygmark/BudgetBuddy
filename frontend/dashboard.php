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
              <a href="profile.php">Profile</a>
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
          $goalTarget   = (float)($currentUser['goal_target'] ?? 0);
          $goalName     = $currentUser['goal_name'] ?? '';
          $goalDeadline = $currentUser['goal_deadline'] ?? null;
          $hasGoal      = ($goalName !== '' && $goalName !== null && $goalTarget > 0);
          $progress     = ($hasGoal && $goalTarget > 0) ? min(100, round($totalSavings / $goalTarget * 100)) : 0;
          $remainingGoal = $hasGoal ? max(0, $goalTarget - $totalSavings) : 0;

          $foodLimit    = (float)($currentUser['food_daily_limit'] ?? 150);
          $transpoLimit = (float)($currentUser['transpo_daily_limit'] ?? 100);
          $foodToday    = get_daily_spent($conn, $userId, $today, 'food');
          $transpoToday = get_daily_spent($conn, $userId, $today, 'transpo');

          $overFood    = $foodToday > $foodLimit;
          $overTranspo = $transpoToday > $transpoLimit;

          // ── Pie chart data (all categories, grouped) ──────────────────────
          function get_pie_data($conn, $userId, $start, $end) {
              $s = $conn->prepare("SELECT category, description, amount FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?");
              $s->bind_param("iss", $userId, $start, $end);
              $s->execute();
              $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
              $s->close();
              $out = [];
              foreach ($rows as $r) {
                  // Decode category encoded in description (e.g. [shopping] notes)
                  if (preg_match('/^\[([^\]]+)\]/', $r['description'] ?? '', $m)) {
                      $cat = strtolower(trim($m[1]));
                  } else {
                      $cat = $r['category'];
                  }
                  $out[$cat] = ($out[$cat] ?? 0) + (float)$r['amount'];
              }
              return $out;
          }
          function get_savings_sum($conn, $userId, $start, $end) {
              $s = $conn->prepare("SELECT COALESCE(SUM(amount),0) as t FROM savings WHERE user_id = ? AND savings_date BETWEEN ? AND ?");
              $s->bind_param("iss", $userId, $start, $end);
              $s->execute();
              $r = (float)$s->get_result()->fetch_assoc()['t'];
              $s->close();
              return $r;
          }

          $piePeriods = [
              'day'   => ['exp' => get_pie_data($conn, $userId, $today,      $today),      'sav' => get_savings_sum($conn, $userId, $today,      $today)],
              'week'  => ['exp' => get_pie_data($conn, $userId, $weekStart,  $today),      'sav' => get_savings_sum($conn, $userId, $weekStart,  $today)],
              'month' => ['exp' => get_pie_data($conn, $userId, $monthStart, $monthEnd),   'sav' => get_savings_sum($conn, $userId, $monthStart, $monthEnd)],
          ];

          $allCatMeta = [
              'food'          => ['label' => 'Food',           'color' => '#50C878'],
              'transpo'       => ['label' => 'Transport',      'color' => '#4FACFE'],
              'shopping'      => ['label' => 'Shopping',       'color' => '#9B59B6'],
              'health'        => ['label' => 'Health',         'color' => '#E74C3C'],
              'entertainment' => ['label' => 'Entertainment',  'color' => '#E67E22'],
              'utilities'     => ['label' => 'Utilities',      'color' => '#2980B9'],
              'education'     => ['label' => 'Education',      'color' => '#16A085'],
              'others'        => ['label' => 'Others',         'color' => '#95A5A6'],
          ];
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

              <?php foreach ($piePeriods as $period => $pData):
                    $expMap      = $pData['exp'];
                    $pSave       = $pData['sav'];
                    $pSpent      = array_sum($expMap);
                    $pTotal      = $pSpent + $pSave;
                    $hasData     = $pTotal > 0;
                    $renderTotal = $pTotal > 0 ? $pTotal : 1;
                    $segments    = [];
                    foreach ($allCatMeta as $catKey => $catInfo) {
                        $val = $expMap[$catKey] ?? 0;
                        if ($val > 0) $segments[] = ['label' => $catInfo['label'], 'value' => $val, 'color' => $catInfo['color']];
                    }
                    if ($pSave > 0) $segments[] = ['label' => 'Savings', 'value' => $pSave, 'color' => '#FFA94D'];
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
                                <span class="pie-center-amount">&#8369;<?= number_format($pSpent, 0) ?></span>
                                <span class="pie-center-sub">spent</span>
                            </div>
                        </div>
                        <div class="pie-legend">
                            <?php foreach ($segments as $seg):
                                $pct = round($seg['value'] / $renderTotal * 100); ?>
                            <div class="legend-row">
                                <span class="legend-dot" style="background:<?= $seg['color'] ?>;"></span>
                                <span class="legend-label"><?= $seg['label'] ?></span>
                                <span class="legend-value">&#8369;<?= number_format($seg['value'], 2) ?></span>
                                <span class="legend-pct"><?= $pct ?>%</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <script>
                    (function() {
                        var segs = <?= json_encode(array_map(function($s){ return ['value'=>(float)$s['value'],'color'=>$s['color']]; }, $segments)) ?>;
                        var total = <?= (float)$renderTotal ?>;
                        var cx = 100, cy = 100, r = 90;
                        var angle = -Math.PI / 2;
                        var g = document.getElementById('slices-<?= $period ?>');
                        segs.forEach(function(s) {
                            if (s.value <= 0) return;
                            var slice = s.value / total;
                            var end   = angle + slice * 2 * Math.PI;
                            var x1 = cx + r * Math.cos(angle), y1 = cy + r * Math.sin(angle);
                            var x2 = cx + r * Math.cos(end),   y2 = cy + r * Math.sin(end);
                            var large = slice > 0.5 ? 1 : 0;
                            var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                            path.setAttribute('d','M '+cx+' '+cy+' L '+x1.toFixed(2)+' '+y1.toFixed(2)+' A '+r+' '+r+' 0 '+large+' 1 '+x2.toFixed(2)+' '+y2.toFixed(2)+' Z');
                            path.setAttribute('fill', s.color);
                            path.setAttribute('stroke', 'var(--bg-white)');
                            path.setAttribute('stroke-width', '2');
                            g.appendChild(path);
                            angle = end;
                        });
                    })();
                    </script>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
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
                  <?php if ($hasGoal && $totalSavings >= $goalTarget): ?>
                      <div class="alert alert-success">
                          <strong>Congratulations!</strong> You have reached your savings goal "<?= htmlspecialchars($goalName) ?>"!
                      </div>
                  <?php elseif ($hasGoal): ?>
                      <div class="alert alert-info">
                          <strong>Info:</strong> Keep saving towards your goal "<?= htmlspecialchars($goalName) ?>". You are <?= $progress ?>% there!
                      </div>
                  <?php else: ?>
                      <div class="alert alert-info">
                          <strong>Tip:</strong> Head to the Goals page to set up a savings goal and track your progress.
                      </div>
                  <?php endif; ?>
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
          <?php
          $dashMonthlyBudget = (float)($currentUser['monthly_budget'] ?? 0);
          $dashOverBudget    = $dashMonthlyBudget > 0 && $monthTotal > $dashMonthlyBudget;
          $dashGoalReached   = $hasGoal && $totalSavings >= $goalTarget;
          if ($dashOverBudget): ?>
          showNotif('warning',
              '⚠️ Monthly Budget Exceeded!',
              'You have spent ₱<?= number_format($monthTotal,2) ?> this month, which is over your ₱<?= number_format($dashMonthlyBudget,2) ?> budget.'
          );
          <?php elseif ($dashGoalReached): ?>
          showNotif('success',
              '🎉 Savings Goal Reached!',
              'Congratulations! You have reached your savings goal "<?= htmlspecialchars(addslashes($goalName)) ?>"!'
          );
          <?php endif; ?>
      });

      /* ── Popup notification ─────────────────────────────── */
      function showNotif(type, title, message) {
          var colors = { warning: '#E67E22', success: '#27AE60', info: '#2980B9' };
          document.getElementById('notif-bar').style.background = colors[type] || colors.info;
          document.getElementById('notif-title').textContent    = title;
          document.getElementById('notif-message').textContent  = message;
          document.getElementById('notif-overlay').style.display = 'flex';
          document.getElementById('notif-popup').style.display   = 'block';
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
          <h3 id="notif-title"   style="margin:0 0 0.75rem;font-size:1.15rem;color:var(--text-dark,#fff);"></h3>
          <p  id="notif-message" style="margin:0 0 1.5rem;color:var(--text-secondary,#aaa);font-size:0.95rem;line-height:1.5;"></p>
          <button onclick="closeNotif()" class="btn btn-primary" style="min-width:120px;">OK, Got it!</button>
      </div>
  </div>

  </body>
  </html>
  