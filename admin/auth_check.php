<?php
  // Admin-only session guard. Include at the top of every admin page.
  if (session_status() === PHP_SESSION_NONE) {
      session_start();
  }
  if (!isset($_SESSION['admin_id'])) {
      header("Location: login.php");
      exit;
  }
  require_once __DIR__ . '/../config/db.php';
  $adminUsername = $_SESSION['admin_username'] ?? 'Admin';
  ?>
  