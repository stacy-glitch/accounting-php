<?php
require_once __DIR__ . '/_utils.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = pdo();

try {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $action = md_get_action();

  if ($method === 'GET') {
    if ($action === '' || $action === 'list') {
      list_account_mappings($pdo);
      return;
    }
    json_err('Unsupported action', 400);
  }

  if ($method !== 'POST') {
    json_err('Method not allowed', 405);
  }

  switch ($action) {
    case 'create':
      create_account_mapping($pdo);
      return;
    case 'update':
      update_account_mapping($pdo);
      return;
    case 'delete':
      delete_account_mapping($pdo);
      return;
    default:
      json_err('未知的 action', 400);
  }
} catch (InvalidArgumentException $e) {
  json_err($e->getMessage(), 400);
} catch (Throwable $e) {
  json_err($e->getMessage(), 500);
}

function list_account_mappings(PDO $pdo): void {
  $stmt = $pdo->query(
    "SELECT id, name, mapping, created_at FROM `account_mappings` ORDER BY id DESC LIMIT 200"
  );
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $seen = [];
  $filtered = [];
  foreach ($rows as $row) {
    $mapping = trim((string)($row['mapping'] ?? ''));
    if ($mapping === '') {
      continue;
    }
    if (isset($seen[$mapping])) {
      continue;
    }
    $seen[$mapping] = true;
    $filtered[] = $row;
  }

  json_ok([
    'endpoint' => 'account_mappings',
    'count' => count($filtered),
    'data' => $filtered,
  ]);
}

function create_account_mapping(PDO $pdo): void {
  $payload = md_payload();
  $data = md_map_fields($payload, ['name', 'mapping']);

  md_require_fields($data, [
    'name' => '對應表單',
    'mapping' => '會計科目',
  ]);
  md_max_length($data['name'], 150, '對應表單');
  md_max_length($data['mapping'], 100, '會計科目');

  md_assert_unique_combo(
    $pdo,
    'account_mappings',
    ['mapping', 'name'],
    ['mapping' => $data['mapping'], 'name' => $data['name']],
    null,
    '相同的對應表單與會計科目已存在'
  );

  $stmt = $pdo->prepare(
    "INSERT INTO `account_mappings` (name, mapping) VALUES (?, ?)"
  );
  $stmt->execute([
    $data['name'],
    $data['mapping'],
  ]);

  $id = (int)$pdo->lastInsertId();
  $row = md_fetch_by_id($pdo, 'account_mappings', ['id', 'name', 'mapping', 'created_at'], $id);
  if (!$row) {
    json_err('新增成功但讀取資料失敗，請重新整理');
  }

  json_ok([
    'endpoint' => 'account_mappings',
    'message' => '新增成功',
    'data' => $row,
  ]);
}

function update_account_mapping(PDO $pdo): void {
  $payload = md_payload();
  $id = md_get_id($payload);
  $existing = md_fetch_by_id($pdo, 'account_mappings', ['id', 'name', 'mapping', 'created_at'], $id);
  if (!$existing) {
    json_err('資料不存在或已被刪除', 404);
  }

  $data = md_map_fields($payload, ['name', 'mapping']);

  md_require_fields($data, [
    'name' => '對應表單',
    'mapping' => '會計科目',
  ]);
  md_max_length($data['name'], 150, '對應表單');
  md_max_length($data['mapping'], 100, '會計科目');

  md_assert_unique_combo(
    $pdo,
    'account_mappings',
    ['mapping', 'name'],
    ['mapping' => $data['mapping'], 'name' => $data['name']],
    $id,
    '相同的對應表單與會計科目已存在'
  );

  $stmt = $pdo->prepare(
    "UPDATE `account_mappings` SET name = ?, mapping = ? WHERE id = ?"
  );
  $stmt->execute([
    $data['name'],
    $data['mapping'],
    $id,
  ]);

  $row = md_fetch_by_id($pdo, 'account_mappings', ['id', 'name', 'mapping', 'created_at'], $id);
  if (!$row) {
    json_err('更新成功但讀取資料失敗，請重新整理');
  }

  json_ok([
    'endpoint' => 'account_mappings',
    'message' => '更新成功',
    'data' => $row,
  ]);
}

function delete_account_mapping(PDO $pdo): void {
  $payload = md_payload();
  $id = md_get_id($payload);
  $existing = md_fetch_by_id($pdo, 'account_mappings', ['id', 'name', 'mapping', 'created_at'], $id);
  if (!$existing) {
    json_err('資料不存在或已被刪除', 404);
  }

  $stmt = $pdo->prepare('DELETE FROM `account_mappings` WHERE id = ?');
  $stmt->execute([$id]);

  json_ok([
    'endpoint' => 'account_mappings',
    'message' => '刪除成功',
    'deleted' => $id,
    'data' => $existing,
  ]);
}
