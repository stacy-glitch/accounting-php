<?php
// 最小通用輸出
function json_ok($payload = [], $extra = []) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(array_merge(['ok'=>true,'ts'=>date('c')], $payload, $extra), JSON_UNESCAPED_UNICODE);
  exit;
}
function json_err($message, $code = 400) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false,'error'=>$message,'ts'=>date('c')], JSON_UNESCAPED_UNICODE);
  exit;
}
// 建立 PDO（讀取 api/config.php）
function pdo() {
  $cfg = @include __DIR__.'/config.php';
  if (!is_array($cfg)) json_err('config.php missing or invalid', 500);
  $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $cfg['db_host'], $cfg['db_port'], $cfg['db_name'], $cfg['charset'] ?? 'utf8mb4'
  );
  try {
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // 以防萬一
    $pdo->exec("SET NAMES " . ($cfg['charset'] ?? 'utf8mb4'));
    return $pdo;
  } catch (Throwable $e) {
    json_err('DB connect failed: '.$e->getMessage(), 500);
  }
}
// 取得 GET 參數（帶預設）
function get($key, $def=null){ return isset($_GET[$key]) ? $_GET[$key] : $def; }
