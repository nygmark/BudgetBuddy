<?php
  // Loads DB credentials from .env file at project root.
  // Copy .env.example to .env and fill in your values before running.
  $envFile = dirname(__DIR__) . '/.env';
  if (file_exists($envFile)) {
      $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      foreach ($lines as $line) {
          if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
          [$key, $val] = explode('=', $line, 2);
          $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
      }
  }

  $host   = $_ENV['DB_HOST'] ?? 'localhost';
  $user   = $_ENV['DB_USER'] ?? 'root';
  $pass   = $_ENV['DB_PASS'] ?? '';
  $dbname = $_ENV['DB_NAME'] ?? 'budget_buddy';

  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  try {
      $conn = new mysqli($host, $user, $pass, $dbname);
      $conn->set_charset('utf8mb4');
  } catch (mysqli_sql_exception $e) {
      http_response_code(500);
      error_log('DB connection failed: ' . $e->getMessage());
      die(json_encode(['success' => false, 'error' => 'Database connection failed']));
  }
  ?>