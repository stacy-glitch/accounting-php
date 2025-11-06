<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/_notes_parser.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  json_err('Method not allowed', 405);
}

$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);

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
  json_ok([
    'records' => [],
    'parse_error' => '尚未上傳任何應收票據舊檔。',
    'filename' => '',
  ]);
}

$snapshotPath = $targetDir . DIRECTORY_SEPARATOR . 'latest.json';
if (is_file($snapshotPath)) {
  $json = file_get_contents($snapshotPath);
  if ($json !== false) {
    $data = json_decode($json, true);
    if (is_array($data)) {
      json_ok([
        'records' => is_array($data['records'] ?? null) ? $data['records'] : [],
        'parse_error' => isset($data['parse_error']) ? (string) $data['parse_error'] : '',
        'filename' => isset($data['filename']) ? (string) $data['filename'] : '',
      ]);
    }
  }
}

$pattern = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*';
$files = glob($pattern);
$allowedExtensions = ['csv', 'xls', 'xlsx', 'ods', 'pdf', 'zip'];

$filtered = [];
foreach ($files as $path) {
  if (!is_file($path)) {
    continue;
  }
  $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExtensions, true)) {
    continue;
  }
  $filtered[] = $path;
}

if (!$filtered) {
  json_ok([
    'records' => [],
    'parse_error' => '尚未上傳任何應收票據舊檔。',
    'filename' => '',
  ]);
}

$latestPath = null;
$latestMtime = 0;
foreach ($filtered as $path) {
  $mtime = filemtime($path) ?: 0;
  if ($mtime >= $latestMtime) {
    $latestMtime = $mtime;
    $latestPath = $path;
  }
}

if ($latestPath === null || !is_file($latestPath)) {
  json_ok([
    'records' => [],
    'parse_error' => '尚未上傳任何應收票據舊檔。',
    'filename' => '',
  ]);
}

$extension = strtolower((string) pathinfo($latestPath, PATHINFO_EXTENSION));
$parseResult = notes_parse_file($latestPath, $extension);

$snapshot = [
  'filename' => basename($latestPath),
  'records' => $parseResult['records'] ?? [],
  'parse_error' => $parseResult['parse_error'] ?? '',
  'saved_at' => date('c', filemtime($latestPath) ?: time()),
];
@file_put_contents(
  $snapshotPath,
  json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

json_ok([
  'records' => $parseResult['records'] ?? [],
  'parse_error' => $parseResult['parse_error'] ?? '',
  'filename' => basename($latestPath),
]);
