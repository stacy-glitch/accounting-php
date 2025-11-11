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

$originalName = (string) ($file['name'] ?? 'labor');
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$normalizedExtension = $extension === 'xls' ? 'xlsx' : $extension;
$allowed = ['pdf'];
if (!in_array($normalizedExtension, $allowed, true)) {
  json_err('僅支援上傳 PDF 檔案');
}

$tmpPath = (string) ($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_file($tmpPath)) {
  json_err('找不到暫存檔案，請重新上傳');
}

$records = parse_pdf_records($tmpPath);

if (!$records) {
  json_err('檔案中沒有可匯入的資料列');
}

$uploadsRoot = __DIR__ . '/../../uploads/payroll/labor';
if (!is_dir($uploadsRoot) && !mkdir($uploadsRoot, 0775, true) && !is_dir($uploadsRoot)) {
  json_err('無法建立上傳根目錄');
}

$yearMonth = sprintf('%04d%02d', $year, $month);
$targetDir = $uploadsRoot . '/' . $yearMonth;
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
  json_err('無法建立月份目錄');
}

$filename = persist_uploaded_file($tmpPath, $targetDir, $originalName, $normalizedExtension);
$relativePath = 'uploads/payroll/labor/' . $yearMonth . '/' . $filename;

$pdo = pdo();
try {
  $pdo->beginTransaction();
  $deleteStmt = $pdo->prepare('DELETE FROM labor_roster_records WHERE roc_year = ? AND month = ?');
  $deleteStmt->execute([$rocYear, $month]);

  $insertStmt = $pdo->prepare(
    'INSERT INTO labor_roster_records
      (roc_year, month, employee_code, employee_name, birth, labor_salary, health_salary, change_type, change_date, personal_share, company_share, note)
     VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );
  foreach ($records as $record) {
    $insertStmt->execute([
      $rocYear,
      $month,
      (string) ($record['employee_code'] ?? ''),
      (string) ($record['employee_name'] ?? ''),
      (string) ($record['birth'] ?? ''),
      normalize_amount($record['labor_salary'] ?? 0),
      normalize_amount($record['health_salary'] ?? 0),
      (string) ($record['change_type'] ?? ''),
      (string) ($record['change_date'] ?? ''),
      normalize_amount($record['personal_share'] ?? 0),
      normalize_amount($record['company_share'] ?? 0),
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

$stored = fetch_db_records($pdo, $rocYear, $month);

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

function extract_record(array $row, array $columnMap, int $rowNumber): ?array {
  $name = get_cell($row, $columnMap['name'] ?? null);
  $birth = get_cell($row, $columnMap['birth'] ?? null);
  $laborSalary = to_amount(get_cell($row, $columnMap['laborSalary'] ?? null));
  $healthSalary = to_amount(get_cell($row, $columnMap['healthSalary'] ?? null));
  $changeType = get_cell($row, $columnMap['changeType'] ?? null);
  $changeDate = get_cell($row, $columnMap['changeDate'] ?? null);
  $personalShare = to_amount(get_cell($row, $columnMap['personalShare'] ?? null));
  $companyShare = to_amount(get_cell($row, $columnMap['companyShare'] ?? null));

  $isEmpty =
    trim($name . $birth . $changeType . $changeDate) === '' &&
    $laborSalary === 0 &&
    $healthSalary === 0 &&
    $personalShare === 0 &&
    $companyShare === 0;
  if ($isEmpty) {
    return null;
  }

  $idSource = get_cell($row, $columnMap['serial'] ?? null);
  $employeeCode = $idSource !== '' ? $idSource : sprintf('row-%03d', $rowNumber);

  return [
    'employee_code' => (string) $employeeCode,
    'employee_name' => $name,
    'birth' => $birth,
    'labor_salary' => $laborSalary,
    'health_salary' => $healthSalary,
    'change_type' => $changeType,
    'change_date' => $changeDate,
    'personal_share' => $personalShare,
    'company_share' => $companyShare,
    'note' => '',
  ];
}

function parse_pdf_records(string $path): array {
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
  return extract_records_from_pdf_text($text);
}

function parse_spreadsheet_records(string $path, string $extension): array {
  $rows = $extension === 'csv' ? read_csv_rows($path) : read_xlsx_rows($path);
  if (count($rows) <= 1) {
    json_err('檔案內容不足，請確認格式');
  }
  $header = array_map('trim', array_shift($rows));
  $columnMap = build_column_map($header);
  if (!isset($columnMap['name'])) {
    json_err('檔案缺少「姓名」欄位，請確認是否使用正確模板');
  }
  $records = [];
  foreach ($rows as $index => $row) {
    $record = extract_record($row, $columnMap, $index + 2);
    if ($record === null) {
      continue;
    }
    $records[] = $record;
  }
  return $records;
}

function extract_records_from_pdf_text(string $text): array {
  $lines = preg_split('/\r\n|\r|\n/', $text);
  $records = [];
  $current = null;
  foreach ($lines as $line) {
    if (!is_string($line)) {
      continue;
    }
    $raw = rtrim($line, "\r\n");
    $trimmed = trim($raw);
    if ($trimmed === '') {
      continue;
    }

    if (preg_match('/^\d+\s+\S+/', $trimmed) && preg_match('/^\s*(\d+)\s+(\S+)\s+(\S+)\s+(\d{7})\s+(.+?)$/u', $raw, $match)) {
      if ($current) {
        finalize_pdf_record($current, $records);
      }
      $serial = (string) $match[1];
      $name = trim($match[2]);
      $idNo = trim($match[3]);
      $birthRaw = trim($match[4]);
      $rest = $match[5];

      // 取出薪資、異動資訊、餘下文字
      $restTokens = preg_split('/\s+/u', trim($rest));
      if (!$restTokens) {
        continue;
      }

      $laborSalary = array_shift($restTokens);
      $healthSalary = $laborSalary;
      if ($restTokens && preg_match('/^[\d,]+$/', $restTokens[0])) {
        $healthSalary = array_shift($restTokens);
      }
      $changeType = $restTokens ? array_shift($restTokens) : '';
      $changeDate = $restTokens ? array_shift($restTokens) : '';
      $tail = trim(implode(' ', $restTokens));

      $amountMatches = [];
      if (preg_match('/([\d,]+)\s+([\d,]+)\s*$/', $tail, $amountMatches)) {
        $personalShare = $amountMatches[1];
        $companyShare = $amountMatches[2];
        $noteText = trim(substr($tail, 0, -strlen($amountMatches[0])));
      } else {
        $personalShare = '0';
        $companyShare = '0';
        $noteText = $tail;
      }

      $current = [
        'employee_code' => $idNo !== '' ? $idNo : $serial,
        'employee_name' => $name,
        'birth' => normalize_pdf_date($birthRaw),
        'labor_salary' => to_amount($laborSalary),
        'health_salary' => to_amount($healthSalary),
        'change_type' => $changeType,
        'change_date' => normalize_pdf_date($changeDate),
        'personal_share' => to_amount($personalShare),
        'company_share' => to_amount($companyShare),
        'noteParts' => [],
      ];
      continue;
    }

    // 目前不保留備註欄位內容
  }

  if ($current) {
    finalize_pdf_record($current, $records);
  }

  return $records;
}

function finalize_pdf_record(array &$current, array &$records): void {
  unset($current['noteParts']);
  $current['note'] = '';
  $records[] = $current;
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

function build_column_map(array $header): array {
  $map = [];
  foreach ($header as $index => $label) {
    $key = normalize_header_label($label);
    if ($key === '') {
      continue;
    }
    switch ($key) {
      case '序號':
      case '序':
        $map['serial'] = $index;
        break;
      case '姓名':
        $map['name'] = $index;
        break;
      case '出生日期':
      case '生日':
        $map['birth'] = $index;
        break;
      case '勞保投保薪資':
      case '勞保薪資':
        $map['laborSalary'] = $index;
        break;
      case '健保投保薪資':
      case '災保投保薪資':
      case '健保投保金額':
        $map['healthSalary'] = $index;
        break;
      case '最近異動別':
      case '異動別':
        $map['changeType'] = $index;
        break;
      case '最近異動日期':
      case '異動日期':
        $map['changeDate'] = $index;
        break;
      case '個人負擔':
      case '保費個人負擔':
        $map['personalShare'] = $index;
        break;
      case '單位負擔':
      case '保費單位負擔':
        $map['companyShare'] = $index;
        break;
      case '備註':
        $map['note'] = $index;
        break;
      case '特殊身分別':
      case '特殊身份別':
        $map['special'] = $index;
        break;
      default:
        break;
    }
  }
  return $map;
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

function to_amount($value): int {
  if ($value === null || $value === '') {
    return 0;
  }
  if (is_numeric($value)) {
    return (int) round((float) $value);
  }
  $filtered = preg_replace('/[^\d\.\-]/', '', (string) $value);
  $num = (float) $filtered;
  return (int) round($num);
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

function fetch_db_records(PDO $pdo, int $rocYear, int $month): array {
  $stmt = $pdo->prepare(
    'SELECT id, roc_year, month, employee_code, employee_name, birth, labor_salary, health_salary, change_type, change_date, personal_share, company_share, note
     FROM labor_roster_records
     WHERE roc_year = ? AND month = ?
     ORDER BY employee_name'
  );
  $stmt->execute([$rocYear, $month]);
  $records = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $records[] = [
      'id' => (int) ($row['id'] ?? 0),
      'roc_year' => (int) ($row['roc_year'] ?? 0),
      'month' => (int) ($row['month'] ?? 0),
      'employee_code' => (string) ($row['employee_code'] ?? ''),
      'employee_name' => (string) ($row['employee_name'] ?? ''),
      'birth' => (string) ($row['birth'] ?? ''),
      'labor_salary' => (int) ($row['labor_salary'] ?? 0),
      'health_salary' => (int) ($row['health_salary'] ?? 0),
      'change_type' => (string) ($row['change_type'] ?? ''),
      'change_date' => (string) ($row['change_date'] ?? ''),
      'personal_share' => (int) ($row['personal_share'] ?? 0),
      'company_share' => (int) ($row['company_share'] ?? 0),
      'note' => (string) ($row['note'] ?? ''),
    ];
  }
  return $records;
}

function normalize_amount($value): int {
  return (int) round((float) preg_replace('/[^\d.\-]/', '', (string) $value));
}

function persist_uploaded_file(string $tmpPath, string $targetDir, string $originalName, string $extension): string {
  $baseName = substr(preg_replace('/[^A-Za-z0-9_-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME)), 0, 40);
  $baseName = trim($baseName, '-') ?: 'labor';
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
