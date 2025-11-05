<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
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

$allowed = ['csv', 'xlsx', 'pdf'];
if (!in_array($type, $allowed, true)) {
  json_err('不支援的檔案格式');
}

$uploadsRoot = realpath(__DIR__ . '/../../uploads');
if ($uploadsRoot === false) {
  json_err('找不到上傳根目錄', 500);
}

$yearMonth = sprintf('%04d%02d', $year, $month);
$targetDir = $uploadsRoot . '/sales/' . $yearMonth;
if (!is_dir($targetDir)) {
  json_err('找不到對應檔案', 404);
}

$pattern = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.' . $type;
$matches = glob($pattern);
if (!$matches) {
  json_err('尚未上傳此格式的檔案', 404);
}

$latestPath = null;
$latestMtime = 0;
foreach ($matches as $path) {
  $mtime = filemtime($path) ?: 0;
  if ($mtime >= $latestMtime) {
    $latestMtime = $mtime;
    $latestPath = $path;
  }
}

if ($latestPath === null || !is_file($latestPath)) {
  json_err('檔案不存在或無法讀取', 404);
}

$handle = fopen($latestPath, 'rb');
if ($handle === false) {
  json_err('無法開啟檔案', 500);
}
fclose($handle);

$filename = basename($latestPath);
$size = filesize($latestPath);

$mimeMap = [
  'csv' => 'text/csv',
  'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'pdf' => 'application/pdf',
];
$contentType = $mimeMap[$type] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);
if ($size !== false) {
  header('Content-Length: ' . $size);
}
header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('X-Download-Endpoint: sales/download');

$sent = readfile($latestPath);
if ($sent === false) {
  // 無法回傳檔案時直接結束，避免輸出混亂。
  exit;
}
exit;
