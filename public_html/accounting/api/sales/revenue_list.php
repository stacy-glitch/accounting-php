<?php
declare(strict_types=1);

require_once __DIR__ . '/_revenue.php';
require_once __DIR__ . '/../petty-cash/_advance_links.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
  json_err('Method not allowed', 405);
}

$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);
if (!is_int($year) || $year < 2000 || $year > 2100) {
  json_err('請提供正確的年份');
}
if (!is_int($month) || $month < 1 || $month > 12) {
  json_err('請提供正確的月份');
}

$pdo = pdo();
ensure_sales_revenue_table($pdo);

$stmt = $pdo->prepare(
  'SELECT id, year, month, customer, customer_name, freight, invoice_amount, tax, warehouse_fee, total, actual_received, advance_total, received_date, received_method, note, created_at, updated_at
   FROM `sales_revenue`
   WHERE `year` = ? AND `month` = ?
   ORDER BY customer ASC, id ASC'
);
$stmt->execute([$year, $month]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
$advanceDetails = [];

if ($rows) {
  $ids = array_map(static function ($row) {
    return (int) ($row['id'] ?? 0);
  }, $rows);
  $ids = array_values(array_filter($ids));

  if ($ids) {
    ensure_petty_advance_links_table($pdo);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $detailStmt = $pdo->prepare(
      "SELECT link.revenue_id,
              link.advance_entry_id,
              link.applied_amount,
              entry.entry_date,
              entry.transaction_date,
              entry.note
       FROM petty_cash_advance_links link
       INNER JOIN petty_cash_entries entry ON entry.id = link.advance_entry_id
       WHERE link.revenue_id IN ($placeholders) AND link.status = ?"
    );
    $params = array_merge($ids, [ADVANCE_LINK_STATUS_ACTIVE]);
    $detailStmt->execute($params);
    foreach ($detailStmt->fetchAll(PDO::FETCH_ASSOC) as $detailRow) {
      $revId = (int) ($detailRow['revenue_id'] ?? 0);
      if (!$revId) {
        continue;
      }
      if (!isset($advanceDetails[$revId])) {
        $advanceDetails[$revId] = [];
      }
      $advanceDetails[$revId][] = [
        'advance_entry_id' => (int) ($detailRow['advance_entry_id'] ?? 0),
        'amount' => (int) ($detailRow['applied_amount'] ?? 0),
        'entry_date' => $detailRow['entry_date'] ?? '',
        'transaction_date' => $detailRow['transaction_date'] ?? '',
        'note' => $detailRow['note'] ?? '',
      ];
    }
  }
}
$totals = [
  'freight' => 0,
  'invoice_amount' => 0,
  'tax' => 0,
  'warehouse_fee' => 0,
  'total' => 0,
  'actual_received' => 0,
  'advance_total' => 0,
];

foreach ($rows as $row) {
  $actualReceived =
    isset($row['actual_received']) && $row['actual_received'] !== null ? (int) $row['actual_received'] : null;

  $entry = [
    'id' => (int) ($row['id'] ?? 0),
    'customer' => (string) ($row['customer'] ?? ''),
    'customer_name' => (string) ($row['customer_name'] ?? ''),
    'freight' => (int) ($row['freight'] ?? 0),
    'invoice_amount' => (int) ($row['invoice_amount'] ?? 0),
    'tax' => (int) ($row['tax'] ?? 0),
    'warehouse_fee' => (int) ($row['warehouse_fee'] ?? 0),
    'total' => (int) ($row['total'] ?? 0),
    'actual_received' => $actualReceived,
    'advance_total' => (int) ($row['advance_total'] ?? 0),
    'received_date' => (string) ($row['received_date'] ?? ''),
    'received_method' => (string) ($row['received_method'] ?? ''),
    'note' => (string) ($row['note'] ?? ''),
    'created_at' => (string) ($row['created_at'] ?? ''),
    'updated_at' => (string) ($row['updated_at'] ?? ''),
  ];

  $totals['freight'] += $entry['freight'];
  $totals['invoice_amount'] += $entry['invoice_amount'];
  $totals['tax'] += $entry['tax'];
  $totals['warehouse_fee'] += $entry['warehouse_fee'];
  $totals['total'] += $entry['total'];
  if ($entry['actual_received'] !== null) {
    $totals['actual_received'] += $entry['actual_received'];
  }
  $totals['advance_total'] += $entry['advance_total'];

  $entry['advance_details'] = $advanceDetails[$entry['id']] ?? [];

  $data[] = $entry;
}

json_ok([
  'endpoint' => 'sales/revenue_list',
  'year' => $year,
  'month' => $month,
  'count' => count($data),
  'totals' => $totals,
  'data' => $data,
]);
