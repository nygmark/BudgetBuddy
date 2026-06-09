<?php
  require_once __DIR__ . '/auth_check.php'; // sets $adminUsername, $conn

  $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
  setcookie('theme', $theme, time() + (86400 * 365), '/');

  // Fetch all users ordered by newest first
  $result = $conn->query("
      SELECT
          u.id,
          u.first_name,
          u.last_name,
          u.email,
          u.created_at,
          COALESCE(e.expense_count, 0) AS expense_count,
          COALESCE(e.total_expenses, 0) AS total_expenses,
          COALESCE(s.savings_count, 0) AS savings_count,
          COALESCE(s.total_savings, 0) AS total_savings
      FROM users u
      LEFT JOIN (
          SELECT user_id, COUNT(*) AS expense_count, SUM(amount) AS total_expenses
          FROM expenses GROUP BY user_id
      ) e ON e.user_id = u.id
      LEFT JOIN (
          SELECT user_id, COUNT(*) AS savings_count, SUM(amount) AS total_savings
          FROM savings GROUP BY user_id
      ) s ON s.user_id = u.id
      ORDER BY u.created_at DESC
  ");

  $users = [];
  while ($row = $result->fetch_assoc()) {
      $users[] = $row;
  }
  $totalUsers = count($users);
  ?>
  <!DOCTYPE html>
  <html data-theme="<?= $theme ?>">
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>BudgetBuddy - Admin Users</title>
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
          }
          .search-bar {
              width: 100%;
              padding: 0.75rem 1rem;
              border: 2px solid var(--border-light);
              border-radius: 10px;
              font-size: 0.95rem;
              background: var(--bg-white);
              color: var(--text-dark);
              margin-bottom: 1.5rem;
              transition: all 0.3s ease;
          }
          .search-bar:focus {
              outline: none;
              border-color: var(--accent-emerald);
              box-shadow: 0 0 0 3px rgba(80, 200, 120, 0.1);
          }
          .user-count-badge {
              display: inline-block;
              background: var(--primary-mint);
              color: var(--text-dark);
              font-weight: 700;
              font-size: 0.85rem;
              padding: 0.2rem 0.7rem;
              border-radius: 20px;
              margin-left: 0.5rem;
          }
          .joined-date {
              font-size: 0.88rem;
              font-weight: 600;
              color: var(--text-dark);
          }
          .joined-time {
              font-size: 0.78rem;
              color: var(--text-secondary);
              margin-top: 2px;
          }
          .avatar {
              width: 36px;
              height: 36px;
              border-radius: 50%;
              background: linear-gradient(135deg, #50C878, #A8E6CF);
              display: flex;
              align-items: center;
              justify-content: center;
              font-weight: 700;
              font-size: 0.85rem;
              color: white;
              flex-shrink: 0;
          }
          .user-name-cell {
              display: flex;
              align-items: center;
              gap: 0.75rem;
          }
          .user-name-text strong { display: block; font-size: 0.95rem; }
          .user-name-text span   { font-size: 0.82rem; color: var(--text-secondary); }
          .no-users {
              text-align: center;
              color: var(--text-secondary);
              padding: 3rem 1rem;
              font-size: 1rem;
          }
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
          <a href="dashboard.php">Analytics</a>
          <a href="users.php" class="active">Users</a>
          <a href="logout.php" class="nav-logout">Logout</a>
      </nav>
  </div>

  <div class="container">
      <div class="card">
          <div class="section-title">
              All Registered Users
              <span class="user-count-badge"><?= $totalUsers ?></span>
          </div>

          <input type="text" class="search-bar" id="searchInput"
                 placeholder="Search by name or email..." oninput="filterUsers()">

          <?php if (empty($users)): ?>
              <div class="no-users">No users registered yet.</div>
          <?php else: ?>
          <div class="table-wrapper">
              <table class="table" id="usersTable">
                  <thead>
                      <tr>
                          <th>#</th>
                          <th>User</th>
                          <th>Date &amp; Time Joined</th>
                          <th>Expenses</th>
                          <th>Total Spent</th>
                          <th>Savings</th>
                          <th>Total Saved</th>
                      </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($users as $i => $u):
                      $initials = strtoupper(substr($u['first_name'],0,1) . substr($u['last_name'],0,1));
                      // Format: 15-Jun-2025  10:32 AM
                      $dt = new DateTime($u['created_at']);
                      $dateStr = $dt->format('d-M-Y');
                      $timeStr = $dt->format('h:i A');
                  ?>
                  <tr class="user-row">
                      <td style="color:var(--text-secondary);font-size:0.85rem;"><?= $i + 1 ?></td>
                      <td>
                          <div class="user-name-cell">
                              <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                              <div class="user-name-text">
                                  <strong><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></strong>
                                  <span><?= htmlspecialchars($u['email']) ?></span>
                              </div>
                          </div>
                      </td>
                      <td>
                          <div class="joined-date"><?= $dateStr ?></div>
                          <div class="joined-time"><?= $timeStr ?></div>
                      </td>
                      <td><?= (int)$u['expense_count'] ?></td>
                      <td>₱<?= number_format((float)$u['total_expenses'], 2) ?></td>
                      <td><?= (int)$u['savings_count'] ?></td>
                      <td>₱<?= number_format((float)$u['total_savings'], 2) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  </tbody>
              </table>
          </div>
          <?php endif; ?>
      </div>
  </div>

  <script>
  function filterUsers() {
      const q = document.getElementById('searchInput').value.toLowerCase();
      document.querySelectorAll('#usersTable .user-row').forEach(function(row) {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(q) ? '' : 'none';
      });
  }

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
  