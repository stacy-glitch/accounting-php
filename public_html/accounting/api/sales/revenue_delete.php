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

$stmt = $pdo->prepare('SELECT id FROM `sales_revenue` WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$existing = $stmt->fetchColumn();
if (!$existing) {
  json_err('資料不存在或已被刪除', 404);
}

$deleteStmt = $pdo->prepare('DELETE FROM `sales_revenue` WHERE id = ?');
$deleteStmt->execute([$id]);

json_ok([
  'endpoint' => 'sales/revenue_delete',
  'message' => '已刪除營收資料',
  'deleted' => $id,
]);
