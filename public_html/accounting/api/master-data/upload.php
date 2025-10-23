<?php
require_once __DIR__ . '/../_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_err('Method not allowed', 405);
}

$tab = $_POST['tab'] ?? '';
$destinations = [
  'customers' => 'customers',
  'vehicles' => 'vehicles',
  'employees' => 'employees',
  'accounts' => 'accounts',
];

if (!isset($destinations[$tab])) {
  json_err('未知的資料類別');
}

if (empty($_FILES['files'])) {
  json_err('請選擇至少一個檔案');
}

$targetDir = __DIR__ . '/../../uploads/master-data/' . $destinations[$tab];
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
  json_err('無法建立上傳目錄', 500);
}

$allowedExt = ['xls', 'xlsx', 'pdf', 'jpg', 'jpeg'];
$fileData = $_FILES['files'];
$count = is_array($fileData['name']) ? count($fileData['name']) : 0;
$saved = [];
$errors = [];

for ($i = 0; $i < $count; $i++) {
  $name = $fileData['name'][$i];
  $tmpName = $fileData['tmp_name'][$i];
  $error = (int) $fileData['error'][$i];
  $size = (int) $fileData['size'][$i];

  if ($error !== UPLOAD_ERR_OK) {
    $errors[] = ['name' => $name, 'error' => $error];
    continue;
  }

  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExt, true)) {
    $errors[] = ['name' => $name, 'error' => '不支援的檔案格式'];
    continue;
  }

  $newName = date('Ymd_His') . '_' . uniqid('', true) . '.' . $ext;
  $targetPath = $targetDir . '/' . $newName;

  if (!move_uploaded_file($tmpName, $targetPath)) {
    $errors[] = ['name' => $name, 'error' => '儲存失敗'];
    continue;
  }

  $saved[] = [
    'originalName' => $name,
    'path' => 'uploads/master-data/' . $destinations[$tab] . '/' . $newName,
    'size' => $size,
  ];
}

if (empty($saved)) {
  $message = '沒有檔案成功上傳。';
  if (!empty($errors)) {
    $message .= ' (' . count($errors) . ' 個失敗)';
  }
  json_err($message);
}

json_ok([
  'message' => '已成功上傳 ' . count($saved) . ' 個檔案。',
  'saved' => $saved,
  'errors' => $errors,
]);
