<?php
declare(strict_types=1);

require_once __DIR__ . '/_balances.php';
require_once __DIR__ . '/_entries.php';

const SUBJECT_ADVANCE_REPAY = '代墊款回補';

$pdo = pdo();
ensure_entries_table($pdo);

$code = isset($_GET['code']) ? trim((string) $_GET['code']) : '';

if ($code !== '') {
  handle_code_query($pdo, $code);
  return;
}

$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
validate_period($year, $month);

handle_month_query($pdo, $year, $month);

function handle_code_query(PDO $pdo, string $code): void {
  $normalizedCode = trim($code);
  if ($normalizedCode === '') {
    json_err('請輸入代號');
  }

  $records = fetch_entries_for_advances($pdo, ['code' => $normalizedCode]);
  if (!$records) {
    json_ok([
      'endpoint' => 'petty-cash/advances',
      'data' => [
        'code' => $normalizedCode,
        'outstanding' => [],
        'total_remaining' => 0,
      ],
    ]);
    return;
  }

  $outstanding = compute_outstanding_advances($records);
  $items = array_map(static function (array $row): array {
    return [
      'id' => $row['id'],
      'code' => $row['code'],
      'entry_date' => $row['entry_date'],
      'transaction_date' => $row['transaction_date'],
      'note' => $row['note'],
      'original_amount' => $row['original'],
      'remaining_amount' => $row['remaining'],
    ];
  }, $outstanding);

  $total = array_reduce($items, static function ($carry, $item) {
    return $carry + (int) ($item['remaining_amount'] ?? 0);
  }, 0);

  json_ok([
    'endpoint' => 'petty-cash/advances',
    'data' => [
      'code' => $normalizedCode,
      'outstanding' => $items,
      'total_remaining' => $total,
    ],
  ]);
}

function handle_month_query(PDO $pdo, int $year, int $month): void {
  $records = fetch_entries_for_advances($pdo);
  $outstanding = compute_outstanding_advances($records);

  $targetMonth = sprintf('%04d-%02d', $year, $month);

  $items = array_values(array_filter($outstanding, static function (array $row) use ($targetMonth): bool {
    if (empty($row['entry_date'])) {
      return false;
    }
    $prefix = substr($row['entry_date'], 0, 7);
    return $prefix === $targetMonth;
  }));

  $items = array_map(static function (array $row): array {
    return [
      'id' => $row['id'],
      'code' => $row['code'],
      'entry_date' => $row['entry_date'],
      'transaction_date' => $row['transaction_date'],
      'note' => $row['note'],
      'original_amount' => $row['original'],
      'remaining_amount' => $row['remaining'],
    ];
  }, $items);

  $total = array_reduce($items, static function ($carry, $item) {
    return $carry + (int) ($item['remaining_amount'] ?? 0);
  }, 0);

  json_ok([
    'endpoint' => 'petty-cash/advances',
    'data' => [
      'year' => $year,
      'month' => $month,
      'entries' => $items,
      'total_remaining' => $total,
    ],
  ]);
}

function compute_outstanding_advances(array $records): array {
  if (!$records) {
    return [];
  }

  $queue = [];

  foreach ($records as $record) {
    $advanceIncome = isset($record['advance_income']) ? (int) $record['advance_income'] : 0;
    $advanceExpense = isset($record['advance_expense']) ? (int) $record['advance_expense'] : 0;
    if ($advanceExpense === 0 && $advanceIncome === 0 && isset($record['advance'])) {
      $advanceExpense = (int) $record['advance'];
    }

    if ($advanceExpense > 0) {
      $queue[] = [
        'id' => $record['id'],
        'code' => $record['code'],
        'entry_date' => $record['entry_date'],
        'transaction_date' => $record['transaction_date'],
        'note' => $record['note'],
        'original' => $advanceExpense,
        'remaining' => $advanceExpense,
      ];
      continue;
    }

    if (!is_repayment_record($record)) {
      continue;
    }

    $repayment = get_repayment_amount($record);
    if ($repayment <= 0) {
      continue;
    }

    $queue = apply_repayment($queue, $repayment);
  }

  return array_values(array_filter($queue, static function (array $row): bool {
    return ($row['remaining'] ?? 0) > 0;
  }));
}

function apply_repayment(array $queue, int $amount): array {
  $remaining = $amount;
  $index = 0;

  while ($remaining > 0 && isset($queue[$index])) {
    $target = &$queue[$index];
    if (($target['remaining'] ?? 0) <= 0) {
      $index++;
      continue;
    }
    $deduction = min($target['remaining'], $remaining);
    $target['remaining'] -= $deduction;
    $remaining -= $deduction;
    if ($target['remaining'] <= 0) {
      $target['remaining'] = 0;
      $index++;
    }
  }

  return $queue;
}

function is_repayment_record(array $record): bool {
  $subject = isset($record['subject']) ? trim((string) $record['subject']) : '';
  return $subject === SUBJECT_ADVANCE_REPAY;
}

function get_repayment_amount(array $record): int {
  $advanceIncome = isset($record['advance_income']) ? (int) $record['advance_income'] : 0;
  $income = isset($record['income']) ? (int) $record['income'] : 0;
  $total = $advanceIncome + $income;
  return $total > 0 ? $total : 0;
}
