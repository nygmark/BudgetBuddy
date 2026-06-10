<?php
  require_once __DIR__ . '/../includes/auth_check.php';
  require_once __DIR__ . '/../includes/helpers.php';

  $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
  setcookie('theme', $theme, time() + (86400 * 365), '/');

  $msg       = isset($_GET['msg'])   ? $_GET['msg']   : '';
  $err       = isset($_GET['err'])   ? $_GET['err']   : '';
  $popup     = isset($_GET['popup']) ? $_GET['popup'] : '';
  $popupGoal = isset($_GET['goal'])  ? $_GET['goal']  : '';
  $today = date('Y-m-d');

  // ── create / update goal ─────────────────────────────────────────────────────
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_goal') {
      $gName     = trim($_POST['goal_name'] ?? '');
      $gTarget   = max(0, floatval($_POST['goal_target'] ?? 0));
      $gDeadline = $_POST['goal_deadline'] ?? null;
      if ($gDeadline === '') $gDeadline = null;

      if (empty($gName)) {
          $err = "Goal name cannot be empty.";
      } elseif ($gTarget <= 0) {
          $err = "Target amount must be greater than 0.";
      } else {
          $upd = $conn->prepare("UPDATE users SET goal_name = ?, goal_target = ?, goal_deadline = ? WHERE id = ?");
          $upd->bind_param("sdsi", $gName, $gTarget, $gDeadline, $userId);
          $upd->execute();
          $upd->close();
          header("Location: saving-goal.php?msg=" . urlencode("Saving goal saved successfully."));
          exit;
      }
  }

  // ── delete goal (reset to blank) ─────────────────────────────────────────────
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_goal') {
      $upd = $conn->prepare("UPDATE users SET goal_name = NULL, goal_target = 0, goal_deadline = NULL WHERE id = ?");
      $upd->bind_param("i", $userId);
      $upd->execute();
      $upd->close();
      header("Location: saving-goal.php?msg=" . urlencode("Goal removed."));
      exit;
  }

  // ── add savings ──────────────────────────────────────────────────────────────
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_savings') {
      $sAmount = floatval($_POST['savings_amount'] ?? 0);
      $sNote   = trim($_POST['savings_note'] ?? '');
      $sDate   = $_POST['savings_date'] ?? $today;
      if ($sAmount > 0) {
          $ins = $conn->prepare("INSERT INTO savings (user_id, amount, note, savings_date) VALUES (?, ?, ?, ?)");
          $ins->bind_param("idss", $userId, $sAmount, $sNote, $sDate);
          if ($ins->execute()) {
              $msg = "Savings added.";
              // Check if savings goal is now reached
              $newTotal   = get_total_savings($conn, $userId);
              $gTarget    = (float)($currentUser['goal_target'] ?? 0);
              $gName      = $currentUser['goal_name'] ?? '';
              $popupParam = '';
              if ($gTarget > 0 && $gName !== '' && $newTotal >= $gTarget) {
                  $popupParam = '&popup=goal_reached&goal=' . urlencode($gName);
              }
          } else {
              $err = "Could not add savings.";
              $popupParam = '';
          }
          $ins->close();
      } else {
          $err = "Invalid savings amount.";
          $popupParam = '';
      }
      header("Location: saving-goal.php?msg=" . urlencode($msg) . "&err=" . urlencode($err) . ($popupParam ?? ''));
      exit;
  }

  // ── load goal data ────────────────────────────────────────────────────────────
  // Re-fetch fresh data after any update
  $fresh = $conn->prepare("SELECT goal_name, goal_target, goal_deadline FROM users WHERE id = ? LIMIT 1");
  $fresh->bind_param("i", $userId);
  $fresh->execute();
  $gRow = $fresh->get_result()->fetch_assoc();
  $fresh->close();

  $goalName     = $gRow['goal_name'] ?? '';
  $goalTarget   = (float)($gRow['goal_target'] ?? 0);
  $goalDeadline = $gRow['goal_deadline'] ?? null;

  // Has a real goal only when both name and positive target are set
  $hasGoal = ($goalName !== '' && $goalName !== null && $goalTarget > 0);

  $totalSavings  = $hasGoal ? get_total_savings($conn, $userId) : 0;
  $progress      = ($hasGoal && $goalTarget > 0) ? min(100, round($totalSavings / $goalTarget * 100)) : 0;
  $remaining     = $hasGoal ? max(0, $goalTarget - $totalSavings) : 0;
  $recentSavings = $hasGoal ? get_recent_savings($conn, $userId, 10) : [];
  ?>
  <!DOCTYPE html>
  <html data-theme="<?= $theme ?>">
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>BudgetBuddy - Saving Goals</title>
      <link rel="stylesheet" href="styles.css">
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
                  <h1><a href="menu.php" style="color:inherit;text-decoration:none;">BudgetBuddy</a></h1>
                  <p>Achieve Your Goals</p>
              </div>
          </div>
          <div class="header-right">
              <button class="theme-toggle" onclick="toggleTheme()">Dark Mode</button>
          </div>
      </header>

      <div style="padding:0 2rem;">
          <nav class="nav">
              <a href="dashboard.php">Dashboard</a>
              <a href="expense-tracker.php">Expenses</a>
              <a href="budget-limits.php">Budget</a>
              <a href="saving-goal.php" class="active">Goals</a>
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

          <?php if (!$hasGoal): ?>
          <!-- No goal yet — show Create form -->
          <div class="card">
              <h2>Create Your Savings Goal</h2>
              <p style="color:var(--text-secondary);margin-bottom:1.5rem;">
                  Set a goal to start tracking your savings progress.
              </p>
              <form method="POST">
                  <input type="hidden" name="action" value="update_goal">
                  <div class="form-group">
                      <label for="goal_name">Goal Name</label>
                      <input type="text" id="goal_name" name="goal_name" required
                             placeholder="e.g., Emergency Fund, Vacation, New Phone">
                  </div>
                  <div class="form-row">
                      <div class="form-group">
                          <label for="goal_target">Target Amount (&#8369;)</label>
                          <input type="number" id="goal_target" step="0.01" name="goal_target"
                                 required placeholder="0.00" min="1">
                      </div>
                      <div class="form-group">
                          <label for="goal_deadline">Deadline <span style="color:var(--text-secondary)">(optional)</span></label>
                          <input type="date" id="goal_deadline" name="goal_deadline">
                      </div>
                  </div>
                  <button type="submit" class="btn btn-primary">Create Goal</button>
              </form>
          </div>

          <?php else: ?>
          <!-- Goal exists — show progress, edit, add savings -->
          <div class="card">
              <h2>Current Goal: <?= htmlspecialchars($goalName) ?></h2>
              <div class="progress-bar">
                  <div class="progress-fill" style="width:<?= $progress ?>%;">
                      <span class="progress-text"><?= $progress ?>%</span>
                  </div>
              </div>
              <div class="goal-details">
                  <div class="goal-item">
                      <span class="goal-label">Saved So Far</span>
                      <span class="goal-value">&#8369;<?= number_format($totalSavings, 2) ?></span>
                  </div>
                  <div class="goal-item">
                      <span class="goal-label">Target</span>
                      <span class="goal-value">&#8369;<?= number_format($goalTarget, 2) ?></span>
                  </div>
                  <div class="goal-item">
                      <span class="goal-label">Remaining</span>
                      <span class="goal-value">&#8369;<?= number_format($remaining, 2) ?></span>
                  </div>
                  <?php if ($goalDeadline): ?>
                  <div class="goal-item">
                      <span class="goal-label">Deadline</span>
                      <span class="goal-value"><?= htmlspecialchars($goalDeadline) ?></span>
                  </div>
                  <?php endif; ?>
              </div>

              <div style="display:flex;gap:1rem;margin-top:1.5rem;">
                  <button class="btn btn-secondary" onclick="toggleEditForm()" style="flex:1;">Edit Goal</button>
                  <form method="POST" onsubmit="return confirm('Remove this goal? Your savings history will be kept.');" style="flex:0 0 auto;">
                      <input type="hidden" name="action" value="delete_goal">
                      <button type="submit" class="btn btn-danger" style="white-space:nowrap;">Remove Goal</button>
                  </form>
              </div>

              <div id="editForm" class="goal-edit-form" style="display:none;margin-top:1.5rem;">
                  <h3 style="margin-bottom:1.5rem;color:var(--text-dark);">Update Your Goal</h3>
                  <form method="POST">
                      <input type="hidden" name="action" value="update_goal">
                      <div class="form-group">
                          <label>Goal Name</label>
                          <input type="text" name="goal_name" value="<?= htmlspecialchars($goalName) ?>" required>
                      </div>
                      <div class="form-row">
                          <div class="form-group">
                              <label>Target Amount</label>
                              <input type="number" step="0.01" name="goal_target" value="<?= htmlspecialchars($goalTarget) ?>" required>
                          </div>
                          <div class="form-group">
                              <label>Deadline</label>
                              <input type="date" name="goal_deadline" value="<?= htmlspecialchars($goalDeadline ?? '') ?>">
                          </div>
                      </div>
                      <div class="edit-btn-group">
                          <button type="submit" class="btn btn-primary">Save Changes</button>
                          <button type="button" class="btn btn-secondary" onclick="toggleEditForm()">Cancel</button>
                      </div>
                  </form>
              </div>
          </div>

          <div class="card">
              <h2>Add Savings</h2>
              <form method="POST">
                  <input type="hidden" name="action" value="add_savings">
                  <div class="form-row">
                      <div class="form-group">
                          <label>Amount</label>
                          <input type="number" step="0.01" name="savings_amount" required placeholder="0.00">
                      </div>
                      <div class="form-group">
                          <label>Date</label>
                          <input type="date" name="savings_date" value="<?= $today ?>">
                      </div>
                  </div>
                  <div class="form-group">
                      <label>Note</label>
                      <input type="text" name="savings_note" placeholder="e.g., Monthly savings">
                  </div>
                  <button type="submit" class="btn btn-primary">Add to Savings</button>
              </form>
          </div>

          <div class="card">
              <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem;">
                    <h2 style="margin:0;">Recent Contributions</h2>
                    <a href="../api/export.php?type=savings" class="btn btn-primary" style="font-size:0.85rem;padding:0.45rem 1rem;text-decoration:none;">&#8595; Export Savings (CSV)</a>
                </div>
              <?php if (!count($recentSavings)): ?>
                  <p style="text-align:center;padding:2rem;color:var(--text-secondary);">No savings recorded yet.</p>
              <?php else: ?>
                  <div class="table-wrapper">
                      <table class="table">
                          <thead><tr><th>Date</th><th>Amount</th><th>Note</th></tr></thead>
                          <tbody>
                          <?php foreach ($recentSavings as $s): ?>
                              <tr>
                                  <td><?= htmlspecialchars($s['savings_date']) ?></td>
                                  <td><strong>&#8369;<?= number_format($s['amount'], 2) ?></strong></td>
                                  <td><?= htmlspecialchars($s['note'] ?? '-') ?></td>
                              </tr>
                          <?php endforeach; ?>
                          </tbody>
                      </table>
                  </div>
              <?php endif; ?>
          </div>
          <?php endif; ?>
      </div>

      <script>
      function toggleTheme() {
          var html = document.documentElement;
          var t = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
          html.setAttribute('data-theme', t);
          document.cookie = 'theme=' + t + '; path=/; max-age=31536000';
          document.querySelector('.theme-toggle').textContent = t === 'dark' ? 'Light Mode' : 'Dark Mode';
      }
      function toggleEditForm() {
          var f = document.getElementById('editForm');
          f.style.display = f.style.display === 'none' ? 'block' : 'none';
      }
      window.addEventListener('load', function() {
          var t = document.documentElement.getAttribute('data-theme') || 'light';
          document.querySelector('.theme-toggle').textContent = t === 'dark' ? 'Light Mode' : 'Dark Mode';
          <?php if ($popup === 'goal_reached' && $popupGoal): ?>
          showNotif('success',
              '🎉 Savings Goal Reached!',
              'Congratulations! You have reached your savings goal "<?= htmlspecialchars(addslashes($popupGoal)) ?>"! Keep up the great work!'
          );
          <?php endif; ?>
      });

      /* ── Popup notification ─────────────────────────────── */
      function showNotif(type, title, message) {
          var colors = { warning: '#E67E22', success: '#27AE60', info: '#2980B9' };
          var overlay = document.getElementById('notif-overlay');
          var popup   = document.getElementById('notif-popup');
          document.getElementById('notif-bar').style.background     = colors[type] || colors.info;
          document.getElementById('notif-title').textContent         = title;
          document.getElementById('notif-message').textContent       = message;
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
          <h3 id="notif-title"   style="margin:0 0 0.75rem;font-size:1.15rem;color:var(--text-dark,#fff);"></h3>
          <p  id="notif-message" style="margin:0 0 1.5rem;color:var(--text-secondary,#aaa);font-size:0.95rem;line-height:1.5;"></p>
          <button onclick="closeNotif()" class="btn btn-primary" style="min-width:120px;">OK, Got it!</button>
      </div>
  </div>

  </body>
  </html>
  