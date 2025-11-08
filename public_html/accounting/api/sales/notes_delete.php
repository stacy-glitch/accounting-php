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
$recordKey = trim((string) ($payload['record_key'] ?? ''));

if (!is_int($year) || $year < 2000 || $year > 2100) {
  json_err('請提供正確的年份');
}
if (!is_int($month) || $month < 1 || $month > 12) {
  json_err('請提供正確的月份');
}
if ($recordKey === '') {
  json_err('缺少要刪除的票據識別碼');
}

$uploadsRoot = realpath(__DIR__ . '/../../uploads');
if ($uploadsRoot === false) {
  json_err('找不到上傳根目錄', 500);
}

$yearMonth = sprintf('%04d%02d', $year, $month);
$targetDir = $uploadsRoot . '/sales-notes/' . $yearMonth;
$snapshotPath = $targetDir . '/latest.json';
if (!is_file($snapshotPath)) {
  json_err('尚未上傳此月份的票據紀錄', 404);
}

$json = file_get_contents($snapshotPath);
if ($json === false) {
  json_err('讀取票據紀錄檔案失敗', 500);
}

$snapshot = json_decode($json, true);
if (!is_array($snapshot)) {
  json_err('票據紀錄檔案格式錯誤，請重新上傳', 500);
}

$records = isset($snapshot['records']) && is_array($snapshot['records']) ? $snapshot['records'] : [];
$targetRecord = isset($payload['record']) && is_array($payload['record']) ? $payload['record'] : null;
$targetSignature = $targetRecord ? notes_record_signature($targetRecord) : '';
$removed = false;

foreach ($records as $index => $record) {
  $currentKey = $record['record_key'] ?? notes_record_signature($record);
  if ($currentKey === $recordKey || ($targetSignature !== '' && $currentKey === $targetSignature)) {
    unset($records[$index]);
    $removed = true;
    break;
  }
}

if (!$removed) {
  json_err('找不到要刪除的票據紀錄', 404);
}

$records = array_values(array_map('notes_assign_record_key', $records));
$snapshot['records'] = $records;
$snapshot['saved_at'] = date('c');

$jsonData = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($jsonData === false || file_put_contents($snapshotPath, $jsonData, LOCK_EX) === false) {
  json_err('刪除成功但寫入檔案失敗，請稍後重試', 500);
}

json_ok([
  'message' => '已刪除應收票據',
  'remaining' => count($records),
]);
