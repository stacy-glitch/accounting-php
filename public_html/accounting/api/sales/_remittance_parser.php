<?php
declare(strict_types=1);

require_once __DIR__ . '/_notes_parser.php';

function remittance_parse_file(string $path, string $extension): array {
  $extension = strtolower($extension);
  if (!in_array($extension, ['xlsx', 'csv'], true)) {
    throw new RuntimeException('目前僅支援上傳 XLSX 或 CSV 檔案');
  }

  $rows = $extension === 'csv' ? notes_read_csv_rows($path) : notes_read_xlsx_rows($path);
  $records = remittance_build_records($rows);

  return [
    'records' => $records,
    'parse_error' => '',
  ];
}

function remittance_build_records(array $rows): array {
  if (empty($rows)) {
    return [];
  }

  $mapping = [];
  while (!empty($rows) && !$mapping) {
    $header = array_shift($rows);
    if (is_array($header)) {
      $mapping = remittance_build_header_mapping($header);
    }
  }

  if (!$mapping) {
    throw new RuntimeException('檔案缺少必要欄位（至少需包含客戶或戶名）');
  }

  $records = [];
  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $record = [
      'customer' => remittance_extract_value($row, $mapping, 'customer'),
      'account' => remittance_extract_value($row, $mapping, 'account'),
      'bank' => remittance_extract_value($row, $mapping, 'bank'),
      'remark' => remittance_extract_value($row, $mapping, 'remark'),
    ];
    if (!remittance_record_has_content($record)) {
      continue;
    }
    $records[] = $record;
  }
  return $records;
}

function remittance_build_header_mapping(array $header): array {
  $aliases = [
    'customer' => ['客戶', '客戶名稱', '戶名', '戶名/公司', '戶名（公司）', '公司名稱', '收款人', 'account', 'customer'],
    'account' => ['帳戶', '帳號', '銀行帳號', '匯款帳號', '銀行帳戶', 'account', 'account_number'],
    'bank' => ['匯款銀行', '銀行', '銀行名稱', 'bank', 'accountbank'],
    'remark' => ['備註', '備考', '備註二', 'remark'],
  ];

  $normalizedHeader = array_map('remittance_normalize_label', $header);
  $mapping = [];
  foreach ($normalizedHeader as $index => $label) {
    if ($label === '') {
      continue;
    }
    foreach ($aliases as $field => $candidates) {
      if (in_array($label, remittance_normalize_aliases($candidates), true)) {
        $mapping[$field] = $index;
        break;
      }
    }
  }

  if (!isset($mapping['customer'])) {
    return [];
  }

  return $mapping;
}

function remittance_normalize_aliases(array $labels): array {
  return array_map('remittance_normalize_label', $labels);
}

function remittance_normalize_label(string $label): string {
  $value = strtolower(trim(notes_remove_bom($label)));
  $value = preg_replace('/[\s　]+/u', '', $value);
  $variants = [
    '（' => '(',
    '）' => ')',
  ];
  return strtr($value, $variants);
}

function remittance_extract_value(array $row, array $mapping, string $field): string {
  if (!isset($mapping[$field])) {
    return '';
  }
  $index = $mapping[$field];
  return isset($row[$index]) ? trim((string) $row[$index]) : '';
}

function remittance_record_has_content(array $record): bool {
  foreach ($record as $value) {
    if (trim((string) $value) !== '') {
      return true;
    }
  }
  return false;
}
