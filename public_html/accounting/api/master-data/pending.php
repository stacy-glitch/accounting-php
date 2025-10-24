<?php
require_once __DIR__ . '/../_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$tab = $_GET['tab'] ?? '';
$groups = [
  'customers' => 'customers',
  'vehicles' => 'vehicles',
  'employees' => 'employees',
  'accounts' => 'accounts',
];

if (!isset($groups[$tab])) {
  json_err('未知的資料類別');
}

$baseDir = realpath(__DIR__ . '/../../uploads');
if ($baseDir === false) {
  json_err('找不到上傳根目錄', 500);
}

$pendingDir = $baseDir . '/master-data/' . $groups[$tab] . '/pending';
if (!is_dir($pendingDir)) {
  json_ok(['items' => []]);
}

$items = [];
foreach (glob($pendingDir . '/*.json') as $metaFile) {
  $meta = json_decode(file_get_contents($metaFile), true);
  if (!$meta) {
    continue;
  }
  $meta['fileExists'] = is_file($pendingDir . '/' . ($meta['savedName'] ?? ''));
  $items[] = $meta;
}

usort($items, function ($a, $b) {
  return strcmp($b['uploadedAt'] ?? '', $a['uploadedAt'] ?? '');
});

json_ok(['items' => $items]);
