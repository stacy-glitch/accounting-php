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

$entryDate = parse_iso_date($payload['entry_date'] ?? '');
$transactionDate = parse_iso_date($payload['transaction_date'] ?? '');
$transactionMonthRaw = trim((string) ($payload['transaction_month'] ?? ''));

if ($entryDate === null) {
  json_err('登記日期格式錯誤');
}

if ($transactionDate !== null && $transactionMonthRaw !== '') {
  json_err('實際交易日期與實際交易年月僅能擇一填寫');
}

$transactionMonth = '';
if ($transactionDate !== null) {
  $transactionMonth = normalize_transaction_month_from_iso($transactionDate);
} elseif ($transactionMonthRaw !== '') {
  $transactionMonth = normalize_transaction_month_value($transactionMonthRaw);
  if ($transactionMonth === '') {
    json_err('實際交易年月格式錯誤');
  }
} else {
  $transactionMonth = normalize_transaction_month_from_iso($entryDate);
}

$code = trim((string) ($payload['code'] ?? ''));
$subject = trim((string) ($payload['subject'] ?? ''));
$note = trim((string) ($payload['note'] ?? ''));
$advanceStatus = trim((string) ($payload['advance_status'] ?? $existing['advance_status'] ?? ''));

if ($code === '') {
  json_err('代號不得為空');
}
if ($subject === '') {
  json_err('會計科目不得為空');
}

$income = parse_non_negative_int($payload['income'] ?? 0, '收入金額');
$expense = parse_non_negative_int($payload['expense'] ?? 0, '支出金額');
$advance = parse_non_negative_int($payload['advance'] ?? 0, '代墊款');

if ($income === 0 && $expense === 0 && $advance === 0) {
  json_err('收入、支出、代墊款不可同時為 0');
}

update_entry($pdo, $id, [
  'entry_date' => $entryDate,
  'transaction_date' => $transactionDate,
  'transaction_month' => $transactionMonth,
  'code' => $code,
  'subject' => $subject,
  'note' => $note,
  'income' => $income,
  'expense' => $expense,
  'advance' => $advance,
  'advance_status' => $advanceStatus,
  'invoice_path' => $existing['invoice_path'] ?? '',
]);

$updated = fetch_entry_by_id($pdo, $id);

json_ok([
  'endpoint' => 'petty-cash/voucher_update',
  'message' => '更新成功',
  'data' => $updated,
]);

function parse_iso_date(string $value): ?string {
  $value = trim($value);
  if ($value === '') {
    return null;
  }
  $dt = DateTime::createFromFormat('Y-m-d', $value);
  if (!$dt) {
    return null;
  }
  return $dt->format('Y-m-d');
}

function parse_non_negative_int($value, string $label): int {
  if (is_string($value)) {
    $value = str_replace(',', '', trim($value));
  }
  if ($value === '' || $value === null) {
    return 0;
  }
  if (!is_numeric($value)) {
    json_err($label . '格式錯誤，僅能輸入整數');
  }
  $int = (int) round((float) $value);
  if ($int < 0) {
    json_err($label . '不可為負數');
  }
  return $int;
}
