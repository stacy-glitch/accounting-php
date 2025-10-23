<?php
header('Content-Type: application/json; charset=utf-8');

$cfg = @include __DIR__ . '/config.php';
$resp = ['ok' => true, 'ts' => date('c'), 'db_ok' => false];

try {
  $dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $cfg['db_host'] ?? '127.0.0.1',
    $cfg['db_port'] ?? '3306',
    $cfg['db_name'] ?? '',
    $cfg['charset'] ?? 'utf8mb4'
  );
  $pdo = new PDO($dsn, $cfg['db_user'] ?? '', $cfg['db_pass'] ?? '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
  $pdo->query('SELECT 1');
  $resp['db_ok'] = true;
} catch (Throwable $e) {
  $resp['db_error'] = $e->getMessage();
}

echo json_encode($resp, JSON_UNESCAPED_UNICODE);
