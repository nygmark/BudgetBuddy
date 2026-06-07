<?php
session_start();
include("../config/db.php");

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
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, monthly_budget, savings_goal, food_daily_limit, transpo_daily_limit, goal_name, goal_target) VALUES (?, ?, ?, ?, 5000, 10000, 150, 100, 'My Savings Goal', 10000)");
            $stmt->bind_param("ssss", $firstName, $lastName, $email, $hashed);

            if ($stmt->execute()) {
                $success = true;
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                $_SESSION['user_email'] = $email;
                header("Location: dashboard.php");
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
<html>
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
            <p class="subtitle">Create Account</p>

            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $e): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">First Name</label>
                        <input type="text" id="firstName" name="firstName" required value="<?= htmlspecialchars($_POST['firstName'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="lastName">Last Name</label>
                        <input type="text" id="lastName" name="lastName" required value="<?= htmlspecialchars($_POST['lastName'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <small>Minimum 6 characters</small>
                </div>

                <div class="form-group">
                    <label for="confirm">Confirm Password</label>
                    <input type="password" id="confirm" name="confirm" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Login</a></p>
            </div>
        </div>
    </div>
</body>
</html>