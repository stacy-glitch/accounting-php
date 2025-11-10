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

$originalName = (string) ($file['name'] ?? 'health');
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$normalizedExtension = $extension === 'xls' ? 'xlsx' : $extension;
$allowed = ['csv', 'xlsx', 'pdf'];
if (!in_array($normalizedExtension, $allowed, true)) {
  json_err('僅支援上傳 CSV、XLSX 或 PDF 檔案');
}

$tmpPath = (string) ($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_file($tmpPath)) {
  json_err('找不到暫存檔案，請重新上傳');
}

$records = $normalizedExtension === 'pdf'
  ? parse_health_pdf($tmpPath)
  : parse_health_spreadsheet($tmpPath, $normalizedExtension);

if (!$records) {
  json_err('檔案中沒有可匯入的資料列');
}

$uploadsRoot = __DIR__ . '/../../uploads/payroll/health';
if (!is_dir($uploadsRoot) && !mkdir($uploadsRoot, 0775, true) && !is_dir($uploadsRoot)) {
  json_err('無法建立上傳根目錄');
}

$yearMonth = sprintf('%04d%02d', $year, $month);
$targetDir = $uploadsRoot . '/' . $yearMonth;
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
  json_err('無法建立月份目錄');
}

$filename = persist_uploaded_file($tmpPath, $targetDir, $originalName, $normalizedExtension);
$relativePath = 'uploads/payroll/health/' . $yearMonth . '/' . $filename;

$pdo = pdo();
try {
  $pdo->beginTransaction();
  $pdo->prepare('DELETE FROM health_roster_records WHERE roc_year = ? AND month = ?')->execute([$rocYear, $month]);
  $insert = $pdo->prepare(
    'INSERT INTO health_roster_records
      (roc_year, month, insurance_fee, dependent_name, id_number, birth, identity_type, change_type, billing_note, self_payment, company_payment, self_total, note)
     VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );
  foreach ($records as $record) {
    $insert->execute([
      $rocYear,
      $month,
      normalize_amount($record['insurance_fee'] ?? 0),
      (string) ($record['dependent_name'] ?? ''),
      (string) ($record['id_number'] ?? ''),
      (string) ($record['birth'] ?? ''),
      (string) ($record['identity_type'] ?? ''),
      (string) ($record['change_type'] ?? ''),
      (string) ($record['billing_note'] ?? ''),
      normalize_amount($record['self_payment'] ?? 0),
      normalize_amount($record['company_payment'] ?? 0),
      normalize_amount($record['self_total'] ?? 0),
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

$stored = fetch_health_records($pdo, $rocYear, $month);

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

function parse_health_spreadsheet(string $path, string $extension): array {
  $rows = $extension === 'csv' ? read_csv_rows($path) : read_xlsx_rows($path);
  if (count($rows) <= 1) {
    return [];
  }
  $header = array_map('trim', array_shift($rows));
  $map = build_health_column_map($header);
  if (!isset($map['dependent'])) {
    json_err('檔案缺少「眷屬姓名」欄位，請確認是否使用正確模板');
  }
  $records = [];
  foreach ($rows as $row) {
    $dependent = get_cell($row, $map['dependent']);
    if ($dependent === '') {
      continue;
    }
    $billingNote = trim(get_cell($row, $map['note'] ?? null));
    $selfPayment = normalize_amount(get_cell($row, $map['self'] ?? null));
    $companyPayment = normalize_amount(get_cell($row, $map['company'] ?? null));
    $selfTotal = normalize_amount(get_cell($row, $map['total'] ?? null));
    if ($selfTotal === 0) {
      $selfTotal = $selfPayment;
    }
    $insuranceFee = normalize_amount(get_cell($row, $map['insurance'] ?? null));
    if ($insuranceFee === 0) {
      $insuranceFee = $selfPayment + $companyPayment;
    }
    $records[] = [
      'insurance_fee' => $insuranceFee,
      'dependent_name' => $dependent,
      'id_number' => get_cell($row, $map['id'] ?? null),
      'birth' => normalize_pdf_date(get_cell($row, $map['birth'] ?? null)),
      'identity_type' => '',
      'change_type' => '',
      'billing_note' => $billingNote,
      'self_payment' => $selfPayment,
      'company_payment' => $companyPayment,
      'self_total' => $selfTotal,
      'note' => '',
    ];
  }
  return $records;
}

function parse_health_pdf(string $path): array {
  $pdftotext = '/opt/homebrew/bin/pdftotext';
  if (!is_file($pdftotext) && function_exists('shell_exec')) {
    $probe = shell_exec('command -v pdftotext');
    if (is_string($probe) && trim($probe) !== '') {
      $pdftotext = trim($probe);
    } else {
      $pdftotext = 'pdftotext';
    }
  }
  $command = escapeshellcmd($pdftotext) . ' -layout -enc UTF-8 ' . escapeshellarg($path) . ' -';
  $descriptor = [
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
  ];
  $process = proc_open($command, $descriptor, $pipes);
  if (!is_resource($process)) {
    json_err('無法啟動 pdftotext，請稍後再試');
  }
  $text = stream_get_contents($pipes[1]);
  $errorText = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $exitCode = proc_close($process);
  if ($exitCode !== 0) {
    json_err('解析 PDF 失敗：' . trim($errorText));
  }

  $lines = preg_split('/\r\n|\r|\n/', $text);
  $records = [];
  foreach ($lines as $line) {
    if (!is_string($line)) {
      continue;
    }
    $trimmed = trim($line);
    if ($trimmed === '' || !preg_match('/^\d/', $trimmed)) {
      continue;
    }
    $hasFee = preg_match('/^\s*([\d,]+)\s+(\S+)\s+([A-Z]\d{1,}\*{0,4}[A-Z0-9]*)\s+(\d{6,7})\s+(.+)$/u', $line, $matchWithFee);
    $matchesWithoutFee = [];
    if (!$hasFee) {
      if (!preg_match('/^\s*(\S+)\s+([A-Z]\d{1,}\*{0,4}[A-Z0-9]*)\s+(\d{6,7})\s+(.+)$/u', $line, $matchesWithoutFee)) {
        continue;
      }
    }

    if ($hasFee) {
      $insuranceFee = $matchWithFee[1];
      $dependent = $matchWithFee[2];
      $idNumber = $matchWithFee[3];
      $birthRaw = $matchWithFee[4];
      $rest = trim($matchWithFee[5]);
    } else {
      $insuranceFee = '0';
      $dependent = $matchesWithoutFee[1];
      $idNumber = $matchesWithoutFee[2];
      $birthRaw = $matchesWithoutFee[3];
      $rest = trim($matchesWithoutFee[4]);
    }

    $tokens = preg_split('/\s+/u', $rest, -1, PREG_SPLIT_NO_EMPTY);
    if (!$tokens) {
      continue;
    }
    $amountTokens = [];
    while (!empty($tokens) && preg_match('/^[\d,]+$/', end($tokens))) {
      $amountTokens[] = array_pop($tokens);
    }
    $amountTokens = array_reverse($amountTokens);
    $noteTokens = $tokens;
    $selfPaymentRaw = '0';
    $companyPaymentRaw = '0';
    $selfTotalRaw = '0';
    if (count($amountTokens) === 1) {
      $selfTotalRaw = $amountTokens[0];
    } elseif (count($amountTokens) === 2) {
      $selfPaymentRaw = $amountTokens[0];
      $companyPaymentRaw = $amountTokens[1];
    } elseif (count($amountTokens) >= 3) {
      $selfPaymentRaw = $amountTokens[0] ?? '0';
      $companyPaymentRaw = $amountTokens[1] ?? '0';
      $selfTotalRaw = $amountTokens ? end($amountTokens) : '0';
    }
    $billingNote = trim(implode(' ', $noteTokens));
    $identityType = '';
    $changeType = '';

    $selfPayment = normalize_amount($selfPaymentRaw);
    $companyPayment = normalize_amount($companyPaymentRaw);
    $selfTotal = normalize_amount($selfTotalRaw);
    if ($selfTotal === 0) {
      $selfTotal = $selfPayment + $companyPayment;
    }
    $insuranceFee = normalize_amount($insuranceFee);
    if ($insuranceFee === 0) {
      $insuranceFee = $selfPayment + $companyPayment;
    }
    $records[] = [
      'insurance_fee' => $insuranceFee,
      'dependent_name' => $dependent,
      'id_number' => $idNumber,
      'birth' => normalize_pdf_date($birthRaw),
      'identity_type' => $identityType,
      'change_type' => $changeType,
      'billing_note' => $billingNote,
      'self_payment' => $selfPayment,
      'company_payment' => $companyPayment,
      'self_total' => $selfTotal,
      'note' => '',
    ];
  }
  return $records;
}

function build_health_column_map(array $header): array {
  $map = [];
  foreach ($header as $idx => $label) {
    $key = normalize_header_label($label);
    switch ($key) {
      case '保險費':
      case '保費':
        $map['insurance'] = $idx;
        break;
      case '眷屬姓名':
      case '姓名':
        $map['dependent'] = $idx;
        break;
      case '身分證號':
      case '身份證號':
        $map['id'] = $idx;
        break;
      case '出生日期':
      case '生日':
        $map['birth'] = $idx;
        break;
      case '身分別':
        $map['identity'] = $idx;
        break;
      case '異動別':
        $map['change'] = $idx;
        break;
      case '計費註記':
      case '註記':
        $map['note'] = $idx;
        break;
      case '自付':
      case '致付':
      case '致富':
      case '自付保費':
      case '自付保險費':
      case '自付保費合計':
        $map['self'] = $idx;
        break;
      case '單位負擔':
      case '雇主負擔':
      case '單位負擔保費':
      case '單位保費':
        $map['company'] = $idx;
        break;
      case '自付保費合計':
      case '自付合計':
      case '自付保費合計(含眷屬)':
        $map['total'] = $idx;
        break;
      default:
        break;
    }
  }
  return $map;
}

function fetch_health_records(PDO $pdo, int $rocYear, int $month): array {
  $stmt = $pdo->prepare(
    'SELECT id, roc_year, month, insurance_fee, dependent_name, id_number, birth, identity_type, change_type, billing_note, self_payment, company_payment, self_total, note
     FROM health_roster_records
     WHERE roc_year = ? AND month = ?
     ORDER BY dependent_name'
  );
  $stmt->execute([$rocYear, $month]);
  $records = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $records[] = [
      'id' => (int) ($row['id'] ?? 0),
      'roc_year' => (int) ($row['roc_year'] ?? 0),
      'month' => (int) ($row['month'] ?? 0),
      'insurance_fee' => (int) ($row['insurance_fee'] ?? 0),
      'dependent_name' => (string) ($row['dependent_name'] ?? ''),
      'id_number' => (string) ($row['id_number'] ?? ''),
      'birth' => (string) ($row['birth'] ?? ''),
      'identity_type' => (string) ($row['identity_type'] ?? ''),
      'change_type' => (string) ($row['change_type'] ?? ''),
      'billing_note' => (string) ($row['billing_note'] ?? ''),
      'self_payment' => (int) ($row['self_payment'] ?? 0),
      'company_payment' => (int) ($row['company_payment'] ?? 0),
      'self_total' => (int) ($row['self_total'] ?? 0),
      'note' => (string) ($row['note'] ?? ''),
    ];
  }
  return $records;
}

function normalize_amount($value): int {
  return (int) round((float) preg_replace('/[^\d.\-]/', '', (string) $value));
}

function normalize_pdf_date(string $value): string {
  $digits = preg_replace('/[^0-9]/', '', $value);
  if (strlen($digits) === 7) {
    return sprintf('%s-%s-%s', substr($digits, 0, 3), substr($digits, 3, 2), substr($digits, 5, 2));
  }
  if (strlen($digits) === 6) {
    return sprintf('%s-%s-%s', substr($digits, 0, 2), substr($digits, 2, 2), substr($digits, 4, 2));
  }
  return $value;
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
  $baseName = trim($baseName, '-') ?: 'health';
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
