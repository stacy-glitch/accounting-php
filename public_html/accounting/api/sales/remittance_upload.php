<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/_remittance_parser.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_err('Method not allowed', 405);
}

if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
  json_err('請選擇要上傳的 XLSX 檔案');
}

$file = $_FILES['file'];
$extension = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, ['xlsx', 'csv'], true)) {
  json_err('目前僅支援 XLSX 或 CSV 檔案');
}

$uploadsRoot = realpath(__DIR__ . '/../../uploads');
if ($uploadsRoot === false) {
  json_err('找不到上傳目錄', 500);
}
$targetDir = $uploadsRoot . '/remittance';
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
  json_err('無法建立匯款帳號儲存目錄', 500);
}

$safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file['name']));
$filename = time() . '_' . $safeName;
$destination = $targetDir . '/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $destination)) {
  json_err('儲存檔案失敗，請稍後再試');
}

$records = [];
$parseError = '';
try {
  $parsed = remittance_parse_file($destination, $extension);
  $records = $parsed['records'];
  $parseError = $parsed['parse_error'];
} catch (Throwable $e) {
  $parseError = $e->getMessage();
}

$snapshot = [
  'filename' => $filename,
  'records' => $records,
  'parse_error' => $parseError,
  'uploaded_at' => date('c'),
];
$snapshotPath = $targetDir . '/latest.json';
file_put_contents($snapshotPath, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$message = '上傳完成';
if ($records) {
  $message .= '，已載入 ' . count($records) . ' 筆資料';
}

json_ok([
  'message' => $message,
  'records' => $records,
  'parse_error' => $parseError,
  'filename' => $filename,
]);
