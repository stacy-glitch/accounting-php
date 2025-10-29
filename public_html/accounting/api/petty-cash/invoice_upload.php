<?php
declare(strict_types=1);

require_once __DIR__ . '/_entries.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_err('Method not allowed', 405);
}

if (!isset($_FILES['invoice'])) {
  json_err('請選擇要上傳的發票影像');
}

$payloadId = $_POST['id'] ?? $_POST['record_id'] ?? '';
$id = is_numeric($payloadId) ? (int) $payloadId : 0;
if ($id <= 0) {
  json_err('缺少紀錄編號');
}

$file = $_FILES['invoice'];
$errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
if ($errorCode !== UPLOAD_ERR_OK) {
  json_err('上傳失敗：' . upload_error_message($errorCode));
}

$pdo = pdo();
ensure_entries_table($pdo);
$existing = fetch_entry_by_id($pdo, $id);
if (!$existing) {
  json_err('資料不存在或已被刪除', 404);
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];
$originalName = $file['name'] ?? 'invoice';
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions, true)) {
  json_err('僅支援上傳圖片檔案（JPG、PNG、WEBP、HEIC）');
}

$tmpPath = $file['tmp_name'];
if (!is_file($tmpPath)) {
  json_err('找不到暫存檔案，請重新上傳');
}

$uploadsRoot = __DIR__ . '/../../uploads/petty-cash/invoices';
if (!is_dir($uploadsRoot) && !mkdir($uploadsRoot, 0775, true) && !is_dir($uploadsRoot)) {
  json_err('無法建立發票儲存目錄');
}

$filename = sprintf('%d_%s.%s', $id, date('YmdHis'), $extension);
$destination = rtrim($uploadsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($tmpPath, $destination)) {
  json_err('移動檔案失敗，請稍後再試');
}

if (!chmod($destination, 0664)) {
  // ignore chmod failure
}

$relativePath = 'uploads/petty-cash/invoices/' . $filename;

try {
  $pdo->beginTransaction();

  if (!empty($existing['invoice_path'])) {
    $oldPath = __DIR__ . '/../../' . ltrim($existing['invoice_path'], '/');
    if (is_file($oldPath)) {
      @unlink($oldPath);
    }
  }

  update_entry($pdo, $id, [
    'entry_date' => $existing['entry_date'],
    'transaction_date' => $existing['transaction_date'],
    'transaction_month' => $existing['transaction_month'],
    'code' => $existing['code'],
    'subject' => $existing['subject'],
    'note' => $existing['note'],
    'income' => $existing['income'],
    'expense' => $existing['expense'],
    'advance' => $existing['advance'],
    'advance_status' => $existing['advance_status'],
    'invoice_path' => $relativePath,
  ]);

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  @unlink($destination);
  json_err('發票儲存失敗：' . $e->getMessage());
}

json_ok([
  'message' => '發票已上傳',
  'data' => [
    'invoice_path' => $relativePath,
    'invoice_url' => '../' . ltrim($relativePath, '/'),
  ],
]);

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
      return '檔案類型被系統阻擋';
    default:
      return '未知的上傳錯誤';
  }
}
