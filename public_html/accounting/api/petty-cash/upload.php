<?php
declare(strict_types=1);

use ZipArchive;

require_once __DIR__ . '/_balances.php';
require_once __DIR__ . '/_entries.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_err('Method not allowed', 405);
}

if (!isset($_FILES['file'])) {
  json_err('請選擇要上傳的檔案');
}

$file = $_FILES['file'];
$errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
if ($errorCode !== UPLOAD_ERR_OK) {
  json_err('檔案上傳失敗：' . upload_error_message($errorCode));
}

$year = isset($_POST['year']) ? (int) $_POST['year'] : (int) date('Y');
$month = isset($_POST['month']) ? (int) $_POST['month'] : (int) date('n');
$mode = isset($_POST['mode']) ? strtolower((string) $_POST['mode']) : 'replace';
$shouldReplace = $mode !== 'append';

validate_period($year, $month);

$originalName = $file['name'] ?? 'upload';
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$tmpPath = $file['tmp_name'];

if (!is_file($tmpPath)) {
  json_err('找不到暫存檔案，請重新上傳');
}

try {
  $rows = [];
  if ($extension === 'xlsx') {
    $rows = read_xlsx_rows($tmpPath);
  } elseif ($extension === 'csv') {
    $rows = read_csv_rows($tmpPath);
  } else {
    json_err('僅支援上傳 Excel (.xlsx) 或 CSV 檔案');
  }
} catch (Throwable $e) {
  json_err('解析檔案失敗：' . $e->getMessage());
}

if (empty($rows)) {
  json_err('檔案內沒有資料');
}

$header = array_map('trim', array_shift($rows));
$headerMap = build_header_index($header);

if (!isset($headerMap['entry_date'])) {
  json_err('找不到「登記日」欄位，請確認上傳格式');
}
if (!isset($headerMap['code']) || !isset($headerMap['subject'])) {
  json_err('找不到必要欄位（代號或會計科目）');
}

$pdo = null;

try {
  $pdo = pdo();
  ensure_entries_table($pdo);
  $pdo->beginTransaction();

  $deleted = 0;
  if ($shouldReplace) {
    $deleted = delete_entries_by_period($pdo, $year, $month);
  }

  $inserted = 0;
  $skipped = 0;

  foreach ($rows as $index => $row) {
    if (!is_array($row)) {
      continue;
    }
    $columns = array_values($row);
    $entryValue = get_cell_value($columns, $headerMap, 'entry_date');
    $entryDate = parse_import_date($entryValue, $year);
    if (!$entryDate) {
      $skipped += 1;
      continue;
    }

    $transactionValue = get_cell_value($columns, $headerMap, 'transaction_date');
    $transactionDate = parse_import_date($transactionValue, $year);

    $transactionMonth = '';
    $transactionMonthValue = get_cell_value($columns, $headerMap, 'transaction_month');
    if ($transactionDate) {
      $transactionMonth = normalize_transaction_month_from_iso($transactionDate);
    } elseif ($transactionMonthValue !== '') {
      $transactionMonth = normalize_transaction_month_value($transactionMonthValue);
      if ($transactionMonth === '') {
        $transactionMonth = '';
      }
    } else {
      $transactionMonth = normalize_transaction_month_from_iso($entryDate);
    }

    $code = trim((string) get_cell_value($columns, $headerMap, 'code'));
    $subject = trim((string) get_cell_value($columns, $headerMap, 'subject'));
    $note = trim((string) get_cell_value($columns, $headerMap, 'note'));
    $advanceStatus = trim((string) get_cell_value($columns, $headerMap, 'advance_status'));

    if ($code === '' && $subject === '') {
      $skipped += 1;
      continue;
    }

    $income = parse_import_amount(get_cell_value($columns, $headerMap, 'income'));
    $expense = parse_import_amount(get_cell_value($columns, $headerMap, 'expense'));
    $advance = parse_import_amount(get_cell_value($columns, $headerMap, 'advance'));

    if ($income === 0 && $expense === 0 && $advance === 0) {
      $skipped += 1;
      continue;
    }

    try {
      insert_entry($pdo, [
        'entry_date' => $entryDate,
        'transaction_date' => $transactionDate,
        'transaction_month' => $transactionMonth,
        'code' => $code,
        'subject' => $subject,
        'note' => $note,
        'income' => $income,
        'expense' => $expense,
        'advance' => $advance,
        'advance_status' => $advanceStatus,
        'invoice_path' => '',
      ]);
    } catch (Throwable $e) {
      $rowNumber = $index + 2; // +1 for zero-based, +1 for header row
      throw new RuntimeException(sprintf('第 %d 列匯入失敗：%s', $rowNumber, $e->getMessage()), 0, $e);
    }
    $inserted += 1;
  }

  $pdo->commit();

  json_ok([
    'message' => sprintf('匯入完成，新增 %d 筆資料。', $inserted),
    'data' => [
      'inserted' => $inserted,
      'deleted' => $deleted,
      'skipped' => $skipped,
      'filename' => $originalName,
      'mode' => $shouldReplace ? 'replace' : 'append',
    ],
  ]);
} catch (Throwable $e) {
  if ($pdo instanceof PDO && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  $message = $e->getMessage();
  if ($e->getPrevious()) {
    $message .= ' | 原始錯誤：' . $e->getPrevious()->getMessage();
  }
  json_err('匯入失敗：' . $message);
}

function build_header_index(array $header): array {
  $mapping = [
    'entry_date' => ['登記日', '登錄日', '入帳日', '日期'],
    'transaction_date' => ['交易日', '實際交易日期', '交易日期'],
    'transaction_month' => ['交易月份', '實際交易月份', '月份'],
    'code' => ['代號', '科目代號'],
    'subject' => ['會計科目', '科目名稱'],
    'note' => ['備註', '說明'],
    'income' => ['收入', '收入金額'],
    'expense' => ['支出', '支出金額'],
    'advance' => ['代墊款', '代墊金額', '代墊'],
    'advance_status' => ['代墊狀態', '狀態'],
  ];

  $index = [];
  foreach ($mapping as $key => $candidates) {
    $index[$key] = find_header_index($header, $candidates);
  }
  return $index;
}

function find_header_index(array $header, array $candidates): ?int {
  foreach ($header as $i => $label) {
    $normalized = normalize_header_label($label);
    foreach ($candidates as $candidate) {
      if ($normalized === normalize_header_label($candidate)) {
        return (int) $i;
      }
    }
  }
  return null;
}

function normalize_header_label(string $value): string {
  $value = preg_replace('/\s+/u', '', trim($value));
  return mb_strtolower($value ?? '', 'UTF-8');
}

function get_cell_value(array $row, array $map, string $key): string {
  if (!isset($map[$key]) || $map[$key] === null) {
    return '';
  }
  $index = (int) $map[$key];
  return isset($row[$index]) ? trim((string) $row[$index]) : '';
}

function parse_import_date($value, int $fallbackYear): ?string {
  if ($value === null) {
    return null;
  }
  if (is_numeric($value) && $value !== '') {
    $serial = (float) $value;
    if ($serial > 20000) {
      return excel_serial_to_iso($serial);
    }
  }
  $text = trim((string) $value);
  if ($text === '') {
    return null;
  }
  $text = str_replace(['年', '月', '日', '.', '／', '/', '－'], ['-', '-', '', '-', '-', '-', '-'], $text);
  $text = preg_replace('/\s+/u', '', $text);
  $text = explode('T', $text)[0];
  $text = explode(' ', $text)[0];

  if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m)) {
    $year = (int) $m[1];
    $month = (int) $m[2];
    $day = (int) $m[3];
    if ($year < 1911) {
      $year += 1911;
    }
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
  }

  if (preg_match('/^(\d{3})-(\d{1,2})-(\d{1,2})$/', $text, $m)) {
    $year = (int) $m[1] + 1911;
    $month = (int) $m[2];
    $day = (int) $m[3];
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
  }

  $digits = preg_replace('/\D+/', '', $text);
  if (strlen($digits) === 7) {
    $roc = (int) substr($digits, 0, 3);
    $month = (int) substr($digits, 3, 2);
    $day = (int) substr($digits, 5, 2);
    return sprintf('%04d-%02d-%02d', $roc + 1911, $month, $day);
  }
  if (strlen($digits) === 8) {
    $year = (int) substr($digits, 0, 4);
    $month = (int) substr($digits, 4, 2);
    $day = (int) substr($digits, 6, 2);
    if ($year < 1911) {
      $year += 1911;
    }
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
  }
  if (strlen($digits) === 4) {
    $month = (int) substr($digits, 0, 2);
    $day = (int) substr($digits, 2, 2);
    $year = $fallbackYear;
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
  }

  return null;
}

function parse_import_amount($value): int {
  if ($value === null) {
    return 0;
  }
  if (is_numeric($value)) {
    return (int) round((float) $value);
  }
  $text = trim((string) $value);
  if ($text === '') {
    return 0;
  }
  $text = str_replace([',', ' '], '', $text);
  if ($text === '' || !is_numeric($text)) {
    return 0;
  }
  return (int) round((float) $text);
}

function read_csv_rows(string $path): array {
  $rows = [];
  if (($handle = fopen($path, 'r')) === false) {
    throw new RuntimeException('無法讀取 CSV 檔案');
  }
  while (($data = fgetcsv($handle)) !== false) {
    $rows[] = array_map('trim', $data);
  }
  fclose($handle);
  return $rows;
}

function read_xlsx_rows(string $path): array {
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
      ksort($rowData);
      $rows[] = array_values($rowData);
    }
  }

  $zip->close();
  return $rows;
}

function column_index_from_ref(string $ref): int {
  $letters = preg_replace('/[^A-Z]/i', '', strtoupper($ref));
  $index = 0;
  $length = strlen($letters);
  for ($i = 0; $i < $length; $i += 1) {
    $index *= 26;
    $index += ord($letters[$i]) - 64;
  }
  return max(0, $index - 1);
}

function excel_serial_to_iso(float $serial): string {
  $timestamp = (int) round(($serial - 25569) * 86400);
  if ($timestamp < 0) {
    $timestamp = 0;
  }
  return gmdate('Y-m-d', $timestamp);
}

function upload_error_message(int $code): string {
  if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
    return '檔案過大';
  }
  if ($code === UPLOAD_ERR_PARTIAL) {
    return '檔案上傳不完整';
  }
  if ($code === UPLOAD_ERR_NO_FILE) {
    return '沒有選擇檔案';
  }
  if ($code === UPLOAD_ERR_NO_TMP_DIR) {
    return '找不到暫存資料夾';
  }
  if ($code === UPLOAD_ERR_CANT_WRITE) {
    return '無法寫入檔案';
  }
  if ($code === UPLOAD_ERR_EXTENSION) {
    return '檔案被擋住，請聯絡系統管理員';
  }
  return '未知的上傳錯誤';
}
