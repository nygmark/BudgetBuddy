<?php
// Quick test for password hashing (used during development)
$plain = "123456";
$hash = password_hash($plain, PASSWORD_DEFAULT);

echo "Plain: $plain<br>";
echo "New hash: $hash<br>";
echo "Verify with new hash: " . (password_verify($plain, $hash) ? "OK" : "FAIL") . "<br>";

// Test the one from schema
$schemaHash = '$2y$10$h75/nMds//4xwOc3WBh.Z.DNarU94HJSstOSHEmpQIp7EPxNLb3CS';
echo "Verify schema sample hash: " . (password_verify($plain, $schemaHash) ? "OK - use john@example.com / 123456" : "FAIL - reimport schema or reset password") . "<br>";
?>