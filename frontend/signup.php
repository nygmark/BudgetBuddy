<?php
  session_start();
  include("../config/db.php");

  $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
  setcookie('theme', $theme, time() + (86400 * 365), '/');

  $errors = [];
  $success = false;

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $firstName = trim($_POST['firstName'] ?? '');
      $lastName  = trim($_POST['lastName'] ?? '');
      $email     = trim($_POST['email'] ?? '');
      $password  = $_POST['password'] ?? '';
      $confirm   = $_POST['confirm'] ?? '';

      if ($firstName === '' || $lastName === '') {
          $errors[] = "First and last name are required.";
      }
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          $errors[] = "Valid email is required.";
      }
      if (strlen($password) < 6) {
          $errors[] = "Password must be at least 6 characters.";
      }
      if ($password !== $confirm) {
          $errors[] = "Passwords do not match.";
      }

      if (empty($errors)) {
          $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
          $check->bind_param("s", $email);
          $check->execute();
          $check->store_result();

          if ($check->num_rows > 0) {
              $errors[] = "Email already registered.";
          } else {
              $hashed = password_hash($password, PASSWORD_DEFAULT);
              // goal_name and goal_target are left NULL/0 — user creates their own goal later
              $stmt = $conn->prepare(
                  "INSERT INTO users (first_name, last_name, email, password, monthly_budget, savings_goal, food_daily_limit, transpo_daily_limit) " .
                  "VALUES (?, ?, ?, ?, 5000, 0, 150, 100)"
              );
              $stmt->bind_param("ssss", $firstName, $lastName, $email, $hashed);

              if ($stmt->execute()) {
                  // Do NOT log in automatically — send to login page with success message
                  $stmt->close();
                  header("Location: login.php?msg=" . urlencode("Account created! Please log in."));
                  exit;
              } else {
                  $errors[] = "Registration failed: " . $conn->error;
              }
              $stmt->close();
          }
          $check->close();
      }
  }
  ?>
  <!DOCTYPE html>
  <html data-theme="<?= $theme ?>">
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>BudgetBuddy - Sign Up</title>
      <link rel="stylesheet" href="styles.css">
  </head>
  <body>
      <div class="auth-container">
          <div class="auth-card">
              <h1>BudgetBuddy</h1>
              <p class="subtitle">Create Your Account</p>

              <?php if (!empty($errors)): ?>
                  <?php foreach ($errors as $e): ?>
                      <div class="alert alert-error">
                          <strong>Error:</strong> <?= htmlspecialchars($e) ?>
                      </div>
                  <?php endforeach; ?>
              <?php endif; ?>

              <form method="POST" action="">
                  <div class="form-row">
                      <div class="form-group">
                          <label for="firstName">First Name</label>
                          <input type="text" id="firstName" name="firstName" required value="<?= htmlspecialchars($_POST['firstName'] ?? '') ?>" placeholder="John">
                      </div>

                      <div class="form-group">
                          <label for="lastName">Last Name</label>
                          <input type="text" id="lastName" name="lastName" required value="<?= htmlspecialchars($_POST['lastName'] ?? '') ?>" placeholder="Doe">
                      </div>
                  </div>

                  <div class="form-group">
                      <label for="email">Email Address</label>
                      <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="your@email.com">
                  </div>

                  <div class="form-group">
                      <label for="password">Password</label>
                      <input type="password" id="password" name="password" required placeholder="••••••••">
                      <small>Minimum 6 characters</small>
                  </div>

                  <div class="form-group">
                      <label for="confirm">Confirm Password</label>
                      <input type="password" id="confirm" name="confirm" required placeholder="••••••••">
                  </div>

                  <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
              </form>

              <div class="auth-footer">
                  <p>Already have an account? <a href="login.php">Login here</a></p>
              </div>
          </div>
      </div>
  </body>
  </html>
  