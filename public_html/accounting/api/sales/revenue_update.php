<?php
declare(strict_types=1);

require_once __DIR__ . '/_revenue.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_err('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input') ?: 'null', true);
if (!is_array($input)) {
  $input = $_POST;
}

$id = isset($input['id']) ? (int) $input['id'] : 0;
if ($id <= 0) {
  json_err('缺少記錄編號');
}

$pdo = pdo();
ensure_sales_revenue_table($pdo);

$stmt = $pdo->prepare(
  'SELECT id, year, month, customer, customer_name, freight, invoice_amount, tax, warehouse_fee, total, actual_received, advance_total, received_date, received_method, note
   FROM `sales_revenue`
   WHERE id = ?
   LIMIT 1'
);
$stmt->execute([$id]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$existing) {
  json_err('資料不存在或已被刪除', 404);
}

$fields = [
  'customer_name' => [
    'type' => 'text',
    'max' => 150,
  ],
  'freight' => ['type' => 'amount'],
  'invoice_amount' => ['type' => 'amount'],
  'tax' => ['type' => 'amount'],
  'warehouse_fee' => ['type' => 'amount'],
  'total' => ['type' => 'amount'],
  'actual_received' => ['type' => 'amount'],
  'advance_total' => ['type' => 'amount'],
  'received_date' => [
    'type' => 'text',
    'max' => 20,
  ],
  'received_method' => [
    'type' => 'text',
    'max' => 50,
  ],
  'note' => [
    'type' => 'text',
    'max' => 255,
  ],
];

$updates = [];
$params = [];

foreach ($fields as $field => $meta) {
  if (!array_key_exists($field, $input)) {
    continue;
  }
  $value = $input[$field];
  switch ($meta['type']) {
    case 'amount':
      $parsed = sales_parse_amount($value);
      $updates[$field] = $parsed;
      $params[] = $parsed;
      break;
    case 'text':
    default:
      $text = trim((string) $value);
      $max = (int) ($meta['max'] ?? 255);
      if ($max > 0 && mb_strlen($text, 'UTF-8') > $max) {
        $text = mb_substr($text, 0, $max, 'UTF-8');
      }
      $updates[$field] = $text;
      $params[] = $text;
      break;
  }
}

if (!$updates) {
  json_ok([
    'endpoint' => 'sales/revenue_update',
    'message' => '沒有需要更新的欄位',
    'data' => $existing,
  ]);
}

$setParts = [];
foreach (array_keys($updates) as $column) {
  $setParts[] = "`{$column}` = ?";
}

$params[] = $id;
$sql = 'UPDATE `sales_revenue` SET ' . implode(', ', $setParts) . ' WHERE `id` = ?';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$stmt = $pdo->prepare(
  'SELECT id, year, month, customer, customer_name, freight, invoice_amount, tax, warehouse_fee, total, actual_received, advance_total, received_date, received_method, note, updated_at
   FROM `sales_revenue`
   WHERE id = ?
   LIMIT 1'
);
$stmt->execute([$id]);
$updated = $stmt->fetch(PDO::FETCH_ASSOC);

json_ok([
  'endpoint' => 'sales/revenue_update',
  'message' => '已更新營收資料',
  'data' => $updated ?: $existing,
]);
