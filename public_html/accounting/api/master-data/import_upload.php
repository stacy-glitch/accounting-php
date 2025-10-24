<?php
use PDO;
use PDOStatement;
use ZipArchive;

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

$category = $groups[$tab];
$categoryDir = $baseDir . '/master-data/' . $category;
$pendingDir = $categoryDir . '/pending';
$processedDir = $categoryDir . '/processed';
$failedDir = $categoryDir . '/failed';

$metaFile = $pendingDir . '/' . $id . '.json';
if (!is_file($metaFile)) {
  json_err('找不到指定的檔案');
}

$meta = json_decode(file_get_contents($metaFile), true);
$savedName = $meta['savedName'] ?? '';
$extension = strtolower($meta['extension'] ?? pathinfo($savedName, PATHINFO_EXTENSION));
$filePath = $pendingDir . '/' . $savedName;

if (!is_file($filePath)) {
  json_err('原始檔案不存在');
}

$result = [
  'message' => '',
  'errors' => [],
];

try {
  if ($extension === 'xlsx') {
    $result = import_xlsx($tab, $filePath);
    $meta['status'] = empty($result['errors']) ? 'processed' : 'processed_with_errors';
  } elseif ($extension === 'pdf') {
    $result['message'] = 'PDF 已儲存，請人工檢閱並匯入資料。';
    $meta['status'] = 'archived';
  } elseif ($extension === 'jpg' || $extension === 'jpeg') {
    $result['message'] = '圖片已儲存 (JPG)。';
    $meta['status'] = 'archived';
  } else {
    throw new RuntimeException('此檔案格式暫不支援匯入。');
  }

  $meta['processedAt'] = date('c');
  $meta['lastResult'] = $result;

  $targetDir = $meta['status'] === 'failed' ? $failedDir : $processedDir;
  if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
    throw new RuntimeException('無法建立儲存目錄');
  }

  rename($filePath, $targetDir . '/' . $savedName);
  @unlink($metaFile);
  file_put_contents($targetDir . '/' . $id . '.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

  json_ok($result);
} catch (Throwable $e) {
  $meta['status'] = 'failed';
  $meta['processedAt'] = date('c');
  $meta['lastResult'] = ['message' => $e->getMessage(), 'errors' => []];
  file_put_contents($failedDir . '/' . $id . '.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  if (is_file($filePath)) {
    rename($filePath, $failedDir . '/' . $savedName);
  }
  @unlink($metaFile);
  json_err('匯入失敗：' . $e->getMessage());
}

function import_xlsx(string $tab, string $path): array
{
  $rows = read_xlsx_rows($path);
  if (empty($rows)) {
    throw new RuntimeException('檔案內沒有資料');
  }

  $header = array_map('trim', array_shift($rows));
  $pdo = pdo();
  switch ($tab) {
    case 'customers':
      return import_customers($pdo, $header, $rows);
    case 'vehicles':
      return import_vehicles($pdo, $header, $rows);
    case 'employees':
      return import_employees($pdo, $header, $rows);
    case 'accounts':
      return import_accounts($pdo, $header, $rows);
    default:
      throw new RuntimeException('未知的資料類別');
  }
}

function read_xlsx_rows(string $path): array
{
  $zip = new ZipArchive();
  if ($zip->open($path) !== true) {
    throw new RuntimeException('無法開啟 XLSX 檔案');
  }

  $strings = [];
  if (($shared = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
    $sx = simplexml_load_string($shared);
    if ($sx !== false) {
      foreach ($sx->si as $si) {
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
    throw new RuntimeException('找不到工作表資料');
  }
  $sheet = simplexml_load_string($sheetXml);
  if ($sheet === false) {
    throw new RuntimeException('解析工作表失敗');
  }

  $rows = [];
  foreach ($sheet->sheetData->row as $row) {
    $rowData = [];
    foreach ($row->c as $c) {
      $ref = (string) $c['r'];
      $col = column_index_from_ref($ref);
      $value = '';
      if (isset($c->v)) {
        $v = (string) $c->v;
        if ((string) $c['t'] === 's') {
          $value = $strings[(int) $v] ?? '';
        } else {
          $value = $v;
        }
      }
      $rowData[$col] = trim($value);
    }
    if (!empty($rowData)) {
      $maxIndex = !empty($rowData) ? max(array_keys($rowData)) : -1;
      $complete = [];
      for ($i = 0; $i <= $maxIndex; $i++) {
        $complete[] = $rowData[$i] ?? '';
      }
      $rows[] = $complete;
    }
  }
  $zip->close();
  return $rows;
}

function column_index_from_ref(string $ref): int
{
  $letters = preg_replace('/\d/', '', $ref);
  $letters = strtoupper($letters);
  $index = 0;
  $len = strlen($letters);
  for ($i = 0; $i < $len; $i++) {
    $index = $index * 26 + (ord($letters[$i]) - 64);
  }
  return $index - 1;
}

function import_customers(PDO $pdo, array $header, array $rows): array
{
  $expected = ['代號', '客戶名稱', '統一編號', '聯絡人', '電話'];
  $map = build_column_map($header, $expected);
  $stmt = $pdo->prepare('INSERT INTO customers (code, name, tax_id, contact, phone) VALUES (:code, :name, :tax_id, :contact, :phone) ON DUPLICATE KEY UPDATE name = VALUES(name), tax_id = VALUES(tax_id), contact = VALUES(contact), phone = VALUES(phone)');
  return execute_import($stmt, $rows, $map, [
    'code' => '代號',
    'name' => '客戶名稱',
    'tax_id' => '統一編號',
    'contact' => '聯絡人',
    'phone' => '電話',
  ], ['code', 'name']);
}

function import_vehicles(PDO $pdo, array $header, array $rows): array
{
  $expected = ['代號', '車牌號碼', '車型', '廠牌', '司機', '行照', '駕照'];
  $map = build_column_map($header, $expected);
  $stmt = $pdo->prepare('INSERT INTO vehicles (code, plate, model, brand, driver, license, permit) VALUES (:code, :plate, :model, :brand, :driver, :license, :permit) ON DUPLICATE KEY UPDATE plate = VALUES(plate), model = VALUES(model), brand = VALUES(brand), driver = VALUES(driver), license = VALUES(license), permit = VALUES(permit)');
  return execute_import($stmt, $rows, $map, [
    'code' => '代號',
    'plate' => '車牌號碼',
    'model' => '車型',
    'brand' => '廠牌',
    'driver' => '司機',
    'license' => '行照',
    'permit' => '駕照',
  ], ['code', 'plate']);
}

function import_employees(PDO $pdo, array $header, array $rows): array
{
  $expected = ['代號', '員工姓名', '電話'];
  $map = build_column_map($header, $expected);
  $stmt = $pdo->prepare('INSERT INTO employees (code, name, phone) VALUES (:code, :name, :phone) ON DUPLICATE KEY UPDATE name = VALUES(name), phone = VALUES(phone)');
  return execute_import($stmt, $rows, $map, [
    'code' => '代號',
    'name' => '員工姓名',
    'phone' => '電話',
  ], ['code', 'name']);
}

function import_accounts(PDO $pdo, array $header, array $rows): array
{
  $expected = ['對應表單', '會計科目'];
  $map = build_column_map($header, $expected);
  $stmt = $pdo->prepare('INSERT INTO account_mappings (name, mapping) VALUES (:name, :mapping) ON DUPLICATE KEY UPDATE mapping = VALUES(mapping)');
  return execute_import($stmt, $rows, $map, [
    'name' => '對應表單',
    'mapping' => '會計科目',
  ], ['name', 'mapping']);
}

function build_column_map(array $header, array $expected): array
{
  $map = [];
  foreach ($expected as $label) {
    $index = array_search($label, $header, true);
    if ($index === false) {
      throw new RuntimeException('欄位 "' . $label . '" 未在檔案中找到');
    }
    $map[$label] = $index;
  }
  return $map;
}

function execute_import(PDOStatement $stmt, array $rows, array $map, array $fieldMap, array $requiredFields): array
{
  $inserted = 0;
  $updated = 0;
  $errors = [];

  foreach ($rows as $rowIndex => $row) {
    $data = [];
    $isEmpty = true;
    foreach ($fieldMap as $field => $label) {
      $value = isset($map[$label], $row[$map[$label]]) ? trim($row[$map[$label]]) : '';
      $data[$field] = $value;
      if ($value !== '') {
        $isEmpty = false;
      }
    }

    if ($isEmpty) {
      continue;
    }

    foreach ($requiredFields as $field) {
      if (($data[$field] ?? '') === '') {
        $errors[] = '第 ' . ($rowIndex + 2) . ' 行欄位「' . ($fieldMap[$field] ?? $field) . '」不可空白';
        continue 2;
      }
    }

    try {
      $stmt->execute($data);
      $affected = $stmt->rowCount();
      if ($affected === 1) {
        $inserted++;
      } elseif ($affected === 2) {
        $updated++;
      } else {
        // 某些資料庫在 ON DUPLICATE KEY UPDATE 會回傳 0，視為更新
        $updated++;
      }
    } catch (Throwable $e) {
      $errors[] = '第 ' . ($rowIndex + 2) . ' 行匯入失敗：' . $e->getMessage();
    }
  }

  $message = '匯入完成：新增 ' . $inserted . ' 筆，更新 ' . $updated . ' 筆';
  if (!empty($errors)) {
    $message .= '，其中 ' . count($errors) . ' 筆失敗';
  }

  return [
    'message' => $message,
    'inserted' => $inserted,
    'updated' => $updated,
    'errors' => $errors,
  ];
}
