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
    'SELECT id, roc_year, month, license_plate, driver, trade_date, station, amount, note
     FROM cpc_records
     WHERE roc_year = ? AND month = ?
     ORDER BY license_plate, trade_date'
  );
  $stmt->execute([$rocYear, $month]);
  $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
  json_ok(['records' => array_map('normalize_row', $records)]);
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
    'license_plate' => trim((string) ($payload['license_plate'] ?? '')),
    'driver' => trim((string) ($payload['driver'] ?? '')),
    'trade_date' => trim((string) ($payload['trade_date'] ?? '')),
    'station' => trim((string) ($payload['station'] ?? '')),
    'amount' => normalize_amount($payload['amount'] ?? 0),
    'note' => trim((string) ($payload['note'] ?? '')),
  ];
  if ($fields['license_plate'] === '') {
    json_err('請輸入車牌號碼');
  }
  $pdo = pdo();
  $stmt = $pdo->prepare(
    'UPDATE cpc_records
     SET license_plate = ?, driver = ?, trade_date = ?, station = ?, amount = ?, note = ?
     WHERE id = ?'
  );
  $stmt->execute([
    $fields['license_plate'],
    $fields['driver'],
    $fields['trade_date'],
    $fields['station'],
    $fields['amount'],
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
  $stmt = $pdo->prepare('DELETE FROM cpc_records WHERE id = ?');
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
    'license_plate' => (string) ($row['license_plate'] ?? ''),
    'driver' => (string) ($row['driver'] ?? ''),
    'trade_date' => (string) ($row['trade_date'] ?? ''),
    'station' => (string) ($row['station'] ?? ''),
    'amount' => (int) ($row['amount'] ?? 0),
    'note' => (string) ($row['note'] ?? ''),
  ];
}

function normalize_amount($value): int {
  return (int) round((float) preg_replace('/[^\d.\-]/', '', (string) $value));
}
