<?php
include("../config/db.php");


$firstName = isset($_POST['firstName']) ? trim($_POST['firstName']) : '';
$lastName  = isset($_POST['lastName']) ? trim($_POST['lastName']) : ''; // Matches capital 'N'
$email     = isset($_POST['email']) ? trim($_POST['email']) : '';
$password  = isset($_POST['password']) ? trim($_POST['password']) : '';


if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
    die("Error: All fields are required.");
}

try {
   
    $checkSql = "SELECT id FROM users WHERE email = ? LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        die("Error: Email is already registered.");
    }
    $checkStmt->close(); 

    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    
    $sql = "INSERT INTO users (first_name, last_name, email, PASSWORD) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $firstName, $lastName, $email, $hashedPassword);

    if ($stmt->execute()) {
        echo "User registered successfully";
    } else {
        echo "Error: Could not register user.";
    }

} catch (Exception $e) {
    
    echo "Error: " . $e->getMessage();
} finally {
    
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
}
?>