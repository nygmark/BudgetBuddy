<?php
  session_start();
  session_destroy();
  header("Location: frontend/menu.php");
  exit;
  ?>