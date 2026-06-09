<?php
  /**
   * BudgetBuddy - Admin Account Setup
   * Run this ONCE to create the first admin account, then DELETE this file.
   * Access: yourdomain.com/admin/setup.php
   */
  require_once __DIR__ . '/../config/db.php';

  // Check if admins table exists and has any admin yet
  $check = $conn->query("SELECT COUNT(*) as cnt FROM admins");
  $row = $check ? $check->fetch_assoc() : null;
  $hasAdmin = $row && ((int)$row['cnt'] > 0);

  $msg = '';
  $error = '';

  if ($hasAdmin && !isset($_GET['force'])) {
      $msg = 'An admin account already exists. Setup is disabled. Delete this file from your server.';
  } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $username = trim($_POST['username'] ?? '');
      $password = $_POST['password'] ?? '';
      $confirm  = $_POST['confirm'] ?? '';
      $secret   = $_POST['setup_secret'] ?? '';

      // Simple protection: require a setup secret key defined below
      define('SETUP_SECRET', 'budget_setup_2024'); // Change this before running!

      if ($secret !== SETUP_SECRET) {
          $error = 'Invalid setup secret key.';
      } elseif (strlen($username) < 3) {
          $error = 'Username must be at least 3 characters.';
      } elseif (strlen($password) < 6) {
          $error = 'Password must be at least 6 characters.';
      } elseif ($password !== $confirm) {
          $error = 'Passwords do not match.';
      } else {
          $hashed = password_hash($password, PASSWORD_DEFAULT);
          // Delete any existing admins if forcing
          if ($hasAdmin) {
              $conn->query("DELETE FROM admins");
          }
          $stmt = $conn->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
          $stmt->bind_param("ss", $username, $hashed);
          if ($stmt->execute()) {
              $msg = "Admin account created successfully! Delete this file (admin/setup.php) from your server now.";
          } else {
              $error = "Failed to create admin: " . $conn->error;
          }
          $stmt->close();
      }
  }
  ?>
  <!DOCTYPE html>
  <html>
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>BudgetBuddy - Admin Setup</title>
      <link rel="stylesheet" href="../frontend/styles.css">
  </head>
  <body>
  <div class="auth-container">
      <div class="auth-card">
          <h1>BudgetBuddy</h1>
          <p class="subtitle">Admin Account Setup</p>
          <div class="alert alert-warning" style="margin-bottom:1.5rem;">
              <strong>Warning:</strong> Delete this file after creating your admin account!
          </div>

          <?php if ($msg): ?>
              <div class="alert alert-success"><strong>Done:</strong> <?= htmlspecialchars($msg) ?></div>
          <?php elseif ($error): ?>
              <div class="alert alert-error"><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <?php if (!$hasAdmin || isset($_GET['force'])): ?>
          <form method="POST">
              <div class="form-group">
                  <label>Admin Username</label>
                  <input type="text" name="username" required placeholder="admin" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
              </div>
              <div class="form-group">
                  <label>Password</label>
                  <input type="password" name="password" required placeholder="At least 6 characters">
              </div>
              <div class="form-group">
                  <label>Confirm Password</label>
                  <input type="password" name="confirm" required placeholder="Repeat password">
              </div>
              <div class="form-group">
                  <label>Setup Secret Key</label>
                  <input type="text" name="setup_secret" required placeholder="budget_setup_2024">
                  <small>Default secret key: <strong>budget_setup_2024</strong> (edit setup.php to change)</small>
              </div>
              <button type="submit" class="btn btn-primary" style="width:100%;">Create Admin Account</button>
          </form>
          <?php endif; ?>
      </div>
  </div>
  </body>
  </html>
  