<?php
declare(strict_types=1);

require_once __DIR__ . '/_entries.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
if ($method !== 'POST') {
  json_err('Method not allowed', 405);
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload) || !$payload) {
  $payload = $_POST;
}

$id = isset($payload['id']) ? (int) $payload['id'] : 0;
if ($id <= 0) {
  json_err('缺少紀錄編號');
}

$pdo = pdo();
ensure_entries_table($pdo);

$existing = fetch_entry_by_id($pdo, $id);
if (!$existing) {
  json_err('資料不存在或已被刪除', 404);
}

$invoicePath = $existing['invoice_path'] ?? '';
$deleted = delete_entry($pdo, $id);
if (!$deleted) {
  json_err('刪除失敗，請稍後再試');
}

if ($invoicePath) {
  $absolute = __DIR__ . '/../../' . ltrim($invoicePath, '/');
  if (is_file($absolute)) {
    @unlink($absolute);
  }
}

json_ok([
  'endpoint' => 'petty-cash/voucher_delete',
  'message' => '刪除成功',
  'deleted' => $id,
]);
