<?php
  session_start();
  if (isset($_SESSION['user_id'])) {
      header("Location: dashboard.php");
      exit;
  }
  $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
  setcookie('theme', $theme, time() + (86400 * 365), '/');
  ?>
  <!DOCTYPE html>
  <html data-theme="<?= $theme ?>">
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>BudgetBuddy</title>
      <link rel="stylesheet" href="styles.css">
      <style>
          body { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem; text-align: center; }
          .menu-logo-wrap { display: flex; align-items: center; justify-content: center; gap: 1.25rem; margin-bottom: 1.5rem; }
          .menu-logo-icon { width: 72px; height: 72px; background: linear-gradient(135deg, #50C878, #A8E6CF); border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; color: white; box-shadow: 0 8px 32px rgba(80,200,120,0.35); overflow: hidden; border: 3px solid rgba(255,255,255,0.4); flex-shrink: 0; }
          .menu-logo-icon img { width: 100%; height: 100%; object-fit: cover; }
          .menu-brand { font-size: 2.8rem; font-weight: 800; color: var(--text-dark); letter-spacing: -1px; text-decoration: none; }
          .menu-brand:hover { color: #50C878; }
          .menu-welcome { font-size: 2.2rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.6rem; line-height: 1.25; }
          .menu-tagline { font-size: 1.25rem; color: var(--text-light); margin-bottom: 0.75rem; font-weight: 500; }
          .menu-sub { font-size: 1.05rem; color: var(--text-secondary); margin-bottom: 2.5rem; max-width: 420px; }
          .menu-buttons { display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; }
          .menu-btn { display: inline-block; text-decoration: none; font-size: 1.05rem; font-weight: 700; padding: 0.85rem 2.5rem; border-radius: 12px; transition: all 0.25s ease; cursor: pointer; letter-spacing: 0.3px; }
          .menu-btn-primary { background: linear-gradient(135deg, #50C878, #A8E6CF); color: white; box-shadow: 0 6px 24px rgba(80,200,120,0.35); border: none; }
          .menu-btn-primary:hover { transform: translateY(-3px); box-shadow: 0 10px 32px rgba(80,200,120,0.45); }
          .menu-btn-outline { background: var(--bg-white); color: var(--text-dark); border: 2px solid var(--border-light); }
          .menu-btn-outline:hover { transform: translateY(-3px); border-color: #50C878; color: #50C878; box-shadow: 0 6px 20px rgba(80,200,120,0.15); }
          .menu-features { display: flex; gap: 1.25rem; margin-top: 3.5rem; flex-wrap: wrap; justify-content: center; max-width: 640px; }
          .menu-feature-chip { background: var(--bg-white); border: 1px solid var(--border-light); border-radius: 50px; padding: 0.45rem 1.2rem; font-size: 0.88rem; color: var(--text-light); font-weight: 500; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
          .theme-corner { position: fixed; top: 1.25rem; right: 1.5rem; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: var(--text-dark); padding: 0.55rem 1.1rem; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: all 0.25s ease; }
          .theme-corner:hover { background: rgba(255,255,255,0.35); }
          @media (max-width: 480px) { .menu-brand { font-size: 2rem; } .menu-welcome { font-size: 1.7rem; } .menu-buttons { flex-direction: column; align-items: center; } .menu-btn { width: 100%; max-width: 280px; text-align: center; } }
      </style>
  </head>
  <body>

      <button class="theme-corner" id="themeBtn" onclick="toggleTheme()">Dark Mode</button>

      <div class="menu-logo-wrap">
          <div class="menu-logo-icon">
              <img src="logo.png" alt="BB" onerror="this.style.display='none'; this.parentElement.textContent='BB';">
          </div>
          <a href="menu.php" class="menu-brand">BudgetBuddy</a>
      </div>

      <h1 class="menu-welcome">Welcome to BudgetBuddy!</h1>
      <p class="menu-tagline">Your personal finance companion</p>
      <p class="menu-sub">Track your expenses, set savings goals, and take control of your budget — all in one place.</p>

      <div class="menu-buttons">
          <a href="login.php" class="menu-btn menu-btn-primary">Log In</a>
          <a href="signup.php" class="menu-btn menu-btn-outline">Sign Up</a>
      </div>

      <div class="menu-features">
          <span class="menu-feature-chip">Expense Tracking</span>
          <span class="menu-feature-chip">Savings Goals</span>
          <span class="menu-feature-chip">Budget Limits</span>
          <span class="menu-feature-chip">Analytics Dashboard</span>
      </div>

      <script>
          var btn = document.getElementById('themeBtn');
          var html = document.documentElement;

          function applyLabel() {
              btn.textContent = html.getAttribute('data-theme') === 'dark' ? 'Light Mode' : 'Dark Mode';
          }

          function toggleTheme() {
              var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
              html.setAttribute('data-theme', next);
              document.cookie = 'theme=' + next + ';path=/;max-age=' + (86400 * 365);
              applyLabel();
          }

          applyLabel();
      </script>
  </body>
  </html>