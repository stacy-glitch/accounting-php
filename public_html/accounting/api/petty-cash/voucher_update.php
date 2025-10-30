<?php
declare(strict_types=1);

require_once __DIR__ . '/_balances.php';
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

$year = isset($payload['year']) ? (int) $payload['year'] : (int) date('Y');
$month = isset($payload['month']) ? (int) $payload['month'] : (int) date('n');
validate_period($year, $month);

$data = normalize_entry_payload($payload, ['existing' => $existing]);

update_entry($pdo, $id, [
  'entry_date' => $data['entry_date'],
  'transaction_date' => $data['transaction_date'],
  'transaction_month' => $data['transaction_month'],
  'code' => $data['code'],
  'subject' => $data['subject'],
  'note' => $data['note'],
  'income' => $data['income'],
  'expense' => $data['expense'],
  'advance_income' => $data['advance_income'],
  'advance_expense' => $data['advance_expense'],
  'advance_status' => $data['advance_status'],
  'invoice_path' => $existing['invoice_path'] ?? '',
]);

$updated = fetch_entry_by_id($pdo, $id);

json_ok([
  'endpoint' => 'petty-cash/voucher_update',
  'message' => '更新成功',
  'data' => $updated,
]);
