<?php
session_start();
include("../config/db.php");

$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
setcookie('theme', $theme, time() + (86400 * 365), '/');

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email required.";
    }
    if ($password === '') {
        $errors[] = "Password required.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $errors[] = "User not found.";
        } else {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_email'] = $user['email'];
                $success = true;
                header("Location: dashboard.php");
                exit;
            } else {
                $errors[] = "Invalid password.";
            }
        }
        $stmt->close();
    }
}

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BudgetBuddy - Login</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>BudgetBuddy</h1>
            <p class="subtitle">Smart Money Management</p>

            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $e): ?>
                    <div class="alert alert-error">
                        <strong>Error:</strong> <?= htmlspecialchars($e) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="your@email.com">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="••••••••">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Login to Account</button>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a href="signup.php">Create one now</a></p>
            </div>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        }

        window.addEventListener('load', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        });
    </script>
</body>
</html>