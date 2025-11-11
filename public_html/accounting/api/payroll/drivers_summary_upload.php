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
$year = $rocYear + 1911;

if (empty($_FILES['file'])) {
  json_err('請選擇要上傳的檔案');
}

$file = $_FILES['file'];
$errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
if ($errorCode !== UPLOAD_ERR_OK) {
  json_err('上傳失敗：' . upload_error_message($errorCode));
}

$originalName = (string) ($file['name'] ?? 'drivers');
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$normalizedExtension = $extension === 'xls' ? 'xlsx' : $extension;
if ($normalizedExtension !== 'xlsx') {
  json_err('僅支援上傳 XLSX 檔案');
}

$tmpPath = (string) ($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_file($tmpPath)) {
  json_err('找不到暫存檔案，請重新上傳');
}

$rows = read_xlsx_rows($tmpPath);
if (count($rows) <= 1) {
  json_err('檔案內容不足，請確認格式');
}
$header = array_map('trim', array_shift($rows));
$map = build_column_map($header);
$records = [];
if (isset($map['driver']) && isset($map['freight'])) {
  foreach ($rows as $row) {
    $code = get_cell($row, $map['code'] ?? null);
    $driver = get_cell($row, $map['driver']);
    $freight = normalize_amount(get_cell($row, $map['freight']));
    $note = get_cell($row, $map['note'] ?? null);
    if ($code === '' && $driver === '' && $freight === 0 && $note === '') {
      continue;
    }
    if ($driver === '') {
      continue;
    }
    $records[] = [
      'driver_code' => $code,
      'driver_name' => $driver,
      'freight' => $freight,
      'note' => $note,
    ];
  }
} else {
  $records = parse_simple_driver_rows(array_merge([$header], $rows));
}

if (!$records) {
  json_err('檔案中沒有可匯入的司機或運費資料，請確認格式');
}

$uploadsRoot = __DIR__ . '/../../uploads/payroll/drivers-summary';
if (!is_dir($uploadsRoot) && !mkdir($uploadsRoot, 0775, true) && !is_dir($uploadsRoot)) {
  json_err('無法建立上傳根目錄');
}

$yearMonth = sprintf('%04d%02d', $year, $month);
$targetDir = $uploadsRoot . '/' . $yearMonth;
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
  json_err('無法建立月份目錄');
}

$filename = persist_uploaded_file($tmpPath, $targetDir, $originalName, $normalizedExtension);
$relativePath = 'uploads/payroll/drivers-summary/' . $yearMonth . '/' . $filename;

$pdo = pdo();
$records = hydrate_driver_names($pdo, $records);
$records = hydrate_driver_codes($pdo, $records);
try {
  $pdo->beginTransaction();
  $pdo->prepare('DELETE FROM driver_summary_records WHERE roc_year = ? AND month = ?')->execute([$rocYear, $month]);
  $insert = $pdo->prepare(
    'INSERT INTO driver_summary_records
      (roc_year, month, driver_code, driver_name, freight, note)
     VALUES
      (?, ?, ?, ?, ?, ?)'
  );
  foreach ($records as $record) {
    $insert->execute([
      $rocYear,
      $month,
      (string) ($record['driver_code'] ?? ''),
      (string) ($record['driver_name'] ?? ''),
      normalize_amount($record['freight'] ?? 0),
      (string) ($record['note'] ?? ''),
    ]);
  }
  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  json_err('儲存資料失敗：' . $e->getMessage(), 500);
}

$stored = fetch_driver_summary_records($pdo, $rocYear, $month);

json_ok([
  'message' => '已上傳並解析檔案',
  'data' => [
    'path' => $relativePath,
    'url' => '../' . ltrim($relativePath, '/'),
    'roc_year' => $rocYear,
    'year' => $year,
    'month' => $month,
    'filename' => $filename,
    'count' => count($stored),
    'records' => $stored,
  ],
]);

function fetch_driver_summary_records(PDO $pdo, int $rocYear, int $month): array {
  $stmt = $pdo->prepare(
    'SELECT id, roc_year, month, driver_code, driver_name, freight, note
     FROM driver_summary_records
     WHERE roc_year = ? AND month = ?
     ORDER BY id'
  );
  $stmt->execute([$rocYear, $month]);
  $records = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $records[] = [
      'id' => (int) ($row['id'] ?? 0),
      'roc_year' => (int) ($row['roc_year'] ?? 0),
      'month' => (int) ($row['month'] ?? 0),
      'driver_code' => (string) ($row['driver_code'] ?? ''),
      'driver_name' => (string) ($row['driver_name'] ?? ''),
      'freight' => (int) ($row['freight'] ?? 0),
      'note' => (string) ($row['note'] ?? ''),
    ];
  }
  return $records;
}

function build_column_map(array $header): array {
  $map = [];
  foreach ($header as $idx => $label) {
    $key = normalize_header_label($label);
    switch ($key) {
      case '代號':
      case '司機代號':
      case '車號':
        $map['code'] = $idx;
        break;
      case '司機':
      case '司機姓名':
      case '姓名':
        $map['driver'] = $idx;
        break;
      case '運費':
      case '金額':
      case '費用':
        $map['freight'] = $idx;
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

function hydrate_driver_names(PDO $pdo, array $records): array {
  $codes = [];
  foreach ($records as $record) {
    $code = trim((string) ($record['driver_code'] ?? ''));
    $name = trim((string) ($record['driver_name'] ?? ''));
    if ($code !== '' && $name === '') {
      $codes[$code] = true;
    }
  }
  if (!$codes) {
    return $records;
  }
  $placeholders = implode(',', array_fill(0, count($codes), '?'));
  $stmt = $pdo->prepare("SELECT code, name FROM employees WHERE code IN ($placeholders)");
  $stmt->execute(array_keys($codes));
  $map = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $code = trim((string) ($row['code'] ?? ''));
    if ($code !== '') {
      $map[$code] = (string) ($row['name'] ?? '');
    }
  }
  if (!$map) {
    return $records;
  }
  foreach ($records as &$record) {
    $code = trim((string) ($record['driver_code'] ?? ''));
    if ($code !== '' && trim((string) ($record['driver_name'] ?? '')) === '' && isset($map[$code])) {
      $record['driver_name'] = $map[$code];
    }
  }
  unset($record);
  return $records;
}

function parse_simple_driver_rows(array $rows): array {
  $records = [];
  foreach ($rows as $row) {
    if (!is_array($row) || count($row) < 2) {
      continue;
    }
    $name = trim((string) ($row[0] ?? ''));
    $amountRaw = trim((string) ($row[1] ?? ''));
    if ($name === '' && $amountRaw === '') {
      continue;
    }
    if ($name === '') {
      continue;
    }
    if (preg_match('/(司機|金額|總匯|總攬|總額|合計)/u', $name)) {
      continue;
    }
    if (preg_match('/^\d{4}\/\d{1,2}$/', $name)) {
      continue;
    }
    $amount = normalize_amount($amountRaw);
    if ($amount === 0) {
      continue;
    }
    $records[] = [
      'driver_code' => '',
      'driver_name' => $name,
      'freight' => $amount,
      'note' => '',
    ];
  }
  return $records;
}

function hydrate_driver_codes(PDO $pdo, array $records): array {
  $names = [];
  foreach ($records as $record) {
    $name = trim((string) ($record['driver_name'] ?? ''));
    $code = trim((string) ($record['driver_code'] ?? ''));
    if ($name !== '' && $code === '') {
      $names[$name] = true;
    }
  }
  if (!$names) {
    return $records;
  }
  $placeholders = implode(',', array_fill(0, count($names), '?'));
  $stmt = $pdo->prepare("SELECT code, name FROM employees WHERE name IN ($placeholders)");
  $stmt->execute(array_keys($names));
  $map = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nameKey = trim((string) ($row['name'] ?? ''));
    $code = trim((string) ($row['code'] ?? ''));
    if ($nameKey !== '' && $code !== '') {
      $map[$nameKey] = $code;
    }
  }
  if (!$map) {
    return $records;
  }
  foreach ($records as &$record) {
    $name = trim((string) ($record['driver_name'] ?? ''));
    $code = trim((string) ($record['driver_code'] ?? ''));
    if ($name !== '' && $code === '' && isset($map[$name])) {
      $record['driver_code'] = $map[$name];
    }
  }
  unset($record);
  return $records;
}

function normalize_amount($value): int {
  return (int) round((float) preg_replace('/[^\d.\-]/', '', (string) $value));
}

function normalize_header_label(string $label): string {
  return preg_replace('/\s+/', '', trim($label));
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

function read_xlsx_rows(string $path): array {
  $zip = new ZipArchive();
  if ($zip->open($path) !== true) {
    json_err('無法開啟 XLSX 檔案');
  }

  $strings = [];
  if (($shared = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
    $sharedXml = simplexml_load_string($shared);
    if ($sharedXml !== false) {
      foreach ($sharedXml->si as $si) {
        $text = '';
        if (isset($si->t)) {
          $text .= (string) $si->t;
        }
        if (isset($si->r)) {
          foreach ($si->r as $run) {
            $text .= (string) $run->t;
          }
        }
        $strings[] = (string) $text;
      }
    }
  }

  $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
  if ($sheetXml === false) {
    $zip->close();
    json_err('找不到工作表資料');
  }
  $sheet = simplexml_load_string($sheetXml);
  if ($sheet === false) {
    $zip->close();
    json_err('解析 XLSX 內容失敗');
  }

  $rows = [];
  foreach ($sheet->sheetData->row as $row) {
    $rowData = [];
    foreach ($row->c as $cell) {
      $ref = (string) $cell['r'];
      $col = column_index_from_ref($ref);
      $value = '';
      if (isset($cell->v)) {
        $raw = (string) $cell->v;
        if ((string) $cell['t'] === 's') {
          $value = $strings[(int) $raw] ?? '';
        } else {
          $value = $raw;
        }
      }
      $rowData[$col] = trim($value);
    }
    if (!empty($rowData)) {
      ksort($rowData);
      $rows[] = array_values($rowData);
    }
  }

  $zip->close();
  return $rows;
}

function column_index_from_ref(string $ref): int {
  $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
  $index = 0;
  $length = strlen($letters);
  for ($i = 0; $i < $length; $i++) {
    $index = $index * 26 + (ord($letters[$i]) - 64);
  }
  return max(0, $index - 1);
}

function persist_uploaded_file(string $tmpPath, string $targetDir, string $originalName, string $extension): string {
  $baseName = substr(preg_replace('/[^A-Za-z0-9_-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME)), 0, 40);
  $baseName = trim($baseName, '-') ?: 'drivers';
  $filename = sprintf('%s_%s.%s', $baseName, date('YmdHis'), $extension);
  $destination = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

  if (!is_uploaded_file($tmpPath)) {
    json_err('暫存檔案已失效，請重新上傳');
  }

  if (!move_uploaded_file($tmpPath, $destination)) {
    json_err('儲存檔案失敗，請稍後再試');
  }
  @chmod($destination, 0664);
  return $filename;
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
