<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok'       => true,
  'endpoint' => 'expenses/expense_update.php',
  'ts'       => date('c'),
], JSON_UNESCAPED_UNICODE);
