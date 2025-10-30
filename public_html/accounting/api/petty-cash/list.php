<?php
declare(strict_types=1);

require_once __DIR__ . '/_balances.php';
require_once __DIR__ . '/_entries.php';

$pdo = pdo();
ensure_opening_balance_table($pdo);
ensure_entries_table($pdo);

$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
validate_period($year, $month);

$opening = fetch_opening_balance($pdo, $year, $month);
$openingBalance = (float) ($opening['opening_balance'] ?? 0);

$entries = fetch_entries_by_period($pdo, $year, $month);

[$records, $remainingBalance] = apply_balances($entries, $openingBalance);

array_unshift($records, [
  'id' => 'summary-prev',
  'is_summary' => true,
  'label' => '上月累計',
  'balance' => (int) round($openingBalance),
]);

json_ok([
  'endpoint' => 'petty-cash/list',
  'data' => [
    'year' => $year,
    'month' => $month,
    'opening_balance' => (int) round($openingBalance),
    'remaining_balance' => (int) round($remainingBalance),
    'records' => $records,
  ],
]);

function apply_balances(array $entries, float $openingBalance): array {
  $running = (int) round($openingBalance);
  $records = [];
  foreach ($entries as $entry) {
    $income = isset($entry['income']) ? (int) $entry['income'] : 0;
    $expense = isset($entry['expense']) ? (int) $entry['expense'] : 0;
    $advanceIncome = isset($entry['advance_income']) ? (int) $entry['advance_income'] : 0;
    $advanceExpense = isset($entry['advance_expense']) ? (int) $entry['advance_expense'] : 0;
    if ($advanceIncome === 0 && $advanceExpense === 0 && isset($entry['advance'])) {
      $advanceExpense = (int) $entry['advance'];
    }
    $running += $income;
    $running += $advanceIncome;
    $running -= $expense;
    $running -= $advanceExpense;
    if ($running < 0) {
      $running = 0;
    }
    $entry['balance'] = $running;
    $records[] = $entry;
  }
  return [$records, $running];
}
