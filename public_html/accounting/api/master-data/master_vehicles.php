<?php
require_once __DIR__ . '/_utils.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = pdo();

try {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $action = md_get_action();

  if ($method === 'GET') {
    if ($action === '' || $action === 'list') {
      list_vehicles($pdo);
      return;
    }
    json_err('Unsupported action', 400);
  }

  if ($method !== 'POST') {
    json_err('Method not allowed', 405);
  }

  switch ($action) {
    case 'create':
      create_vehicle($pdo);
      return;
    case 'update':
      update_vehicle($pdo);
      return;
    case 'delete':
      delete_vehicle($pdo);
      return;
    default:
      json_err('未知的 action', 400);
  }
} catch (InvalidArgumentException $e) {
  json_err($e->getMessage(), 400);
} catch (Throwable $e) {
  json_err($e->getMessage(), 500);
}

function list_vehicles(PDO $pdo): void {
  $orderClause = md_code_order_clause('code');
  $stmt = $pdo->query(
    "SELECT id, code, model, brand, driver, license, permit, created_at FROM `vehicles` ORDER BY {$orderClause} LIMIT 200"
  );
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  json_ok([
    'endpoint' => 'master_vehicles',
    'count' => count($rows),
    'data' => $rows,
  ]);
}

function create_vehicle(PDO $pdo): void {
  $payload = md_payload();
  $data = md_map_fields($payload, ['code', 'license', 'model', 'brand', 'driver']);

  md_require_fields($data, [
    'code' => '代號',
    'license' => '車牌號碼',
  ]);
  md_max_length($data['code'], 50, '代號');
  md_max_length($data['license'], 100, '車牌號碼');
  md_max_length($data['model'], 100, '車型');
  md_max_length($data['brand'], 100, '廠牌');
  md_max_length($data['driver'], 100, '司機');

  md_assert_unique($pdo, 'vehicles', 'code', $data['code'], null, '代號已存在，請改用其他代號');

  $stmt = $pdo->prepare(
    "INSERT INTO `vehicles` (code, license, model, brand, driver, plate) VALUES (?, ?, ?, ?, ?, ?)"
  );
  $stmt->execute([
    $data['code'],
    $data['license'],
    $data['model'],
    $data['brand'],
    $data['driver'],
    $data['license'],
  ]);

  $id = (int)$pdo->lastInsertId();
  $row = md_fetch_by_id($pdo, 'vehicles', ['id', 'code', 'model', 'brand', 'driver', 'license', 'permit', 'created_at'], $id);
  if (!$row) {
    json_err('新增成功但讀取資料失敗，請重新整理');
  }

  json_ok([
    'endpoint' => 'master_vehicles',
    'message' => '新增成功',
    'data' => $row,
  ]);
}

function update_vehicle(PDO $pdo): void {
  $payload = md_payload();
  $id = md_get_id($payload);
  $existing = md_fetch_by_id($pdo, 'vehicles', ['id', 'code', 'model', 'brand', 'driver', 'license', 'permit', 'created_at'], $id);
  if (!$existing) {
    json_err('資料不存在或已被刪除', 404);
  }

  $data = md_map_fields($payload, ['code', 'license', 'model', 'brand', 'driver']);

  md_require_fields($data, [
    'code' => '代號',
    'license' => '車牌號碼',
  ]);
  md_max_length($data['code'], 50, '代號');
  md_max_length($data['license'], 100, '車牌號碼');
  md_max_length($data['model'], 100, '車型');
  md_max_length($data['brand'], 100, '廠牌');
  md_max_length($data['driver'], 100, '司機');

  md_assert_unique($pdo, 'vehicles', 'code', $data['code'], $id, '代號已存在，請改用其他代號');

  $stmt = $pdo->prepare(
    "UPDATE `vehicles` SET code = ?, license = ?, model = ?, brand = ?, driver = ?, plate = ? WHERE id = ?"
  );
  $stmt->execute([
    $data['code'],
    $data['license'],
    $data['model'],
    $data['brand'],
    $data['driver'],
    $data['license'],
    $id,
  ]);

  $row = md_fetch_by_id($pdo, 'vehicles', ['id', 'code', 'model', 'brand', 'driver', 'license', 'permit', 'created_at'], $id);
  if (!$row) {
    json_err('更新成功但讀取資料失敗，請重新整理');
  }

  json_ok([
    'endpoint' => 'master_vehicles',
    'message' => '更新成功',
    'data' => $row,
  ]);
}

function delete_vehicle(PDO $pdo): void {
  $payload = md_payload();
  $id = md_get_id($payload);
  $existing = md_fetch_by_id($pdo, 'vehicles', ['id', 'code', 'model', 'brand', 'driver', 'license', 'permit', 'created_at'], $id);
  if (!$existing) {
    json_err('資料不存在或已被刪除', 404);
  }

  $stmt = $pdo->prepare('DELETE FROM `vehicles` WHERE id = ?');
  $stmt->execute([$id]);

  json_ok([
    'endpoint' => 'master_vehicles',
    'message' => '刪除成功',
    'deleted' => $id,
    'data' => $existing,
  ]);
}
