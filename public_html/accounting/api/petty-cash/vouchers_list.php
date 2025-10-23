<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok'       => true,
  'endpoint' => 'petty-cash/vouchers_list.php',
  'ts'       => date('c'),
], JSON_UNESCAPED_UNICODE);
