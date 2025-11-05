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

$year = isset($input['year']) ? (int) $input['year'] : 0;
$month = isset($input['month']) ? (int) $input['month'] : 0;
if ($year < 2000 || $year > 2100) {
  json_err('請提供正確的年份');
}
if ($month < 1 || $month > 12) {
  json_err('請提供正確的月份');
}

$customer = sales_normalize_text($input['customer'] ?? '', 50);
if ($customer === '') {
  json_err('請選擇客戶代號');
}

$pdo = pdo();
ensure_sales_revenue_table($pdo);

$stmt = $pdo->prepare('SELECT `name` FROM `customers` WHERE `code` = ? LIMIT 1');
$stmt->execute([$customer]);
$customerRow = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$customerRow) {
  json_err('找不到指定的客戶代號，請先至資料維護新增。');
}

$customerNameInput = sales_normalize_text($input['customer_name'] ?? '', 150);
$customerName = $customerNameInput !== '' ? $customerNameInput : sales_normalize_text($customerRow['name'] ?? '', 150);

$freight = sales_parse_amount($input['freight'] ?? 0);
$invoiceAmount = sales_parse_amount($input['invoice_amount'] ?? 0);
$tax = sales_parse_amount($input['tax'] ?? 0);
$warehouseFee = sales_parse_amount($input['warehouse_fee'] ?? 0);
$total = sales_parse_amount($input['total'] ?? 0);
$actualReceived = sales_parse_amount($input['actual_received'] ?? 0);
$advanceTotal = sales_parse_amount($input['advance_total'] ?? 0);
$receivedDate = sales_normalize_received_date($input['received_date'] ?? '', $year, $month);
$receivedMethod = sales_normalize_text($input['received_method'] ?? '', 50);
$note = sales_normalize_text($input['note'] ?? '', 255);

try {
  $pdo->beginTransaction();
  $insertStmt = $pdo->prepare(
    'INSERT INTO `sales_revenue`
      (`year`, `month`, `customer`, `customer_name`, `freight`, `invoice_amount`, `tax`, `warehouse_fee`, `total`, `actual_received`, `advance_total`, `received_date`, `received_method`, `note`)
     VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );
  $insertStmt->execute([
    $year,
    $month,
    $customer,
    $customerName,
    $freight,
    $invoiceAmount,
    $tax,
    $warehouseFee,
    $total,
    $actualReceived,
    $advanceTotal,
    $receivedDate,
    $receivedMethod,
    $note,
  ]);
  $id = (int) $pdo->lastInsertId();
  $pdo->commit();
} catch (PDOException $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  if ((int) $e->getCode() === 23000) {
    json_err('該月份已存在相同客戶，請改用編輯或刪除後再新增。');
  }
  json_err('新增失敗：' . $e->getMessage(), 500);
}

$selectStmt = $pdo->prepare(
  'SELECT id, year, month, customer, customer_name, freight, invoice_amount, tax, warehouse_fee, total, actual_received, advance_total, received_date, received_method, note, created_at, updated_at
   FROM `sales_revenue`
   WHERE id = ?
   LIMIT 1'
);
$selectStmt->execute([$id]);
$created = $selectStmt->fetch(PDO::FETCH_ASSOC);

json_ok([
  'endpoint' => 'sales/revenue_create',
  'message' => '新增營收資料完成',
  'data' => $created ?: null,
]);
