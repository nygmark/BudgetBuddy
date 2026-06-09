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

    // Basic validation
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
        // Check if email exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errors[] = "Email already registered.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, monthly_budget, savings_goal) VALUES (?, ?, ?, ?, 5000.00, 10000.00)");
            $stmt->bind_param("ssss", $firstName, $lastName, $email, $hashed);

            if ($stmt->execute()) {
                $success = true;
                // Auto login
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                $_SESSION['user_email'] = $email;
                header("Location: ../frontend/dashboard.php");
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
    <title>BudgetBuddy - Sign Up</title>
</head>
<body>
    <h1>BudgetBuddy Sign Up</h1>
    <p>No design - plain functional page</p>

    <?php if ($success): ?>
        <p>Success! Redirecting...</p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <ul style="color:red;">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="POST" action="">
        <label>First Name:<br>
            <input type="text" name="firstName" required value="<?= htmlspecialchars($_POST['firstName'] ?? '') ?>">
        </label><br><br>

        <label>Last Name:<br>
            <input type="text" name="lastName" required value="<?= htmlspecialchars($_POST['lastName'] ?? '') ?>">
        </label><br><br>

        <label>Email:<br>
            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </label><br><br>

        <label>Password (min 6 chars):<br>
            <input type="password" name="password" required>
        </label><br><br>

        <label>Confirm Password:<br>
            <input type="password" name="confirm" required>
        </label><br><br>

        <button type="submit">Sign Up</button>
    </form>

    <p>Already have an account? <a href="login.php">Login here</a></p>
</body>
</html>