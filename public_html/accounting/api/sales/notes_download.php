<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  json_err('Method not allowed', 405);
}

$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);
$type = strtolower(trim((string) ($_GET['type'] ?? '')));

if (!is_int($year) || $year < 2000 || $year > 2100) {
  json_err('請提供正確的年份');
}
if (!is_int($month) || $month < 1 || $month > 12) {
  json_err('請提供正確的月份');
}

$uploadsRoot = realpath(__DIR__ . '/../../uploads');
if ($uploadsRoot === false) {
  json_err('找不到上傳根目錄', 500);
}

$yearMonth = sprintf('%04d%02d', $year, $month);
$targetDir = $uploadsRoot . '/sales-notes/' . $yearMonth;
if (!is_dir($targetDir)) {
  json_err('找不到對應檔案', 404);
}

$files = glob(rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*');
if (!$files) {
  json_err('尚未上傳檔案', 404);
}

if ($type !== '') {
  $filtered = [];
  foreach ($files as $path) {
    if (!is_file($path)) {
      continue;
    }
    $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === $type) {
      $filtered[] = $path;
    }
  }
  if (!$filtered) {
    json_err('找不到指定格式的檔案', 404);
  }
  $files = $filtered;
}

$latestPath = null;
$latestMtime = 0;
foreach ($files as $path) {
  if (!is_file($path)) {
    continue;
  }
  $mtime = filemtime($path) ?: 0;
  if ($mtime >= $latestMtime) {
    $latestMtime = $mtime;
    $latestPath = $path;
  }
}

if ($latestPath === null || !is_file($latestPath)) {
  json_err('檔案不存在或無法讀取', 404);
}

$filename = basename($latestPath);
$size = filesize($latestPath);
$extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

$mimeMap = [
  'csv' => 'text/csv',
  'xls' => 'application/vnd.ms-excel',
  'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
  'pdf' => 'application/pdf',
  'zip' => 'application/zip',
];
$contentType = $mimeMap[$extension] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);
if ($size !== false) {
  header('Content-Length: ' . $size);
}
header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('X-Download-Endpoint: sales/notes_download');

$sent = readfile($latestPath);
if ($sent === false) {
  exit;
}
exit;

