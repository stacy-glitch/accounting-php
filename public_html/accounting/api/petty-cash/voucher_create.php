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

$year = isset($payload['year']) ? (int) $payload['year'] : (int) date('Y');
$month = isset($payload['month']) ? (int) $payload['month'] : (int) date('n');
validate_period($year, $month);

$data = prepare_entry_data($payload);

$pdo = pdo();
ensure_entries_table($pdo);

$insertId = insert_entry($pdo, [
  'entry_date' => $data['entry_date'],
  'transaction_date' => $data['transaction_date'],
  'transaction_month' => $data['transaction_month'],
  'code' => $data['code'],
  'subject' => $data['subject'],
  'note' => $data['note'],
  'income' => $data['income'],
  'expense' => $data['expense'],
  'advance' => $data['advance'],
  'advance_status' => $data['advance_status'],
]);

json_ok([
  'endpoint' => 'petty-cash/voucher_create',
  'message' => '新增成功',
  'data' => [
    'id' => $insertId,
  ],
]);
