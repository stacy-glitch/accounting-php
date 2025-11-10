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
if ($extension === 'xls') {
  $extension = 'xlsx';
}
if (!in_array($extension, ['csv', 'xlsx'], true)) {
  json_err('僅支援上傳 CSV 或 XLSX 檔案');
}

$tmpPath = (string) ($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_file($tmpPath)) {
  json_err('找不到暫存檔案，請重新上傳');
}

$rows = $extension === 'xlsx' ? read_xlsx_rows($tmpPath) : read_csv_rows($tmpPath);
if (count($rows) <= 1) {
  json_err('檔案內容不足，請確認格式');
}

$headerRow = array_shift($rows);
$normalizedHeader = array_map('normalize_label', $headerRow);
error_log('[CPC_UPLOAD] header=' . json_encode($normalizedHeader, JSON_UNESCAPED_UNICODE));
if (!empty($rows)) {
  error_log('[CPC_UPLOAD] sample_row=' . json_encode($rows[0], JSON_UNESCAPED_UNICODE));
}
$colIndex = [];
foreach ($normalizedHeader as $idx => $label) {
  if ($label === '') {
    continue;
  }
  if (!array_key_exists($label, $colIndex)) {
    $colIndex[$label] = $idx;
  }
}

$requiredLabels = ['車號', '交易日期時間', '油站', '參考金額'];
$missingLabels = [];
foreach ($requiredLabels as $label) {
  if (!array_key_exists($label, $colIndex)) {
    $missingLabels[] = $label;
  }
}
if ($missingLabels) {
  json_err('匯入檔缺少欄位：' . implode(' / ', $missingLabels));
}

$records = [];
foreach ($rows as $row) {
  if (!is_array($row)) {
    continue;
  }
  $plate = trim((string) ($row[$colIndex['車號']] ?? ''));
  $dateTime = trim((string) ($row[$colIndex['交易日期時間']] ?? ''));
  $station = trim((string) ($row[$colIndex['油站']] ?? ''));
  $amountRaw = trim((string) ($row[$colIndex['參考金額']] ?? ''));
  $dateTime = normalize_excel_datetime($dateTime);

  if ($plate === '' && $dateTime === '' && $amountRaw === '') {
    continue;
  }
  if ($plate === '' || $dateTime === '' || $amountRaw === '') {
    continue;
  }

  $tradeDateRaw = substr($dateTime, 0, 10);
  if (strlen($tradeDateRaw) !== 10) {
    continue;
  }
  $tradeDate = format_roc_date(str_replace(['.', '/'], '-', $tradeDateRaw));

$amount = normalize_amount($amountRaw);
if ($amount <= 0) {
  continue;
}

  $records[] = [
    'license_plate' => $plate,
    'driver' => '',
    'trade_date' => $tradeDate,
    'station' => $station,
    'amount' => $amount,
    'note' => '',
  ];
}

if (!empty($records)) {
  error_log('[CPC_UPLOAD] sample_record=' . json_encode($records[0], JSON_UNESCAPED_UNICODE));
}

if (!$records) {
  json_err('檔案中沒有可匯入的資料列');
}

$pdo = pdo();
try {
  $pdo->beginTransaction();
  $pdo->prepare('DELETE FROM cpc_records WHERE roc_year = ? AND month = ?')->execute([$rocYear, $month]);
  $pdo->prepare('DELETE FROM cpc_summary WHERE roc_year = ? AND month = ?')->execute([$rocYear, $month]);
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

json_ok(['imported' => count($records)]);

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

function read_csv_rows(string $path): array {
  if (!is_file($path)) {
    json_err('無法讀取 CSV 檔案');
  }
  $raw = @file_get_contents($path);
  if ($raw === false) {
    json_err('無法讀取 CSV 檔案');
  }
  $utf8 = convert_to_utf8($raw);
  if (strncmp($utf8, "\xEF\xBB\xBF", 3) === 0) {
    $utf8 = substr($utf8, 3);
  }
  $temp = fopen('php://temp', 'r+');
  if ($temp === false) {
    json_err('無法解析 CSV 檔案');
  }
  fwrite($temp, $utf8);
  rewind($temp);

  $firstLine = fgets($temp);
  if ($firstLine === false) {
    fclose($temp);
    return [];
  }
  $delimiter = detect_csv_delimiter($firstLine);
  rewind($temp);

  $rows = [];
  while (($data = fgetcsv($temp, 0, $delimiter)) !== false) {
    if ($data === [null] || $data === false) {
      continue;
    }
    $rows[] = array_map(static function ($value) {
      return trim((string) $value);
    }, $data);
  }
  fclose($temp);
  return $rows;
}

function detect_csv_delimiter(string $line): string {
  foreach ([",", "\t", ";", "|"] as $delimiter) {
    if (substr_count($line, $delimiter) > 0) {
      return $delimiter;
    }
  }
  return ',';
}

function read_xlsx_rows(string $path): array {
  $zip = new ZipArchive();
  if ($zip->open($path) !== true) {
    json_err('無法開啟 XLSX 檔案');
  }

  $sharedStrings = [];
  $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
  if ($sharedXml !== false) {
    $shared = simplexml_load_string($sharedXml);
    if ($shared !== false) {
      foreach ($shared->si as $si) {
        $text = '';
        if (isset($si->t)) {
          $text .= (string) $si->t;
        }
        if (isset($si->r)) {
          foreach ($si->r as $run) {
            $text .= (string) $run->t;
          }
        }
        $sharedStrings[] = trim((string) $text);
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
  $maxColumn = 0;
  $rawRows = [];
  foreach ($sheet->sheetData->row as $row) {
    $rowData = [];
    foreach ($row->c as $cell) {
      $column = column_index_from_ref((string) $cell['r']);
      $maxColumn = max($maxColumn, $column);
      $value = '';
      if (isset($cell->v)) {
        $raw = (string) $cell->v;
        if ((string) $cell['t'] === 's') {
          $value = $sharedStrings[(int) $raw] ?? '';
        } else {
          $value = $raw;
        }
      }
      $rowData[$column] = trim($value);
    }
    if (!empty($rowData)) {
      $rawRows[] = $rowData;
    }
  }

  foreach ($rawRows as $rowData) {
    $normalized = [];
    for ($i = 0; $i <= $maxColumn; $i++) {
      $normalized[$i] = isset($rowData[$i]) ? $rowData[$i] : '';
    }
    $rows[] = $normalized;
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

function normalize_amount($value): int {
  if ($value === null) {
    return 0;
  }
  if (is_numeric($value)) {
    return (int) round((float) $value);
  }
  $filtered = preg_replace('/[^\d.\-]/', '', (string) $value);
  if ($filtered === '' || !is_numeric($filtered)) {
    return 0;
  }
  return (int) round((float) $filtered);
}

function format_roc_date(string $date): string {
  if ($date === '') {
    return '';
  }
  try {
    $dt = new DateTimeImmutable($date);
  } catch (Throwable $e) {
    return $date;
  }
  $year = (int) $dt->format('Y') - 1911;
  if ($year <= 0) {
    return $dt->format('Y/m/d');
  }
  $month = (int) $dt->format('n');
  $day = (int) $dt->format('j');
  return sprintf('%d/%d/%d', $year, $month, $day);
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

function normalize_label($label): string {
  if ($label === null || $label === '') {
    return '';
  }
  $value = trim(convert_to_utf8((string) $label));
  if ($value === '') {
    return '';
  }
  $compact = preg_replace('/\s+/u', '', $value);
  if ($compact === '') {
    return '';
  }
  $compact = str_replace(['.', '．', '﹒', '。'], '', $compact);

  $aliasMap = [
    '車號' => '車號',
    '車牌號碼' => '車號',
    '車牌' => '車號',
    '車輛' => '車號',
    '交易日期時間' => '交易日期時間',
    '交易日期' => '交易日期時間',
    '交易時間' => '交易日期時間',
    '油站' => '油站',
    '加油站' => '油站',
    '站名' => '油站',
    '參考金額' => '參考金額',
    '金額' => '參考金額',
  ];

  foreach ($aliasMap as $alias => $canonical) {
    $pos = function_exists('mb_stripos')
      ? mb_stripos($compact, $alias)
      : stripos($compact, $alias);
    if ($pos !== false) {
      return $canonical;
    }
  }

  return $compact;
}

function normalize_excel_datetime(string $value): string {
  if ($value === '') {
    return '';
  }
  if (!is_numeric($value)) {
    return $value;
  }
  $serial = (float) $value;
  if ($serial <= 0) {
    return '';
  }
  $timestamp = (int) round(($serial - 25569) * 86400);
  if ($timestamp < 0) {
    $timestamp = 0;
  }
  try {
    $tz = new DateTimeZone(date_default_timezone_get() ?: 'UTC');
  } catch (Throwable $e) {
    $tz = new DateTimeZone('UTC');
  }
  $dt = (new DateTimeImmutable('@' . $timestamp))->setTimezone($tz);
  return $dt->format('Y-m-d H:i:s');
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

function convert_to_utf8(string $value): string {
  if ($value === '') {
    return '';
  }

  $encoding = null;
  $bomMap = [
    "\xEF\xBB\xBF" => 'UTF-8',
    "\xFF\xFE" => 'UTF-16LE',
    "\xFE\xFF" => 'UTF-16BE',
    "\xFF\xFE\x00\x00" => 'UTF-32LE',
    "\x00\x00\xFE\xFF" => 'UTF-32BE',
  ];
  foreach ($bomMap as $bom => $enc) {
    if (strncmp($value, $bom, strlen($bom)) === 0) {
      $value = substr($value, strlen($bom));
      $encoding = $enc;
      break;
    }
  }

  if ($encoding === null && function_exists('mb_detect_encoding')) {
    $encoding = @mb_detect_encoding(
      $value,
      ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'UTF-32LE', 'UTF-32BE', 'BIG5', 'BIG5-HKSCS', 'CP950', 'ISO-8859-1'],
      true
    );
  }
  if ($encoding && strtoupper($encoding) !== 'UTF-8') {
    $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);
    if ($converted !== false) {
      $value = $converted;
    }
  }
  if (strpos($value, "\0") !== false) {
    $value = str_replace("\0", '', $value);
  }
  return $value;
}
