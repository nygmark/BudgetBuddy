<?php
  require_once __DIR__ . '/../includes/auth_check.php';

  $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
  setcookie('theme', $theme, time() + (86400 * 365), '/');

  $msg = isset($_GET['msg']) ? $_GET['msg'] : '';
  $err = isset($_GET['err']) ? $_GET['err'] : '';

  // ── Handle profile update ────────────────────────────────────────────────────
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
      $firstName   = trim($_POST['first_name'] ?? '');
      $lastName    = trim($_POST['last_name']  ?? '');
      $currentPwd  = $_POST['current_password'] ?? '';
      $newPwd      = $_POST['new_password']     ?? '';
      $confirmPwd  = $_POST['confirm_password'] ?? '';

      // Validation
      if ($firstName === '' || $lastName === '') {
          $err = "First and last name are required.";
      } elseif ($currentPwd === '') {
          $err = "Current password is required to save changes.";
      } elseif (!password_verify($currentPwd, $currentUser['password'])) {
          $err = "Current password is incorrect.";
      } elseif ($newPwd !== '' && strlen($newPwd) < 6) {
          $err = "New password must be at least 6 characters.";
      } elseif ($newPwd !== '' && $newPwd !== $confirmPwd) {
          $err = "New passwords do not match.";
      } else {
          // Build update query — password only changes if new one supplied
          if ($newPwd !== '') {
              $hashed = password_hash($newPwd, PASSWORD_DEFAULT);
              $upd = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, password = ? WHERE id = ?");
              $upd->bind_param("sssi", $firstName, $lastName, $hashed, $userId);
          } else {
              $upd = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
              $upd->bind_param("ssi", $firstName, $lastName, $userId);
          }

          if ($upd->execute()) {
              // Refresh session name
              $_SESSION['user_name'] = $firstName . ' ' . $lastName;
              $msg = "Profile updated successfully.";
          } else {
              $err = "Failed to update profile.";
          }
          $upd->close();
      }

      if (!empty($msg)) {
          header("Location: profile.php?msg=" . urlencode($msg));
          exit;
      }
  }

  // Use fresh data
  $firstName = $currentUser['first_name'];
  $lastName  = $currentUser['last_name'];
  $email     = $currentUser['email'];
  ?>
  <!DOCTYPE html>
  <html data-theme="<?= $theme ?>">
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>BudgetBuddy - Profile Settings</title>
      <link rel="stylesheet" href="styles.css">
      <style>
          .profile-avatar {
              width: 72px;
              height: 72px;
              border-radius: 50%;
              background: var(--accent-green, #4CAF50);
              display: flex;
              align-items: center;
              justify-content: center;
              font-size: 2rem;
              font-weight: 700;
              color: #fff;
              margin: 0 auto 1.5rem;
              letter-spacing: 1px;
          }
          .profile-email-badge {
              display: inline-block;
              padding: 0.35rem 0.9rem;
              background: var(--card-bg, rgba(255,255,255,0.05));
              border: 1px solid var(--border-color, rgba(255,255,255,0.1));
              border-radius: 2rem;
              font-size: 0.85rem;
              color: var(--text-secondary);
              margin-bottom: 2rem;
          }
          .section-divider {
              border: none;
              border-top: 1px solid var(--border-color, rgba(255,255,255,0.1));
              margin: 1.75rem 0;
          }
          .password-hint {
              font-size: 0.8rem;
              color: var(--text-secondary);
              margin-top: 0.3rem;
          }
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
                  <p>Profile Settings</p>
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
              <a href="saving-goal.php">Goals</a>
              <a href="profile.php" class="active">Profile</a>
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

          <div class="card" style="max-width:560px;margin:0 auto;text-align:center;">
              <div class="profile-avatar"><?= strtoupper(substr($firstName,0,1) . substr($lastName,0,1)) ?></div>
              <div class="profile-email-badge">&#9993;&nbsp;<?= htmlspecialchars($email) ?></div>

              <form method="POST" style="text-align:left;">
                  <input type="hidden" name="action" value="update_profile">

                  <!-- Name fields -->
                  <div class="form-row">
                      <div class="form-group">
                          <label>First Name</label>
                          <input type="text" name="first_name" required
                                 value="<?= htmlspecialchars($firstName) ?>" placeholder="First name">
                      </div>
                      <div class="form-group">
                          <label>Last Name</label>
                          <input type="text" name="last_name" required
                                 value="<?= htmlspecialchars($lastName) ?>" placeholder="Last name">
                      </div>
                  </div>

                  <hr class="section-divider">
                  <p style="font-size:0.9rem;color:var(--text-secondary);margin-bottom:1rem;">
                      Change password &mdash; leave blank to keep current password.
                  </p>

                  <div class="form-group">
                      <label>New Password</label>
                      <input type="password" name="new_password" placeholder="Leave blank to keep current">
                      <div class="password-hint">Minimum 6 characters</div>
                  </div>
                  <div class="form-group">
                      <label>Confirm New Password</label>
                      <input type="password" name="confirm_password" placeholder="Re-enter new password">
                  </div>

                  <hr class="section-divider">
                  <p style="font-size:0.9rem;color:var(--text-secondary);margin-bottom:1rem;">
                      Enter your <strong>current password</strong> to confirm all changes.
                  </p>

                  <div class="form-group">
                      <label>Current Password <span style="color:#E74C3C;">*</span></label>
                      <input type="password" name="current_password" required
                             placeholder="Your current password">
                  </div>

                  <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem;">
                      Save Changes
                  </button>
              </form>
          </div>
      </div>

      <script>
      function toggleTheme() {
          var html = document.documentElement;
          var t = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
          html.setAttribute('data-theme', t);
          document.cookie = 'theme=' + t + '; path=/; max-age=31536000';
          document.querySelector('.theme-toggle').textContent = t === 'dark' ? 'Light Mode' : 'Dark Mode';
      }
      window.addEventListener('load', function() {
          var t = document.documentElement.getAttribute('data-theme') || 'light';
          document.querySelector('.theme-toggle').textContent = t === 'dark' ? 'Light Mode' : 'Dark Mode';
      });
      </script>
  </body>
  </html>
  