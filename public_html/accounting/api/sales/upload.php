<?php
declare(strict_types=1);

require_once __DIR__ . '/_revenue.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_err('Method not allowed', 405);
}

$year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);
$month = filter_input(INPUT_POST, 'month', FILTER_VALIDATE_INT);
if (!is_int($year) || $year < 2000 || $year > 2100) {
  json_err('請提供正確的年份');
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

$originalName = (string) ($file['name'] ?? 'upload');
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$normalizedExtension = $extension === 'xls' ? 'xlsx' : $extension;
$allowed = ['csv', 'xlsx', 'pdf'];
if (!in_array($normalizedExtension, $allowed, true)) {
  json_err('僅支援上傳 CSV、XLSX 或 PDF 檔案');
}

$pdo = pdo();
ensure_sales_revenue_table($pdo);
$customers = fetch_customer_map($pdo);
$customerByCode = $customers['by_code'];
$customerByName = $customers['by_name'];

ensure_sales_alias_table($pdo);
$aliasMaps = fetch_sales_alias_map($pdo);
$aliasByKey = $aliasMaps['by_alias'];
$aliasByCode = $aliasMaps['by_code'];

$existingCodes = array_fill_keys(array_keys($customerByCode), true);
foreach ($aliasByCode as $code => $_) {
  if ($code !== '') {
    $existingCodes[$code] = true;
  }
}

$newAliases = [];

$yearMonth = sprintf('%04d%02d', $year, $month);
$uploadsRoot = realpath(__DIR__ . '/../../uploads');
if ($uploadsRoot === false) {
  json_err('找不到上傳根目錄', 500);
}
$targetDir = $uploadsRoot . '/sales/' . $yearMonth;
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
  json_err('無法建立上傳目錄');
}

$tmpPath = (string) ($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_file($tmpPath)) {
  json_err('找不到暫存檔案，請重新上傳');
}

if ($normalizedExtension === 'pdf') {
  try {
    $savedName = persist_uploaded_file($tmpPath, $targetDir, $originalName, $normalizedExtension);
  } catch (RuntimeException $e) {
    json_err($e->getMessage());
  }
  json_ok([
    'endpoint' => 'sales/upload',
    'message' => 'PDF 已儲存',
    'saved' => $savedName,
    'year' => $year,
    'month' => $month,
    'overwritten' => 0,
  ]);
}

$rows = $normalizedExtension === 'csv'
  ? read_csv_rows($tmpPath)
  : read_xlsx_rows($tmpPath);

$headerInfo = detect_header_info($rows);
$headerIndex = $headerInfo['index'];
$mapping = $headerInfo['mapping'];
if ($headerIndex < 0 || !$mapping) {
  json_err('找不到表頭（需包含「客戶」「運費」「稅金」「合計」等欄位）');
}
validate_required_columns($mapping, ['customer', 'freight', 'tax', 'total']);

$rows = array_slice($rows, $headerIndex + 1);

$records = [];
$missingCustomers = [];
foreach ($rows as $index => $row) {
  if (!is_array($row)) {
    continue;
  }
  $record = extract_record($row, $mapping);
  if (is_row_empty($record)) {
    continue;
  }
  $customerRaw = trim((string) ($record['customer'] ?? ''));
  $freightAmount = sales_parse_amount($record['freight'] ?? '');
  $taxAmount = sales_parse_amount($record['tax'] ?? '');
  $warehouseAmount = sales_parse_amount($record['warehouse_fee'] ?? '');
  $totalAmount = sales_parse_amount($record['total'] ?? '');
  $invoiceAmount = sales_parse_amount($record['invoice_amount'] ?? '');
  if ($totalAmount === 0 && $invoiceAmount > 0) {
    $totalAmount = $invoiceAmount;
  }

  $normalizedCustomer = preg_replace('/\s+/u', '', $customerRaw);
  $skipKeywords = ['客戶帳款', '載運日期', '總覽', '合計', '總計'];
  foreach ($skipKeywords as $keyword) {
    if ($normalizedCustomer !== '' && mb_strpos($normalizedCustomer, $keyword) !== false) {
      continue 2;
    }
  }

  $rawCustomer = $customerRaw;
  [$code, $customerName] = resolve_customer_reference(
    $rawCustomer,
    $customerByCode,
    $customerByName,
    $aliasByKey,
    $aliasByCode,
    $newAliases,
    $existingCodes
  );
  if ($code === '') {
    $rowNumber = $headerIndex + $index + 2;
    $missingCustomers[] = $rawCustomer !== '' ? $rawCustomer : ('第' . $rowNumber . '行缺少客戶資訊');
    continue;
  }
  if ($customerName === '' && isset($customerByCode[$code])) {
    $customerName = $customerByCode[$code];
  }
  if ($customerName === '' && isset($aliasByCode[$code])) {
    $customerName = $aliasByCode[$code];
  }

  $records[] = [
    'year' => $year,
    'month' => $month,
    'customer' => $code,
    'customer_name' => sales_limit_length($customerName ?? '', 150),
    'freight' => $freightAmount,
    'invoice_amount' => $freightAmount + $taxAmount,
    'tax' => $taxAmount,
    'warehouse_fee' => $warehouseAmount,
    'total' => $totalAmount,
    'actual_received' => null,
    'advance_total' => 0,
    'received_date' => '',
    'received_method' => '',
    'note' => '',
  ];
}

if ($missingCustomers) {
  $missingList = implode('、', array_unique(array_map('trim', $missingCustomers)));
  json_err('找不到客戶資訊：' . $missingList);
}

$records = aggregate_records_by_customer($records);

$overwritten = 0;
$savedName = '';

try {
  $pdo->beginTransaction();

  $deleteStmt = $pdo->prepare('DELETE FROM `sales_revenue` WHERE `year` = ? AND `month` = ?');
  $deleteStmt->execute([$year, $month]);
  $overwritten = (int) $deleteStmt->rowCount();

  if ($newAliases) {
    save_sales_aliases($pdo, $newAliases);
  }

  if ($records) {
    $insertStmt = $pdo->prepare(
      'INSERT INTO `sales_revenue`
        (`year`, `month`, `customer`, `customer_name`, `freight`, `invoice_amount`, `tax`, `warehouse_fee`, `total`, `actual_received`, `advance_total`, `received_date`, `received_method`, `note`)
       VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($records as $entry) {
      $insertStmt->execute([
        $entry['year'],
        $entry['month'],
        $entry['customer'],
        $entry['customer_name'],
        $entry['freight'],
        $entry['invoice_amount'],
        $entry['tax'],
        $entry['warehouse_fee'],
        $entry['total'],
        $entry['actual_received'],
        $entry['advance_total'],
        $entry['received_date'],
        $entry['received_method'],
        $entry['note'],
      ]);
    }
  }

  $savedName = persist_uploaded_file($tmpPath, $targetDir, $originalName, $normalizedExtension);
  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  if ($savedName !== '') {
    $savedPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $savedName;
    if (is_file($savedPath)) {
      @unlink($savedPath);
    }
  }

  if ($e instanceof RuntimeException) {
    json_err($e->getMessage());
  }

  json_err('匯入失敗：' . $e->getMessage(), 500);
}

json_ok([
  'endpoint' => 'sales/upload',
  'message' => '匯入完成',
  'inserted' => count($records),
  'overwritten' => $overwritten,
  'saved' => $savedName,
  'year' => $year,
  'month' => $month,
]);

function build_header_mapping(array $header): array {
  $aliases = [
    'customer' => ['customer', '客戶', '客戶代號', '代號', '戶名'],
    'freight' => ['freight', '運費'],
    'invoice_amount' => ['invoice_amount', '發票金額', '發票', '開立金額'],
    'tax' => ['tax', '稅金', '稅額'],
    'warehouse_fee' => ['warehouse_fee', '倉租', '代繳倉租', '代繼倉租', '代墊倉租'],
    'total' => ['total', '合計', '總金額'],
    'actual_received' => ['actual_received', '實收', '實際收款', '收款金額'],
    'advance_total' => ['advance_total', '代墊款', '代墊合計', '代墊支出'],
    'received_date' => ['received_date', '收款日期', '日期'],
    'received_method' => ['received_method', '收款方式', '付款方式'],
    'note' => ['note', '備註', '說明'],
  ];

  $mapping = [];
  foreach ($header as $index => $column) {
    $normalized = normalize_header($column);
    foreach ($aliases as $field => $keys) {
      if (in_array($normalized, array_map('normalize_header', $keys), true)) {
        $mapping[$field] = $index;
        break;
      }
    }
  }
  return $mapping;
}

function validate_required_columns(array $mapping, array $required): void {
  $missing = [];
  foreach ($required as $field) {
    if (!array_key_exists($field, $mapping)) {
      $missing[] = $field;
    }
  }
  if ($missing) {
    json_err('缺少必要欄位：' . implode('、', $missing));
  }
}

function extract_record(array $row, array $mapping): array {
  $record = [];
  foreach ($mapping as $field => $index) {
    $record[$field] = normalize_cell($row[$index] ?? '');
  }
  return $record;
}

function normalize_cell($value): string {
  if (is_array($value)) {
    return '';
  }
  return sales_normalize_text($value);
}

function is_row_empty(array $record): bool {
  foreach ($record as $value) {
    if (trim((string) $value) !== '') {
      return false;
    }
  }
  return true;
}

function normalize_header(string $text): string {
  $normalized = strtolower(trim(sales_remove_bom($text)));
  return preg_replace('/\s+/', '', $normalized);
}

function read_csv_rows(string $path): array {
  $rows = [];
  if (($handle = fopen($path, 'r')) === false) {
    json_err('無法讀取 CSV 檔案');
  }
  while (($data = fgetcsv($handle)) !== false) {
    $rows[] = array_map(static function ($value) {
      return trim(sales_remove_bom((string) $value));
    }, $data);
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
  $base = pathinfo($originalName, PATHINFO_FILENAME);
  $normalizedBase = preg_replace('/[^A-Za-z0-9_\-]+/', '-', $base);
  if ($normalizedBase === '' || $normalizedBase === '-' || $normalizedBase === null) {
    $normalizedBase = 'sales';
  }
  $filename = sprintf(
    '%s_%s.%s',
    trim($normalizedBase, '-'),
    date('YmdHis'),
    $extension
  );
  $destination = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

  if (!is_uploaded_file($tmpPath)) {
    throw new RuntimeException('暫存檔案已失效，請重新上傳');
  }

  if (!move_uploaded_file($tmpPath, $destination)) {
    throw new RuntimeException('儲存原始檔案失敗');
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
      return '檔案被系統阻擋';
    default:
      return '未知的上傳錯誤';
  }
}

function resolve_customer_reference(
  string $value,
  array $codeMap,
  array $nameMap,
  array &$aliasByKey,
  array &$aliasByCode,
  array &$newAliases,
  array &$existingCodes
): array {
  $raw = trim(sales_remove_bom($value));
  if ($raw === '') {
    return ['', ''];
  }

  $tokens = extract_customer_tokens($raw);

  foreach ($tokens as $token) {
    $code = normalize_customer_code($token);
    if ($code !== '' && isset($codeMap[$code])) {
      return [$code, $codeMap[$code]];
    }
  }

  foreach ($tokens as $token) {
    $nameKey = normalize_customer_name($token);
    if ($nameKey !== '' && isset($nameMap[$nameKey])) {
      $code = $nameMap[$nameKey];
      return [$code, $codeMap[$code] ?? ''];
    }
  }

  foreach ($tokens as $token) {
    $aliasKey = normalize_customer_name($token);
    if ($aliasKey !== '' && isset($aliasByKey[$aliasKey])) {
      $info = $aliasByKey[$aliasKey];
      $code = isset($info['code']) ? $info['code'] : '';
      if ($code === '') {
        continue;
      }
      $name = $info['name'] ?? '';
      if ($name === '' && isset($aliasByCode[$code])) {
        $name = $aliasByCode[$code];
      }
      if ($name === '' && isset($codeMap[$code])) {
        $name = $codeMap[$code];
      }
      return [$code, $name];
    }
  }

  $aliasKey = normalize_customer_name($raw);
  if ($aliasKey === '') {
    $aliasKey = md5($raw);
  }

  if (isset($aliasByKey[$aliasKey])) {
    $info = $aliasByKey[$aliasKey];
    $code = isset($info['code']) ? $info['code'] : '';
    $name = $info['name'] ?? '';
    if ($name === '' && isset($aliasByCode[$code])) {
      $name = $aliasByCode[$code];
    }
    if ($name === '' && isset($codeMap[$code])) {
      $name = $codeMap[$code];
    }
    return [$code, $name];
  }

  $displayName = sales_limit_length($raw, 150);
  $code = generate_temp_customer_code($existingCodes);

  $aliasByKey[$aliasKey] = [
    'code' => $code,
    'name' => $displayName,
  ];
  $aliasByCode[$code] = $displayName;
  $newAliases[$aliasKey] = [
    'code' => $code,
    'name' => $displayName,
  ];

  return [$code, $displayName];
}

function extract_customer_tokens(string $value): array {
  $tokens = [];
  $tokens[] = $value;

  $parenStripped = preg_replace('/[()（）]/u', '', $value);
  if ($parenStripped !== null && $parenStripped !== $value) {
    $tokens[] = trim($parenStripped);
  }

  $split = preg_split('/[\\s　,，;；:：\\-–—~\\/]+/u', $value);
  if (is_array($split)) {
    foreach ($split as $token) {
      $token = trim($token);
      if ($token !== '') {
        $tokens[] = $token;
      }
    }
  }

  return array_unique($tokens);
}

function aggregate_records_by_customer(array $records): array {
  if (empty($records)) {
    return [];
  }

  $numericFields = ['freight', 'invoice_amount', 'tax', 'warehouse_fee', 'total', 'advance_total'];
  $groups = [];

  foreach ($records as $record) {
    $code = $record['customer'];
    if (!isset($groups[$code])) {
      $groups[$code] = $record;
      continue;
    }

    foreach ($numericFields as $field) {
      $groups[$code][$field] = (int) ($groups[$code][$field] ?? 0) + (int) ($record[$field] ?? 0);
    }

    if (empty($groups[$code]['customer_name']) && !empty($record['customer_name'])) {
      $groups[$code]['customer_name'] = $record['customer_name'];
    }

    if (empty($groups[$code]['received_date']) && !empty($record['received_date'])) {
      $groups[$code]['received_date'] = $record['received_date'];
    }

    if (empty($groups[$code]['received_method']) && !empty($record['received_method'])) {
      $groups[$code]['received_method'] = $record['received_method'];
    }

    if (!empty($record['note'])) {
      if (empty($groups[$code]['note'])) {
        $groups[$code]['note'] = $record['note'];
      } elseif (strpos($groups[$code]['note'], $record['note']) === false) {
        $groups[$code]['note'] = sales_limit_length($groups[$code]['note'] . '；' . $record['note'], 255);
      }
    }
  }

  return array_values($groups);
}

function generate_temp_customer_code(array &$existingCodes): string {
  static $counter = 1;
  while (true) {
    $code = sprintf('TMP-%04d', $counter);
    $counter++;
    if (!isset($existingCodes[$code])) {
      $existingCodes[$code] = true;
      return $code;
    }
  }
}

function detect_header_info(array $rows): array {
  $requiredFields = ['customer', 'freight', 'tax', 'total'];
  foreach ($rows as $index => $row) {
    if (!is_array($row) || !count($row)) {
      continue;
    }
    $mapping = build_header_mapping(array_map('trim', $row));
    if (!$mapping) {
      continue;
    }
    $matches = 0;
    foreach ($requiredFields as $field) {
      if (isset($mapping[$field])) {
        $matches++;
      }
    }
    if ($matches >= 3) {
      return ['index' => $index, 'mapping' => $mapping];
    }
  }
  return ['index' => -1, 'mapping' => []];
}
