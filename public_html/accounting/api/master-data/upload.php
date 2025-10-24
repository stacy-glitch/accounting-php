<?php
require_once __DIR__ . '/../_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_err('Method not allowed', 405);
}

$tab = $_POST['tab'] ?? '';
$groups = [
  'customers' => 'customers',
  'vehicles' => 'vehicles',
  'employees' => 'employees',
  'accounts' => 'accounts',
];

if (!isset($groups[$tab])) {
  json_err('未知的資料類別');
}

if (empty($_FILES['files'])) {
  json_err('請選擇至少一個檔案');
}

$baseDir = realpath(__DIR__ . '/../../uploads');
if ($baseDir === false) {
  json_err('找不到上傳根目錄', 500);
}

$categoryDir = $baseDir . '/master-data/' . $groups[$tab];
$pendingDir = $categoryDir . '/pending';
$processedDir = $categoryDir . '/processed';
$failedDir = $categoryDir . '/failed';

foreach ([$categoryDir, $pendingDir, $processedDir, $failedDir] as $dir) {
  if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
    json_err('無法建立上傳目錄', 500);
  }
}

$allowedExt = ['xls', 'xlsx', 'pdf', 'jpg', 'jpeg'];
$data = $_FILES['files'];
$count = is_array($data['name']) ? count($data['name']) : 0;
$saved = [];
$errors = [];

for ($i = 0; $i < $count; $i++) {
  $name = $data['name'][$i];
  $tmpName = $data['tmp_name'][$i];
  $error = (int) $data['error'][$i];
  $size = (int) $data['size'][$i];

  if ($error !== UPLOAD_ERR_OK) {
    $errors[] = ['name' => $name, 'error' => $error];
    continue;
  }

  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExt, true)) {
    $errors[] = ['name' => $name, 'error' => '不支援的檔案格式'];
    continue;
  }

  $id = date('YmdHis') . '_' . bin2hex(random_bytes(4));
  $savedName = $id . '.' . $ext;
  $pendingPath = $pendingDir . '/' . $savedName;

  if (!move_uploaded_file($tmpName, $pendingPath)) {
    $errors[] = ['name' => $name, 'error' => '儲存失敗'];
    continue;
  }

  $meta = [
    'id' => $id,
    'tab' => $tab,
    'category' => $groups[$tab],
    'originalName' => $name,
    'savedName' => $savedName,
    'extension' => $ext,
    'size' => $size,
    'uploadedAt' => date('c'),
    'status' => 'pending',
  ];

  $metaPath = $pendingDir . '/' . $id . '.json';
  file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

  $saved[] = $meta;
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
