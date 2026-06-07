<?php
session_start();

$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';

session_destroy();

header("Location: login.php");
exit;
?>