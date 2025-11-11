<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
switch ($method) {
  case 'GET':
    handle_get();
    break;
  case 'POST':
    handle_update();
    break;
  case 'DELETE':
    handle_delete();
    break;
  default:
    json_err('Method not allowed', 405);
}

function handle_get(): void {
  $rocYear = filter_input(INPUT_GET, 'roc_year', FILTER_VALIDATE_INT) ?: (int) (date('Y') - 1911);
  $month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT) ?: (int) date('n');
  $month = max(1, min(12, $month));

  $pdo = pdo();
  $stmt = $pdo->prepare(
    'SELECT id, roc_year, month, driver_code, driver_name, freight, note
     FROM driver_summary_records
     WHERE roc_year = ? AND month = ?
     ORDER BY id'
  );
  $stmt->execute([$rocYear, $month]);
  $records = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $records[] = normalize_row($row);
  }
  json_ok(['records' => $records]);
}

function handle_update(): void {
  $payload = json_decode(file_get_contents('php://input'), true);
  if (!is_array($payload)) {
    json_err('Invalid payload');
  }
  $id = isset($payload['id']) ? (int) $payload['id'] : 0;
  if ($id <= 0) {
    json_err('缺少資料列編號');
  }
  $fields = [
    'driver_code' => trim((string) ($payload['driver_code'] ?? '')),
    'driver_name' => trim((string) ($payload['driver_name'] ?? '')),
    'freight' => normalize_amount($payload['freight'] ?? 0),
    'note' => trim((string) ($payload['note'] ?? '')),
  ];
  if ($fields['driver_name'] === '' && $fields['driver_code'] !== '') {
    $lookup = lookup_driver_name($pdo, $fields['driver_code']);
    if ($lookup !== null) {
      $fields['driver_name'] = $lookup;
    }
  }
  if ($fields['driver_code'] === '' && $fields['driver_name'] !== '') {
    $codeLookup = lookup_driver_code($pdo, $fields['driver_name']);
    if ($codeLookup !== null) {
      $fields['driver_code'] = $codeLookup;
    }
  }
  if ($fields['driver_name'] === '') {
    json_err('請輸入司機名稱');
  }
  $pdo = pdo();
  $stmt = $pdo->prepare(
    'UPDATE driver_summary_records
     SET driver_code = ?, driver_name = ?, freight = ?, note = ?
     WHERE id = ?'
  );
  $stmt->execute([
    $fields['driver_code'],
    $fields['driver_name'],
    $fields['freight'],
    $fields['note'],
    $id,
  ]);
  if ($stmt->rowCount() === 0) {
    json_err('資料不存在或未變更', 404);
  }
  json_ok(['message' => 'updated']);
}

function handle_delete(): void {
  $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
  if ($id <= 0) {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (isset($payload['id'])) {
      $id = (int) $payload['id'];
    }
  }
  if ($id <= 0) {
    json_err('缺少資料列編號');
  }
  $pdo = pdo();
  $stmt = $pdo->prepare('DELETE FROM driver_summary_records WHERE id = ?');
  $stmt->execute([$id]);
  if ($stmt->rowCount() === 0) {
    json_err('資料不存在或已刪除', 404);
  }
  json_ok(['message' => 'deleted']);
}

function normalize_row(array $row): array {
  return [
    'id' => (int) ($row['id'] ?? 0),
    'roc_year' => (int) ($row['roc_year'] ?? 0),
    'month' => (int) ($row['month'] ?? 0),
    'driver_code' => (string) ($row['driver_code'] ?? ''),
    'driver_name' => (string) ($row['driver_name'] ?? ''),
    'freight' => (int) ($row['freight'] ?? 0),
    'note' => (string) ($row['note'] ?? ''),
  ];
}

function normalize_amount($value): int {
  return (int) round((float) preg_replace('/[^\d.\-]/', '', (string) $value));
}

function lookup_driver_name(PDO $pdo, string $code): ?string {
  $stmt = $pdo->prepare('SELECT name FROM employees WHERE code = ? LIMIT 1');
  $stmt->execute([$code]);
  $name = $stmt->fetchColumn();
  if ($name === false || trim((string) $name) === '') {
    return null;
  }
  return (string) $name;
}

function lookup_driver_code(PDO $pdo, string $name): ?string {
  $stmt = $pdo->prepare('SELECT code FROM employees WHERE name = ? LIMIT 1');
  $stmt->execute([$name]);
  $code = $stmt->fetchColumn();
  if ($code === false || trim((string) $code) === '') {
    return null;
  }
  return (string) $code;
}
