<?php
require_once __DIR__ . '/../_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_err('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$tab = $input['tab'] ?? '';
$id = $input['id'] ?? '';

$groups = [
  'customers' => 'customers',
  'vehicles' => 'vehicles',
  'employees' => 'employees',
  'accounts' => 'accounts',
];

if (!$id || !isset($groups[$tab])) {
  json_err('參數不完整');
}

$baseDir = realpath(__DIR__ . '/../../uploads');
if ($baseDir === false) {
  json_err('找不到上傳根目錄', 500);
}

$pendingDir = $baseDir . '/master-data/' . $groups[$tab] . '/pending';
$metaFile = $pendingDir . '/' . $id . '.json';

if (!is_file($metaFile)) {
  json_err('找不到指定的檔案');
}

$meta = json_decode(file_get_contents($metaFile), true);
$savedName = $meta['savedName'] ?? '';
$filePath = $pendingDir . '/' . $savedName;

if (is_file($filePath) && !unlink($filePath)) {
  json_err('刪除檔案失敗', 500);
}

if (!unlink($metaFile)) {
  json_err('刪除紀錄失敗', 500);
}

json_ok(['message' => '已刪除上傳檔案。']);
