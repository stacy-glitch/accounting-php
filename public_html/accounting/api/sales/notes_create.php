<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/_notes_storage.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_err('Method not allowed', 405);
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
  json_err('請提供有效的 JSON 資料');
}

$year = filter_var($payload['year'] ?? null, FILTER_VALIDATE_INT);
$month = filter_var($payload['month'] ?? null, FILTER_VALIDATE_INT);
if (!is_int($year) || $year < 2000 || $year > 2100) {
  json_err('請提供正確的年份');
}
if (!is_int($month) || $month < 1 || $month > 12) {
  json_err('請提供正確的月份');
}

$customerCode = trim((string)($payload['customer_code'] ?? $payload['customer'] ?? ''));
$customerName = trim((string)($payload['customer_name'] ?? ''));
$noteNumber = trim((string)($payload['note_number'] ?? $payload['number'] ?? ''));
if ($noteNumber === '') {
  json_err('請輸入票號');
}

$amount = notes_parse_amount($payload['amount'] ?? null);
if ($amount <= 0) {
  json_err('請輸入有效的金額');
}

$entryDate = notes_normalize_text($payload['entry_date'] ?? '');
$dueDate = notes_normalize_text($payload['due_date'] ?? '');
$depositDate = notes_normalize_text($payload['deposit_date'] ?? '');
$months = notes_normalize_months($payload['months'] ?? []);
if (empty($months)) {
  json_err('請至少選擇一個帳款月份');
}
$note = notes_normalize_text($payload['note'] ?? '');

$record = [
  'customer' => $customerCode !== '' ? $customerCode : $customerName,
  'customer_code' => $customerCode,
  'customer_name' => $customerName,
  'note_number' => $noteNumber,
  'amount' => $amount,
  'total' => $amount,
  'entry_date' => $entryDate,
  'due_date' => $dueDate,
  'deposit_date' => $depositDate,
  'months' => $months,
  'note' => $note,
  'created_at' => date('c'),
];
$record = notes_assign_record_key($record);

$uploadsRoot = realpath(__DIR__ . '/../../uploads');
if ($uploadsRoot === false) {
  json_err('找不到上傳目錄，無法儲存資料', 500);
}

$yearMonth = sprintf('%04d%02d', $year, $month);
$targetDir = $uploadsRoot . '/sales-notes/' . $yearMonth;
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
  json_err('無法建立儲存目錄，請確認權限', 500);
}
$snapshotPath = $targetDir . '/latest.json';

$snapshot = [
  'filename' => 'manual_entries.json',
  'records' => [],
  'parse_error' => '',
];

if (is_file($snapshotPath)) {
  $json = file_get_contents($snapshotPath);
  if ($json !== false) {
    $existing = json_decode($json, true);
    if (is_array($existing)) {
      $snapshot['filename'] = $existing['filename'] ?? $snapshot['filename'];
      $snapshot['parse_error'] = $existing['parse_error'] ?? '';
      if (!empty($existing['records']) && is_array($existing['records'])) {
        $snapshot['records'] = $existing['records'];
      }
    }
  }
}

$snapshot['records'][] = $record;
$snapshot['saved_at'] = date('c');

$jsonData = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($jsonData === false) {
  json_err('儲存資料時發生錯誤', 500);
}

if (file_put_contents($snapshotPath, $jsonData, LOCK_EX) === false) {
  json_err('寫入資料失敗，請稍後再試', 500);
}

json_ok([
  'message' => '新增應收票據成功',
  'record' => $record,
  'count' => count($snapshot['records']),
]);
