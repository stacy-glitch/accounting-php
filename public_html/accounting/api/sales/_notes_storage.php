<?php
declare(strict_types=1);

function notes_parse_amount($value): int {
  if (is_int($value)) {
    return max(0, $value);
  }
  if (is_float($value)) {
    return max(0, (int) round($value));
  }
  $text = trim((string) $value);
  if ($text === '') {
    return 0;
  }
  $normalized = preg_replace('/[^\d\-]/', '', $text);
  if ($normalized === '' || !is_numeric($normalized)) {
    return 0;
  }
  return max(0, (int) $normalized);
}

function notes_normalize_text(string $value): string {
  return trim($value);
}

function notes_normalize_months($value): array {
  if (is_array($value)) {
    $list = $value;
  } else {
    $text = trim((string) $value);
    if ($text === '') {
      return [];
    }
    $list = preg_split('/[,，、\s]+/u', $text) ?: [];
  }
  $months = array_values(
    array_filter(
      array_map(static function ($item) {
        return trim((string) $item);
      }, $list),
      static function ($item) {
        return $item !== '';
      }
    )
  );
  sort($months, SORT_STRING);
  return $months;
}

function notes_normalize_date_key($value): string {
  $text = trim((string) $value);
  if ($text === '') {
    return '';
  }

  $patterns = [
    '/^(\d{2,3})年(\d{1,2})月(\d{1,2})日$/u' => static function (array $m): array {
      return [((int) $m[1]) + 1911, (int) $m[2], (int) $m[3]];
    },
    '/^(\d{2,3})[\/](\d{1,2})[\/](\d{1,2})$/u' => static function (array $m): array {
      return [((int) $m[1]) + 1911, (int) $m[2], (int) $m[3]];
    },
    '/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/u' => static function (array $m): array {
      return [(int) $m[1], (int) $m[2], (int) $m[3]];
    },
  ];

  foreach ($patterns as $pattern => $converter) {
    if (preg_match($pattern, $text, $matches)) {
      [$year, $month, $day] = $converter($matches);
      return sprintf('%04d-%02d-%02d', $year, max(1, min(12, $month)), max(1, min(31, $day)));
    }
  }

  return $text;
}

function notes_record_signature(array $record): string {
  $months = notes_normalize_months($record['months'] ?? []);
  sort($months);
  $parts = [
    strtoupper(trim((string) ($record['customer_code'] ?? $record['customer'] ?? ''))),
    trim((string) ($record['customer_name'] ?? '')),
    trim((string) ($record['note_number'] ?? $record['number'] ?? '')),
    notes_normalize_date_key($record['entry_date'] ?? ''),
    notes_normalize_date_key($record['due_date'] ?? ''),
    notes_normalize_date_key($record['deposit_date'] ?? ''),
    (string) notes_parse_amount($record['amount'] ?? 0),
    implode(',', $months),
    trim((string) ($record['note'] ?? '')),
  ];

  return implode('||', $parts);
}

function notes_assign_record_key(array $record): array {
  if (!isset($record['record_key']) || $record['record_key'] === '') {
    $record['record_key'] = notes_record_signature($record);
  }
  return $record;
}
