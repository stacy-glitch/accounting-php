<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_err('Method not allowed', 405);
}

$rocYear = filter_input(INPUT_POST, 'roc_year', FILTER_VALIDATE_INT);
$month = filter_input(INPUT_POST, 'month', FILTER_VALIDATE_INT);
if (!is_int($rocYear) || $rocYear < 1 || $rocYear > 200) {
  json_err('請提供正確的ROC年份');
}
if (!is_int($month) || $month < 1 || $month > 12) {
  json_err('請提供正確的月份');
}

if (empty($_FILES['file'])) {
  json_err('請選擇要上傳的檔案');
}

$file = $_FILES['file'];
$errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
if ($errorCode !== UPLOAD_ERR_OK) {
  json_err('上傳失敗：' . upload_error_message($errorCode));
}

$originalName = (string) ($file['name'] ?? 'cpc');
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($extension !== 'csv') {
  json_err('僅支援上傳 CSV 檔案');
}

$tmpPath = (string) ($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_file($tmpPath)) {
  json_err('找不到暫存檔案，請重新上傳');
}

$rows = read_csv_rows($tmpPath);
if (count($rows) <= 1) {
  json_err('檔案內容不足，請確認格式');
}

$header = array_map('trim', array_shift($rows));
$map = build_cpc_column_map($header);

$records = [];
foreach ($rows as $row) {
  $license = get_cell($row, $map['plate'] ?? null);
  if ($license === '') {
    continue;
  }
  $tradeDate = get_cell($row, $map['date'] ?? null);
  if ($tradeDate === '' && isset($map['datetime'])) {
    $tradeDate = get_cell($row, $map['datetime']);
    if (strlen($tradeDate) >= 10) {
      $tradeDate = substr($tradeDate, 0, 10);
    }
  }
  $records[] = [
    'license_plate' => $license,
    'driver' => get_cell($row, $map['driver'] ?? null),
    'trade_date' => $tradeDate,
    'station' => get_cell($row, $map['station'] ?? null),
    'amount' => normalize_amount(get_cell($row, $map['amount'] ?? null)),
    'note' => get_cell($row, $map['note'] ?? null),
  ];
}

if (!$records) {
  json_err('檔案中沒有可匯入的資料列');
}

$pdo = pdo();
try {
  $pdo->beginTransaction();
  $pdo->prepare('DELETE FROM cpc_records WHERE roc_year = ? AND month = ?')->execute([$rocYear, $month]);
  $insert = $pdo->prepare(
    'INSERT INTO cpc_records
      (roc_year, month, code, license_plate, driver, trade_date, station, amount, note)
     VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );
  foreach ($records as $record) {
    $insert->execute([
      $rocYear,
      $month,
      '',
      $record['license_plate'],
      $record['driver'],
      $record['trade_date'],
      $record['station'],
      $record['amount'],
      $record['note'],
    ]);
  }
  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  json_err('儲存資料失敗：' . $e->getMessage(), 500);
}

$stored = fetch_cpc_records($pdo, $rocYear, $month);

json_ok([
  'message' => '已上傳並解析檔案',
  'data' => [
    'roc_year' => $rocYear,
    'month' => $month,
    'count' => count($stored),
    'records' => $stored,
  ],
]);

function build_cpc_column_map(array $header): array {
  $map = [];
  foreach ($header as $idx => $label) {
    $key = normalize_header_label($label);
    switch ($key) {
      case '車牌號碼':
      case '車號':
        $map['plate'] = $idx;
        break;
      case '司機':
      case '司機代號':
        $map['driver'] = $idx;
        break;
      case '交易日期':
      case '加油日期':
        $map['date'] = $idx;
        break;
      case '交易日期時間':
        $map['datetime'] = $idx;
        break;
      case '油站':
      case '加油站':
        $map['station'] = $idx;
        break;
      case '金額':
      case '油資':
      case '參考金額':
        $map['amount'] = $idx;
        break;
      case '備註':
        $map['note'] = $idx;
        break;
      default:
        break;
    }
  }
  return $map;
}

function fetch_cpc_records(PDO $pdo, int $rocYear, int $month): array {
  $stmt = $pdo->prepare(
    'SELECT id, roc_year, month, code, license_plate, driver, trade_date, station, amount, note
     FROM cpc_records
     WHERE roc_year = ? AND month = ?
     ORDER BY license_plate, trade_date'
  );
  $stmt->execute([$rocYear, $month]);
  return array_map('normalize_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function get_cell(array $row, ?int $index): string {
  if ($index === null) {
    return '';
  }
  $value = $row[$index] ?? '';
  if (!is_string($value)) {
    $value = (string) $value;
  }
  return trim($value);
}

function read_csv_rows(string $path): array {
  $handle = fopen($path, 'rb');
  if ($handle === false) {
    json_err('無法讀取 CSV 檔案');
  }
  $rows = [];
  while (($data = fgetcsv($handle)) !== false) {
    if ($data === [null] || $data === false) {
      continue;
    }
    $rows[] = array_map('trim', $data);
  }
  fclose($handle);
  return $rows;
}

function normalize_row(array $row): array {
  return [
    'id' => (int) ($row['id'] ?? 0),
    'roc_year' => (int) ($row['roc_year'] ?? 0),
    'month' => (int) ($row['month'] ?? 0),
    'license_plate' => (string) ($row['license_plate'] ?? ''),
    'driver' => (string) ($row['driver'] ?? ''),
    'trade_date' => (string) ($row['trade_date'] ?? ''),
    'station' => (string) ($row['station'] ?? ''),
    'amount' => (int) ($row['amount'] ?? 0),
    'note' => (string) ($row['note'] ?? ''),
  ];
}

function normalize_header_label(string $label): string {
  return preg_replace('/\s+/', '', trim($label));
}

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
