<?php
require_once __DIR__ . '/_utils.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = pdo();

try {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $action = md_get_action();

  if ($method === 'GET') {
    if ($action === '' || $action === 'list') {
      list_employees($pdo);
      return;
    }
    json_err('Unsupported action', 400);
  }

  if ($method !== 'POST') {
    json_err('Method not allowed', 405);
  }

  switch ($action) {
    case 'create':
      create_employee($pdo);
      return;
    case 'update':
      update_employee($pdo);
      return;
    case 'delete':
      delete_employee($pdo);
      return;
    default:
      json_err('未知的 action', 400);
  }
} catch (InvalidArgumentException $e) {
  json_err($e->getMessage(), 400);
} catch (Throwable $e) {
  json_err($e->getMessage(), 500);
}

function list_employees(PDO $pdo): void {
  $orderClause = md_code_order_clause('code');
  $stmt = $pdo->query(
    "SELECT id, code, name, phone, created_at FROM `employees` ORDER BY {$orderClause} LIMIT 200"
  );
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  json_ok([
    'endpoint' => 'master_employees',
    'count' => count($rows),
    'data' => $rows,
  ]);
}

function create_employee(PDO $pdo): void {
  $payload = md_payload();
  $data = md_map_fields($payload, ['code', 'name', 'phone']);

  md_require_fields($data, [
    'code' => '代號',
    'name' => '員工姓名',
  ]);
  md_max_length($data['code'], 50, '代號');
  md_max_length($data['name'], 100, '員工姓名');
  md_max_length($data['phone'], 50, '電話');

  md_assert_unique($pdo, 'employees', 'code', $data['code'], null, '代號已存在，請改用其他代號');

  $stmt = $pdo->prepare(
    "INSERT INTO `employees` (code, name, phone) VALUES (?, ?, ?)"
  );
  $stmt->execute([
    $data['code'],
    $data['name'],
    $data['phone'],
  ]);

  $id = (int)$pdo->lastInsertId();
  $row = md_fetch_by_id($pdo, 'employees', ['id', 'code', 'name', 'phone', 'created_at'], $id);
  if (!$row) {
    json_err('新增成功但讀取資料失敗，請重新整理');
  }

  json_ok([
    'endpoint' => 'master_employees',
    'message' => '新增成功',
    'data' => $row,
  ]);
}

function update_employee(PDO $pdo): void {
  $payload = md_payload();
  $id = md_get_id($payload);
  $existing = md_fetch_by_id($pdo, 'employees', ['id', 'code', 'name', 'phone', 'created_at'], $id);
  if (!$existing) {
    json_err('資料不存在或已被刪除', 404);
  }

  $data = md_map_fields($payload, ['code', 'name', 'phone']);

  md_require_fields($data, [
    'code' => '代號',
    'name' => '員工姓名',
  ]);
  md_max_length($data['code'], 50, '代號');
  md_max_length($data['name'], 100, '員工姓名');
  md_max_length($data['phone'], 50, '電話');

  md_assert_unique($pdo, 'employees', 'code', $data['code'], $id, '代號已存在，請改用其他代號');

  $stmt = $pdo->prepare(
    "UPDATE `employees` SET code = ?, name = ?, phone = ? WHERE id = ?"
  );
  $stmt->execute([
    $data['code'],
    $data['name'],
    $data['phone'],
    $id,
  ]);

  $row = md_fetch_by_id($pdo, 'employees', ['id', 'code', 'name', 'phone', 'created_at'], $id);
  if (!$row) {
    json_err('更新成功但讀取資料失敗，請重新整理');
  }

  json_ok([
    'endpoint' => 'master_employees',
    'message' => '更新成功',
    'data' => $row,
  ]);
}

function delete_employee(PDO $pdo): void {
  $payload = md_payload();
  $id = md_get_id($payload);
  $existing = md_fetch_by_id($pdo, 'employees', ['id', 'code', 'name', 'phone', 'created_at'], $id);
  if (!$existing) {
    json_err('資料不存在或已被刪除', 404);
  }

  $stmt = $pdo->prepare('DELETE FROM `employees` WHERE id = ?');
  $stmt->execute([$id]);

  json_ok([
    'endpoint' => 'master_employees',
    'message' => '刪除成功',
    'deleted' => $id,
    'data' => $existing,
  ]);
}
