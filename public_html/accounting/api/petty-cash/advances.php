<?php
declare(strict_types=1);

require_once __DIR__ . '/_balances.php';
require_once __DIR__ . '/_entries.php';
require_once __DIR__ . '/_advance_links.php';

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
  $outstanding = attach_advance_links($pdo, $outstanding);
  $items = array_map(static function (array $row): array {
    return [
      'id' => $row['id'],
      'code' => $row['code'],
      'entry_date' => $row['entry_date'],
      'transaction_date' => $row['transaction_date'],
      'note' => $row['note'],
      'original_amount' => $row['original'],
      'remaining_amount' => $row['remaining'],
      'display_amount' => $row['remaining'] + $row['linked_amount'],
      'linked_amount' => $row['linked_amount'],
      'links' => $row['links'],
      'link_status' => $row['link_status'],
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
  $outstanding = attach_advance_links($pdo, $outstanding);

  $targetMonth = sprintf('%04d-%02d', $year, $month);

  $items = [];
  foreach ($outstanding as $row) {
    if (empty($row['entry_date'])) {
      continue;
    }
    $prefix = substr($row['entry_date'], 0, 7);
    if ($prefix !== $targetMonth) {
      continue;
    }
    $items[] = [
      'id' => $row['id'],
      'code' => $row['code'],
      'entry_date' => $row['entry_date'],
      'transaction_date' => $row['transaction_date'],
      'note' => $row['note'],
      'original_amount' => $row['original'],
      'remaining_amount' => $row['remaining'],
      'display_amount' => $row['remaining'] + $row['linked_amount'],
      'linked_amount' => $row['linked_amount'],
      'links' => $row['links'],
      'link_status' => $row['link_status'],
    ];
  }

  usort($items, static function (array $a, array $b): int {
    $codeA = $a['code'] ?? '';
    $codeB = $b['code'] ?? '';
    if ($codeA === $codeB) {
      $idA = (int) ($a['id'] ?? 0);
      $idB = (int) ($b['id'] ?? 0);
      return $idA <=> $idB;
    }
    return strcmp((string) $codeA, (string) $codeB);
  });

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

function attach_advance_links(PDO $pdo, array $outstanding): array {
  if (!$outstanding) {
    return [];
  }

  $ids = array_map(static function (array $row): int {
    return (int) ($row['id'] ?? 0);
  }, $outstanding);
  $ids = array_values(array_filter($ids));
  if (!$ids) {
    return $outstanding;
  }

  $linksByAdvance = fetch_advance_links_by_entry_ids($pdo, $ids);
  $revenueIds = [];
  foreach ($linksByAdvance as $rows) {
    foreach ($rows as $link) {
      if (($link['status'] ?? '') === ADVANCE_LINK_STATUS_ACTIVE && !empty($link['revenue_id'])) {
        $revenueIds[] = (int) $link['revenue_id'];
      }
    }
  }
  $revenueSummaries = $revenueIds ? fetch_revenue_summaries($pdo, array_unique($revenueIds)) : [];

  foreach ($outstanding as &$row) {
    $advanceId = (int) ($row['id'] ?? 0);
    $linkedAmount = 0;
    $activeLinks = [];
    if ($advanceId && isset($linksByAdvance[$advanceId])) {
      foreach ($linksByAdvance[$advanceId] as $link) {
        if (($link['status'] ?? '') !== ADVANCE_LINK_STATUS_ACTIVE) {
          continue;
        }
        $linkedAmount += (int) ($link['applied_amount'] ?? 0);
        if (!empty($link['revenue_id'])) {
          $revId = (int) $link['revenue_id'];
          if (isset($revenueSummaries[$revId])) {
            $link['revenue'] = $revenueSummaries[$revId];
          }
        }
        $activeLinks[] = $link;
      }
    }

    $row['linked_amount'] = $linkedAmount;
    $row['remaining'] = max(0, (int) ($row['remaining'] ?? 0) - $linkedAmount);
    $row['links'] = $activeLinks;
    $row['link_status'] = $linkedAmount > 0 ? 'linked' : 'unlinked';
  }
  unset($row);

  return array_values($outstanding);
}
