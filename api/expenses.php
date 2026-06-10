<?php
  session_start();
  header('Content-Type: application/json');
  require_once __DIR__ . '/../../config/db.php';

  if (!isset($_SESSION['user_id'])) {
      http_response_code(401);
      echo json_encode(['success' => false, 'error' => 'Not authenticated']);
      exit;
  }
  $userId = $_SESSION['user_id'];
  $method = $_SERVER['REQUEST_METHOD'];

  $validCategories = ['food','transpo','shopping','health','entertainment','utilities','education','others'];

  function encode_api_expense($category, $customCat, $notes) {
      if ($category === 'food' || $category === 'transpo') {
          return ['dbCat' => $category, 'dbDesc' => $notes];
      }
      $label  = ($category === 'others') ? $customCat : $category;
      $dbDesc = '[' . $label . ']' . ($notes !== '' ? ' ' . $notes : '');
      return ['dbCat' => 'food', 'dbDesc' => $dbDesc];
  }

  function decode_api_row(&$row) {
      if (preg_match('/^\[([^\]]+)\]\s*(.*)$/s', $row['description'] ?? '', $m)) {
          $row['display_category'] = $m[1];
          $row['description']      = trim($m[2]);
      } else {
          $row['display_category'] = $row['category'];
      }
  }

  if ($method === 'GET') {
      $stmt = $conn->prepare("SELECT id, amount, category, description, expense_date FROM expenses WHERE user_id = ? ORDER BY expense_date DESC, id DESC");
      $stmt->bind_param("i", $userId);
      $stmt->execute();
      $expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmt->close();
      foreach ($expenses as &$row) decode_api_row($row);
      unset($row);
      echo json_encode(['success' => true, 'expenses' => array_values($expenses)]);
      exit;
  }

  if ($method === 'POST') {
      $input = $_POST;
      if (empty($input)) $input = json_decode(file_get_contents('php://input'), true) ?? [];

      $action = $input['action'] ?? 'add';

      if ($action === 'delete') {
          $id = intval($input['id'] ?? 0);
          if ($id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid id']); exit; }
          $del = $conn->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
          $del->bind_param("ii", $id, $userId);
          $del->execute();
          $del->close();
          echo json_encode(['success' => true, 'message' => 'Deleted']);
          exit;
      }

      $amount    = floatval($input['amount'] ?? 0);
      $category  = trim($input['category'] ?? '');
      $date      = $input['date'] ?? date('Y-m-d');
      $notes     = trim($input['notes'] ?? $input['description'] ?? '');
      $customCat = trim($input['custom_category'] ?? '');

      if ($amount <= 0 || !in_array($category, $validCategories)) {
          http_response_code(400);
          echo json_encode(['success'=>false,'error'=>'Invalid amount or category']);
          exit;
      }
      if ($category === 'others' && $customCat === '') {
          http_response_code(400);
          echo json_encode(['success'=>false,'error'=>'Custom category required for Others']);
          exit;
      }

      $enc  = encode_api_expense($category, $customCat, $notes);
      $stmt = $conn->prepare("INSERT INTO expenses (user_id, amount, category, description, expense_date) VALUES (?, ?, ?, ?, ?)");
      $stmt->bind_param("idsss", $userId, $amount, $enc['dbCat'], $enc['dbDesc'], $date);
      if ($stmt->execute()) {
          $newId = $stmt->insert_id;
          $stmt->close();
          echo json_encode(['success'=>true,'message'=>'Expense added','id'=>$newId]);
      } else {
          http_response_code(500);
          echo json_encode(['success'=>false,'error'=>'Failed to add expense']);
      }
      exit;
  }

  http_response_code(405);
  echo json_encode(['success'=>false,'error'=>'Method not allowed']);
  ?>
  