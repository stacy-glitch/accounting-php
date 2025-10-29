<?php
declare(strict_types=1);

require_once __DIR__ . '/_entries.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_err('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$payloadId = null;
if (is_array($input) && isset($input['id'])) {
  $payloadId = $input['id'];
} elseif (isset($_POST['id'])) {
  $payloadId = $_POST['id'];
}

$id = is_numeric($payloadId) ? (int) $payloadId : 0;
if ($id <= 0) {
  json_err('缺少紀錄編號');
}

$pdo = pdo();
ensure_entries_table($pdo);
$existing = fetch_entry_by_id($pdo, $id);
if (!$existing) {
  json_err('資料不存在或已被刪除', 404);
}

$relativePath = trim((string) ($existing['invoice_path'] ?? ''));
if ($relativePath !== '') {
  $absolutePath = __DIR__ . '/../../' . ltrim($relativePath, '/');
  if (is_file($absolutePath)) {
    @unlink($absolutePath);
  }
}

try {
  $pdo->beginTransaction();
  update_entry($pdo, $id, [
    'entry_date' => $existing['entry_date'],
    'transaction_date' => $existing['transaction_date'],
    'transaction_month' => $existing['transaction_month'],
    'code' => $existing['code'],
    'subject' => $existing['subject'],
    'note' => $existing['note'],
    'income' => $existing['income'],
    'expense' => $existing['expense'],
    'advance' => $existing['advance'],
    'advance_status' => $existing['advance_status'],
    'invoice_path' => '',
  ]);
  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  json_err('刪除發票失敗：' . $e->getMessage());
}

json_ok([
  'message' => '發票已刪除',
  'data' => [
    'invoice_path' => '',
    'invoice_url' => '',
  ],
]);
