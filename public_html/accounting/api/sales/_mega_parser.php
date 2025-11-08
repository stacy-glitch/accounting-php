<?php
declare(strict_types=1);

require_once __DIR__ . '/_notes_parser.php';

function mega_parse_file(string $path, string $extension): array {
  $extension = strtolower($extension);
  $records = [];
  $parseError = '';

  try {
    if ($extension === 'csv') {
      $rows = notes_read_csv_rows($path);
      $records = mega_build_records($rows);
    } elseif ($extension === 'xlsx') {
      $rows = notes_read_xlsx_rows($path);
      $records = mega_build_records($rows);
    } elseif ($extension === 'xls') {
      $parseError = '目前尚未支援解析 XLS，已儲存原檔供參考。';
    } else {
      $parseError = '目前僅能解析 CSV、XLS、XLSX，其他格式將僅儲存原檔。';
    }
  } catch (Throwable $e) {
    $parseError = $e->getMessage();
  }

  return [
    'records' => $records,
    'parse_error' => $parseError,
  ];
}

function mega_build_records(array $rows): array {
  if (count($rows) <= 1) {
    return [];
  }

  $mapping = [];
  while (!empty($rows) && !$mapping) {
    $candidate = array_shift($rows);
    if (!is_array($candidate)) {
      continue;
    }
    $normalized = array_map('mega_normalize_header', $candidate);
    $mapping = mega_build_header_mapping($normalized);
  }

  if (!$mapping) {
    throw new RuntimeException('檔案缺少必要欄位（帳務日期、摘要或金額）');
  }

  $records = [];
  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $record = [];
    foreach ($mapping as $field => $index) {
      $record[$field] = isset($row[$index]) ? trim((string) $row[$index]) : '';
    }
    if (!mega_record_has_content($record)) {
      continue;
    }
    $records[] = [
      'transaction_date' => mega_format_transaction_date($record['transaction_date'] ?? ''),
      'description' => $record['description'] ?? '',
      'expense' => mega_parse_amount($record['expense'] ?? ''),
      'income' => mega_parse_amount($record['income'] ?? ''),
      'balance' => mega_parse_amount($record['balance'] ?? ''),
      'note' => mega_clean_note($record['note'] ?? ''),
      'reconciliation' => $record['reconciliation'] ?? '',
      'form' => $record['form'] ?? '',
    ];
  }
  return $records;
}

function mega_normalize_header(string $label): string {
  $normalized = mb_strtolower($label);
  $normalized = preg_replace('/[\s　]+/u', '', $normalized);
  $normalized = str_replace(['(', ')', '（', '）', ':', '：', '-', '_'], '', $normalized);
  $variants = [
    '⽇' => '日',
    '⼾' => '戶',
    '⾦' => '金',
    '⽀' => '支',
    '⼊' => '入',
    '⽬' => '目',
  ];
  $normalized = strtr($normalized, $variants);
  return trim($normalized);
}

function mega_build_header_mapping(array $header): array {
  $aliases = [
    'transaction_date' => ['帳務日期', '交易日期', '日期', 'date'],
    'description' => ['摘要', '說明', 'description'],
    'expense' => ['支出金額', '支出', '付款', 'debit'],
    'income' => ['存入金額', '收入金額', '收入', 'credit'],
    'balance' => ['帳戶餘額', '餘額', '結餘', 'balance'],
    'note' => ['備註', '備考', 'note'],
    'reconciliation' => ['對帳', '勾稽', 'reconcile'],
    'form' => ['對應表單', '表單', 'form'],
  ];

  $normalizedAliases = [];
  foreach ($aliases as $field => $candidates) {
    $normalizedAliases[$field] = array_map('mega_normalize_header', $candidates);
  }

  $mapping = [];
  foreach ($header as $index => $label) {
    if ($label === '') {
      continue;
    }
    $normalized = mega_normalize_header($label);
    foreach ($aliases as $field => $candidates) {
      if (in_array($label, $candidates, true)) {
        $mapping[$field] = $index;
        break;
      }
      if ($normalized !== '' && in_array($normalized, $normalizedAliases[$field], true)) {
        $mapping[$field] = $index;
        break;
      }
    }
  }

  if (!isset($mapping['transaction_date']) || !isset($mapping['description'])) {
    return [];
  }
  if (!isset($mapping['expense']) && !isset($mapping['income'])) {
    return [];
  }
  return $mapping;
}

function mega_record_has_content(array $record): bool {
  foreach ($record as $value) {
    if (trim((string) $value) !== '') {
      return true;
    }
  }
  return false;
}

function mega_parse_amount($value): int {
  if (is_numeric($value)) {
    return (int) round((float) $value);
  }
  $text = trim((string) $value);
  if ($text === '') {
    return 0;
  }
  $normalized = preg_replace('/[^0-9\-]/u', '', $text);
  if ($normalized === '' || !is_numeric($normalized)) {
    return 0;
  }
  return (int) round((float) $normalized);
}

function mega_clean_note(string $value): string {
  $text = trim($value);
  $text = preg_replace('/補通訊[:：]０４－２２２５２７７７/iu', '', $text);
  $text = str_replace('一成運輸報關股份有限公司', '', $text);
  return trim($text);
}

function mega_format_transaction_date(string $value): string {
  $text = trim($value);
  if ($text === '') {
    return '';
  }
  if (preg_match('/^(\\d{4})[-\\/](\\d{1,2})[-\\/](\\d{1,2})/', $text, $m)) {
    $year = (int)$m[1];
    $month = (int)$m[2];
    $day = (int)$m[3];
  } elseif (preg_match('/^(\\d{4})(\\d{2})(\\d{2})/', $text, $m)) {
    $year = (int)$m[1];
    $month = (int)$m[2];
    $day = (int)$m[3];
  } else {
    return $text;
  }
  $roc = $year - 1911;
  if ($roc <= 0) {
    return $text;
  }
  return $roc . '/' . $month . '/' . $day;
}
