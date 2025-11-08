<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/_klsb_parser.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_err('Method not allowed', 405);
}

$year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);
$month = filter_input(INPUT_POST, 'month', FILTER_VALIDATE_INT);
if (!is_int($year) || $year < 2000 || $year > 2100) {
  json_err('請提供正確的年份');
}
if (!is_int($month) || $month < 1 || $month > 12) {
  json_err('請提供正確的月份');
}

if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
  json_err('請選擇要上傳的檔案');
}

$file = $_FILES['file'];
$ext = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
$uploadsRoot = realpath(__DIR__ . '/../../uploads');
if ($uploadsRoot === false) {
  json_err('找不到上傳根目錄', 500);
}

$yearMonth = sprintf('%04d%02d', $year, $month);
$targetDir = $uploadsRoot . '/klsb/' . $yearMonth;
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
  json_err('無法建立儲存目錄', 500);
}

$filename = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file['name']);
$destination = $targetDir . '/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $destination)) {
  json_err('儲存檔案失敗，請稍後再試');
}

$records = [];
$parseError = '';
if (in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
  $parsed = klsb_parse_file($destination, $ext);
  $records = $parsed['records'];
  $parseError = $parsed['parse_error'];
} else {
  $parseError = '此格式暫無法解析，已儲存原檔供人工輸入。';
}

if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
  $records[] = [
    'transaction_date' => '',
    'description' => '附件：' . $file['name'],
    'expense' => 0,
    'income' => 0,
    'balance' => 0,
    'note' => '已上傳 ' . $file['name'] . '，請人工補登。',
    'reconciliation' => '',
    'form' => '',
  ];
}

$snapshotPath = $targetDir . '/latest.json';
$snapshot = [
  'filename' => $filename,
  'records' => $records,
  'parse_error' => $parseError,
  'saved_at' => date('c'),
];
file_put_contents($snapshotPath, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

json_ok([
  'message' => '上傳完成，已更新 ' . count($records) . ' 筆資料',
  'records' => $records,
  'parse_error' => $parseError,
]);
