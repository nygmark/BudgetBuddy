<?php
  session_start();
  require_once __DIR__ . '/../config/db.php';

  if (!isset($_SESSION['user_id'])) {
      http_response_code(401);
      echo json_encode(['success' => false, 'error' => 'Not authenticated']);
      exit;
  }
  $userId = $_SESSION['user_id'];

  $type = $_GET['type'] ?? 'expenses'; // expenses | savings | summary

  // Decode old-style encoded rows
  function decode_row(&$row) {
      if (preg_match('/^\[([^\]]+)\]\s*(.*)$/s', $row['description'] ?? '', $m)) {
          $row['display_category'] = ucfirst($m[1]);
          $row['description']      = trim($m[2]);
      } else {
          $row['display_category'] = ucfirst($row['category'] ?? '');
      }
  }

  if ($type === 'expenses') {
      $stmt = $conn->prepare("SELECT amount, category, description, expense_date FROM expenses WHERE user_id = ? ORDER BY expense_date DESC, id DESC");
      $stmt->bind_param("i", $userId);
      $stmt->execute();
      $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmt->close();
      foreach ($rows as &$r) decode_row($r);
      unset($r);

      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename="expenses_' . date('Y-m-d') . '.csv"');
      $out = fopen('php://output', 'w');
      fputcsv($out, ['Date', 'Category', 'Description', 'Amount']);
      foreach ($rows as $r) {
          fputcsv($out, [
              $r['expense_date'],
              $r['display_category'],
              $r['description'],
              number_format((float)$r['amount'], 2, '.', '')
          ]);
      }
      fclose($out);

  } elseif ($type === 'savings') {
      $stmt = $conn->prepare("SELECT savings_date, note, amount FROM savings WHERE user_id = ? ORDER BY savings_date DESC, id DESC");
      $stmt->bind_param("i", $userId);
      $stmt->execute();
      $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmt->close();

      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename="savings_' . date('Y-m-d') . '.csv"');
      $out = fopen('php://output', 'w');
      fputcsv($out, ['Date', 'Note', 'Amount']);
      foreach ($rows as $r) {
          fputcsv($out, [$r['savings_date'], $r['note'], number_format((float)$r['amount'], 2, '.', '')]);
      }
      fclose($out);

  } elseif ($type === 'summary') {
      // Monthly summary per category
      $stmt = $conn->prepare("
          SELECT DATE_FORMAT(expense_date,'%Y-%m') as month,
                 category,
                 COUNT(*) as entries,
                 COALESCE(SUM(amount),0) as total
          FROM expenses
          WHERE user_id = ?
          GROUP BY month, category
          ORDER BY month DESC, total DESC
      ");
      $stmt->bind_param("i", $userId);
      $stmt->execute();
      $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmt->close();

      $savStmt = $conn->prepare("
          SELECT DATE_FORMAT(savings_date,'%Y-%m') as month,
                 COUNT(*) as entries,
                 COALESCE(SUM(amount),0) as total
          FROM savings
          WHERE user_id = ?
          GROUP BY month
          ORDER BY month DESC
      ");
      $savStmt->bind_param("i", $userId);
      $savStmt->execute();
      $savRows = $savStmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $savStmt->close();

      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename="monthly_summary_' . date('Y-m-d') . '.csv"');
      $out = fopen('php://output', 'w');
      fputcsv($out, ['Month', 'Type', 'Category', 'Entries', 'Total Amount']);
      foreach ($rows as $r) {
          fputcsv($out, [$r['month'], 'Expense', ucfirst($r['category']), $r['entries'], number_format((float)$r['total'], 2, '.', '')]);
      }
      foreach ($savRows as $r) {
          fputcsv($out, [$r['month'], 'Savings', '—', $r['entries'], number_format((float)$r['total'], 2, '.', '')]);
      }
      fclose($out);

  } else {
      http_response_code(400);
      echo json_encode(['success' => false, 'error' => 'Unknown export type']);
  }
  ?>