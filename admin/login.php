<?php
  if (session_status() === PHP_SESSION_NONE) {
      session_start();
  }
  require_once __DIR__ . '/../config/db.php';

  // If already logged in as admin, go to dashboard
  if (isset($_SESSION['admin_id'])) {
      header("Location: dashboard.php");
      exit;
  }

  $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
  setcookie('theme', $theme, time() + (86400 * 365), '/');

  $errors = [];

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $username = trim($_POST['username'] ?? '');
      $password = $_POST['password'] ?? '';

      if ($username === '' || $password === '') {
          $errors[] = "Username and password are required.";
      } else {
          $stmt = $conn->prepare("SELECT id, username, password FROM admins WHERE username = ? LIMIT 1");
          $stmt->bind_param("s", $username);
          $stmt->execute();
          $result = $stmt->get_result();
          $admin = $result->fetch_assoc();
          $stmt->close();

          if (!$admin || !password_verify($password, $admin['password'])) {
              $errors[] = "Invalid username or password.";
          } else {
              $_SESSION['admin_id']       = $admin['id'];
              $_SESSION['admin_username'] = $admin['username'];
              header("Location: dashboard.php");
              exit;
          }
      }
  }
  ?>
  <!DOCTYPE html>
  <html data-theme="<?= $theme ?>">
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>BudgetBuddy - Admin Login</title>
      <link rel="stylesheet" href="../frontend/styles.css">
  </head>
  <body>
      <div class="auth-container">
          <div class="auth-card">
              <h1>BudgetBuddy</h1>
              <p class="subtitle">Admin Panel</p>

              <?php foreach ($errors as $e): ?>
                  <div class="alert alert-error"><strong>Error:</strong> <?= htmlspecialchars($e) ?></div>
              <?php endforeach; ?>

              <form method="POST" action="">
                  <div class="form-group">
                      <label for="username">Admin Username</label>
                      <input type="text" id="username" name="username" required
                             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                             placeholder="admin">
                  </div>
                  <div class="form-group">
                      <label for="password">Password</label>
                      <input type="password" id="password" name="password" required placeholder="••••••••">
                  </div>
                  <button type="submit" class="btn btn-primary" style="width:100%;">Login to Admin Panel</button>
              </form>

              <div class="auth-footer">
                  <p><a href="../frontend/login.php">&#8592; Back to User Login</a></p>
              </div>
          </div>
      </div>
      <script>
          window.addEventListener('load', function () {
              const saved = localStorage.getItem('theme') || 'light';
              document.documentElement.setAttribute('data-theme', saved);
          });
      </script>
  </body>
  </html>
  