<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/_notes_parser.php';

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

if (empty($_FILES['file'])) {
  json_err('請選擇要上傳的檔案');
}

$file = $_FILES['file'];
$errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($errorCode !== UPLOAD_ERR_OK) {
  json_err('上傳失敗：' . upload_error_message($errorCode));
}

$originalName = (string) ($file['name'] ?? 'notes');
$extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

$allowed = ['csv', 'xls', 'xlsx', 'ods', 'pdf', 'zip'];
if (!in_array($extension, $allowed, true)) {
  json_err('僅支援上傳 CSV、XLS/XLSX、ODS、PDF 或 ZIP 檔案');
}

$uploadsRoot = realpath(__DIR__ . '/../../uploads');
if ($uploadsRoot === false) {
  json_err('找不到上傳根目錄', 500);
}

$yearMonth = sprintf('%04d%02d', $year, $month);
$targetDir = $uploadsRoot . '/sales-notes/' . $yearMonth;
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
  json_err('無法建立上傳目錄');
}

$tmpPath = (string) ($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_file($tmpPath)) {
  json_err('找不到暫存檔案，請重新上傳');
}

$parseResult = notes_parse_file($tmpPath, $extension);

try {
  $savedName = save_notes_uploaded_file($tmpPath, $targetDir, $originalName, $extension);
} catch (RuntimeException $e) {
  json_err($e->getMessage());
}

$snapshot = [
  'filename' => $savedName,
  'records' => $parseResult['records'] ?? [],
  'parse_error' => $parseResult['parse_error'] ?? '',
  'saved_at' => date('c'),
];

@file_put_contents(
  $targetDir . DIRECTORY_SEPARATOR . 'latest.json',
  json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

json_ok([
  'message' => '上傳成功',
  'filename' => $savedName,
  'extension' => $extension,
  'year' => $year,
  'month' => $month,
  'records' => $parseResult['records'] ?? [],
  'parse_error' => $parseResult['parse_error'] ?? '',
]);

function save_notes_uploaded_file(string $tmpPath, string $targetDir, string $originalName, string $extension): string {
  if (!is_uploaded_file($tmpPath)) {
    throw new RuntimeException('暫存檔案已失效，請重新上傳');
  }

  $base = pathinfo($originalName, PATHINFO_FILENAME);
  $normalizedBase = preg_replace('/[^A-Za-z0-9_\-]+/', '-', $base);
  if ($normalizedBase === '' || $normalizedBase === null) {
    $normalizedBase = 'notes';
  }

  $filename = sprintf(
    '%s_%s.%s',
    trim($normalizedBase, '-'),
    date('YmdHis'),
    $extension
  );

  $destination = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
  if (!move_uploaded_file($tmpPath, $destination)) {
    throw new RuntimeException('儲存原始檔案失敗');
  }

  @chmod($destination, 0664);
  return $filename;
}

function upload_error_message(int $code): string {
  switch ($code) {
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
      return '檔案超過允許大小';
    case UPLOAD_ERR_PARTIAL:
      return '檔案上傳不完整';
    case UPLOAD_ERR_NO_FILE:
      return '沒有選擇檔案';
    case UPLOAD_ERR_NO_TMP_DIR:
      return '伺服器暫存目錄不存在';
    case UPLOAD_ERR_CANT_WRITE:
      return '無法寫入伺服器磁碟';
    case UPLOAD_ERR_EXTENSION:
      return '檔案因副檔名限制被拒絕';
    default:
      return '未知的上傳錯誤';
  }
}
