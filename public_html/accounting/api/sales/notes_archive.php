<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
  json_err('Method not allowed', 405);
}

$uploadsRoot = realpath(__DIR__ . '/../../uploads');
if ($uploadsRoot === false) {
  json_err('找不到上傳根目錄', 500);
}

$notesDir = $uploadsRoot . '/sales-notes';
if (!is_dir($notesDir)) {
  json_ok([
    'count' => 0,
    'records' => [],
  ]);
}

$suffixMap = [];

$directories = glob($notesDir . '/*', GLOB_ONLYDIR);
if ($directories === false) {
  $directories = [];
}

foreach ($directories as $dir) {
  $snapshotPath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'latest.json';
  if (!is_file($snapshotPath)) {
    continue;
  }
  $content = file_get_contents($snapshotPath);
  if ($content === false) {
    continue;
  }
  $payload = json_decode($content, true);
  if (!is_array($payload)) {
    continue;
  }
  $records = is_array($payload['records'] ?? null) ? $payload['records'] : [];
  foreach ($records as $record) {
    $noteNumber = extract_note_number($record);
    $suffix = extract_suffix($noteNumber);
    if ($suffix === '') {
      continue;
    }
    $months = normalize_month_list($record['months'] ?? null);
    if (!$months) {
      continue;
    }
    if (!isset($suffixMap[$suffix])) {
      $suffixMap[$suffix] = [];
    }
    foreach ($months as $month) {
      $suffixMap[$suffix][$month] = true;
    }
  }
}

$result = [];
foreach ($suffixMap as $suffix => $monthsAssoc) {
  $months = array_keys($monthsAssoc);
  sort($months, SORT_STRING);
  $result[] = [
    'suffix' => $suffix,
    'months' => $months,
  ];
}

json_ok([
  'count' => count($result),
  'records' => $result,
]);

function extract_note_number(array $record): string {
  $fields = ['note_number', 'noteNumber', 'number', 'ticket', 'note'];
  foreach ($fields as $field) {
    if (!isset($record[$field])) {
      continue;
    }
    $value = trim((string) $record[$field]);
    if ($value !== '') {
      return $value;
    }
  }
  return '';
}

function extract_suffix(string $text): string {
  if ($text === '') {
    return '';
  }
  $digits = preg_replace('/\D+/', '', $text);
  if ($digits === '' || strlen($digits) < 5) {
    return '';
  }
  return substr($digits, -5);
}

function normalize_month_list($value): array {
  if (is_array($value)) {
    $tokens = [];
    foreach ($value as $item) {
      $tokens[] = normalize_month_token($item);
    }
  } elseif (is_string($value)) {
    $parts = preg_split('/[,，、\s]+/u', $value);
    $tokens = [];
    foreach ($parts as $part) {
      $tokens[] = normalize_month_token($part);
    }
  } else {
    return [];
  }
  $tokens = array_filter($tokens);
  if (!$tokens) {
    return [];
  }
  $unique = [];
  foreach ($tokens as $token) {
    $unique[$token] = true;
  }
  return array_keys($unique);
}

function normalize_month_token($value): string {
  if ($value === null) {
    return '';
  }
  $text = trim((string) $value);
  if ($text === '') {
    return '';
  }
  if (preg_match('/^(\d{2,3})年(\d{1,2})月$/u', $text, $matches)) {
    return format_month_output((int) $matches[1], (int) $matches[2]);
  }
  if (preg_match('/^(\d{2,3})[\/\-](\d{1,2})$/u', $text, $matches)) {
    return format_month_output((int) $matches[1], (int) $matches[2]);
  }
  if (preg_match('/^(\d{4})[\/\-](\d{1,2})$/u', $text, $matches)) {
    $rocYear = (int) $matches[1] - 1911;
    return format_month_output($rocYear, (int) $matches[2]);
  }
  if (preg_match('/^(\d{2,3})$/', $text)) {
    // treat as ROC month without delimiter, e.g., 11401 -> 114/1
    if (strlen($text) >= 3) {
      $year = (int) substr($text, 0, -2);
      $month = (int) substr($text, -2);
      return format_month_output($year, $month);
    }
  }
  return '';
}

function format_month_output(int $rocYear, int $month): string {
  if ($rocYear <= 0 || $month <= 0) {
    return '';
  }
  return $rocYear . '/' . $month;
}
