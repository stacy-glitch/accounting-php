<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/_mega_remittance.php';

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

$remittanceMap = mega_load_remittance_account_map($uploadsRoot);

$yearMonth = sprintf('%04d%02d', $year, $month);
$targetDir = $uploadsRoot . '/mega-bank/' . $yearMonth;
if (!is_dir($targetDir)) {
  json_ok([
    'records' => [],
    'parse_error' => '',
    'filename' => '',
  ]);
}

$snapshotPath = $targetDir . '/latest.json';
if (is_file($snapshotPath)) {
  $json = file_get_contents($snapshotPath);
  if ($json !== false) {
    $data = json_decode($json, true);
    if (is_array($data)) {
      $records = is_array($data['records'] ?? null) ? $data['records'] : [];
      $records = mega_replace_note_with_customer_from_accounts($records, $remittanceMap);
      json_ok([
        'records' => $records,
        'parse_error' => isset($data['parse_error']) ? (string) $data['parse_error'] : '',
        'filename' => isset($data['filename']) ? (string) $data['filename'] : '',
      ]);
    }
  }
}

json_ok([
  'records' => [],
  'parse_error' => '',
  'filename' => '',
]);
