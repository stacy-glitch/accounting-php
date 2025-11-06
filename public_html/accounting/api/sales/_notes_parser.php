<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';

function notes_parse_file(string $path, string $extension): array {
  $extension = strtolower($extension);
  $parseError = '';
  $records = [];

  if (in_array($extension, ['csv', 'xlsx'], true)) {
    try {
      $rows = $extension === 'csv'
        ? notes_read_csv_rows($path)
        : notes_read_xlsx_rows($path);
      $records = notes_build_records($rows);
    } catch (RuntimeException $e) {
      $parseError = $e->getMessage();
    }
  } elseif ($extension === 'xls') {
    $parseError = '目前僅支援解析 CSV 或 XLSX，已跳過內容讀取。';
  } elseif ($extension === 'ods') {
    $parseError = '目前尚未支援解析 ODS 格式，已跳過內容讀取。';
  } else {
    $parseError = '此副檔名暫不支援解析，已跳過內容讀取。';
  }

  return [
    'records' => $records,
    'parse_error' => $parseError,
  ];
}

function notes_read_csv_rows(string $path): array {
  $rows = [];
  $handle = fopen($path, 'r');
  if ($handle === false) {
    throw new RuntimeException('無法讀取 CSV 檔案');
  }
  while (($data = fgetcsv($handle)) !== false) {
    $rows[] = array_map(static function ($value) {
      return trim(notes_remove_bom((string) $value));
    }, $data);
  }
  fclose($handle);
  return $rows;
}

function notes_read_xlsx_rows(string $path): array {
  $zip = new ZipArchive();
  if ($zip->open($path) !== true) {
    throw new RuntimeException('無法開啟 XLSX 檔案');
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
    throw new RuntimeException('找不到工作表資料');
  }
  $sheet = simplexml_load_string($sheetXml);
  if ($sheet === false) {
    $zip->close();
    throw new RuntimeException('解析 XLSX 內容失敗');
  }

  $rows = [];
  foreach ($sheet->sheetData->row as $row) {
    $rowData = [];
    foreach ($row->c as $cell) {
      $ref = (string) $cell['r'];
      $col = notes_column_index_from_ref($ref);
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

function notes_column_index_from_ref(string $ref): int {
  $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
  $index = 0;
  $length = strlen($letters);
  for ($i = 0; $i < $length; $i++) {
    $index = $index * 26 + (ord($letters[$i]) - 64);
  }
  return max(0, $index - 1);
}

function notes_build_records(array $rows): array {
  if (count($rows) <= 1) {
    return [];
  }
  $header = array_map(static function ($value) {
    return notes_normalize_header_label($value);
  }, array_shift($rows));
  $mapping = notes_build_header_mapping($header);
  if (!$mapping) {
    throw new RuntimeException('檔案缺少必要欄位（客戶或票號）');
  }

  $records = [];
  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $record = notes_extract_values($row, $mapping);
    if (notes_row_is_empty($record)) {
      continue;
    }
    $records[] = [
      'customer' => $record['customer'],
      'note_number' => $record['note_number'],
      'amount' => notes_parse_amount($record['amount']),
      'total' => notes_parse_amount($record['total']),
      'entry_date' => notes_normalize_text($record['entry_date']),
      'due_date' => notes_normalize_text($record['due_date']),
      'deposit_date' => notes_normalize_text($record['deposit_date']),
      'months' => notes_normalize_month_list($record['months']),
      'note' => notes_normalize_text($record['note']),
    ];
  }
  return $records;
}

function notes_build_header_mapping(array $header): array {
  $aliases = [
    'customer' => ['客戶', '客戶代號', '客戶(代號)', '客戶（代號）', '客戶名稱', 'customer', '客戶代碼', 'customer_code'],
    'note_number' => ['票號', '票據號碼', '票據', 'note', 'note_number', 'number', '票號碼'],
    'amount' => ['金額', 'amount'],
    'total' => ['總計', '總額', '共計', 'total'],
    'entry_date' => ['登記日', '登錄日', 'entrydate', 'registerdate'],
    'due_date' => ['到期日', 'duedate'],
    'deposit_date' => ['入帳日', '入賬日', 'depositdate'],
    'months' => ['帳款月份', '帳款月', '月份', 'months'],
    'note' => ['備註', '備註說明', '備考', 'note', 'remark'],
  ];

  $mapping = [];
  foreach ($header as $index => $label) {
    if ($label === '') {
      continue;
    }
    foreach ($aliases as $field => $candidates) {
      if (in_array($label, $candidates, true)) {
        $mapping[$field] = $index;
        break;
      }
    }
  }

  if (!isset($mapping['customer']) || !isset($mapping['note_number'])) {
    return [];
  }

  return $mapping;
}

function notes_extract_values(array $row, array $mapping): array {
  $values = [];
  foreach ($mapping as $field => $index) {
    $values[$field] = isset($row[$index]) ? (string) $row[$index] : '';
  }
  return $values;
}

function notes_row_is_empty(array $record): bool {
  foreach ($record as $value) {
    if (trim((string) $value) !== '') {
      return false;
    }
  }
  return true;
}

function notes_normalize_header_label(string $label): string {
  $normalized = strtolower(trim(notes_remove_bom($label)));
  return preg_replace('/\s+/u', '', $normalized);
}

function notes_parse_amount($value): int {
  if (is_int($value)) {
    return $value;
  }
  if (is_float($value)) {
    return (int) round($value);
  }
  $text = trim((string) $value);
  if ($text === '') {
    return 0;
  }
  $normalized = str_replace([',', '，', '$', ' '], '', $text);
  if ($normalized === '' || !is_numeric($normalized)) {
    return 0;
  }
  return (int) round((float) $normalized);
}

function notes_normalize_text($value): string {
  return trim(notes_remove_bom((string) $value));
}

function notes_normalize_month_list($value): array {
  $text = trim((string) $value);
  if ($text === '') {
    return [];
  }
  $parts = preg_split('/[、,，\s]+/u', $text);
  $months = [];
  foreach ($parts as $part) {
    $trimmed = trim((string) $part);
    if ($trimmed !== '') {
      $months[] = $trimmed;
    }
  }
  return $months;
}

function notes_remove_bom(string $text): string {
  if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) {
    return substr($text, 3) ?: '';
  }
  return $text;
}
