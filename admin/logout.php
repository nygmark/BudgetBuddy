<?php
  if (session_status() === PHP_SESSION_NONE) {
      session_start();
  }
  unset($_SESSION['admin_id'], $_SESSION['admin_username']);
  // Only destroy the session if no user is logged in either
  if (empty($_SESSION)) {
      session_destroy();
  }
  header("Location: login.php");
  exit;
  ?>
  