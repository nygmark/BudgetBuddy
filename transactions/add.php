<?php
include("../config/db.php");

// 1. Grab incoming transaction data from Thunder Client safely
$user_id          = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
$title            = isset($_POST['title']) ? trim($_POST['title']) : '';
$amount           = isset($_POST['amount']) ? trim($_POST['amount']) : '';
$type             = isset($_POST['type']) ? trim($_POST['type']) : ''; // Must be 'income' or 'expense'
$category         = isset($_POST['category']) ? trim($_POST['category']) : '';
$transaction_date = isset($_POST['transaction_date']) ? trim($_POST['transaction_date']) : ''; // YYYY-MM-DD format

// 2. Validate that no fields are left empty
if (empty($user_id) || empty($title) || empty($amount) || empty($type) || empty($category) || empty($transaction_date)) {
    die("Error: All fields are required.");
}

try {
    // 3. Insert transaction statement using prepared placeholders
    $sql = "INSERT INTO transactions (user_id, title, amount, type, category, transaction_date) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    // "i" for integer (user_id), "s" for strings/decimals
    $stmt->bind_param("isssss", $user_id, $title, $amount, $type, $category, $transaction_date);

    if ($stmt->execute()) {
        echo "Transaction added successfully";
    } else {
        echo "Error: Could not add transaction.";
    }

} catch (Exception $e) {
    // Graceful error tracking if a foreign key violates or database crashes
    echo "Error: " . $e->getMessage();
} finally {
    // 4. Structured connection cleanup
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
}
?>