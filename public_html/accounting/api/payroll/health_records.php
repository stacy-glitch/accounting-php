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
    'SELECT id, roc_year, month, insurance_fee, dependent_name, id_number, birth, identity_type, change_type, billing_note, self_payment, company_payment, self_total, note
     FROM health_roster_records
     WHERE roc_year = ? AND month = ?
     ORDER BY dependent_name'
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
    'dependent_name' => trim((string) ($payload['dependent_name'] ?? '')),
    'id_number' => trim((string) ($payload['id_number'] ?? '')),
    'birth' => trim((string) ($payload['birth'] ?? '')),
    'identity_type' => trim((string) ($payload['identity_type'] ?? '')),
    'change_type' => trim((string) ($payload['change_type'] ?? '')),
    'billing_note' => trim((string) ($payload['billing_note'] ?? '')),
    'insurance_fee' => normalize_amount($payload['insurance_fee'] ?? 0),
    'self_payment' => normalize_amount($payload['self_payment'] ?? 0),
    'company_payment' => normalize_amount($payload['company_payment'] ?? 0),
    'self_total' => normalize_amount($payload['self_total'] ?? 0),
    'note' => trim((string) ($payload['note'] ?? '')),
  ];
  if ($fields['dependent_name'] === '') {
    json_err('請輸入眷屬姓名');
  }
  $pdo = pdo();
  $stmt = $pdo->prepare(
    'UPDATE health_roster_records
     SET insurance_fee = ?, dependent_name = ?, id_number = ?, birth = ?, identity_type = ?, change_type = ?, billing_note = ?, self_payment = ?, company_payment = ?, self_total = ?, note = ?
     WHERE id = ?'
  );
  $stmt->execute([
    $fields['insurance_fee'],
    $fields['dependent_name'],
    $fields['id_number'],
    $fields['birth'],
    $fields['identity_type'],
    $fields['change_type'],
    $fields['billing_note'],
    $fields['self_payment'],
    $fields['company_payment'],
    $fields['self_total'],
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
  $stmt = $pdo->prepare('DELETE FROM health_roster_records WHERE id = ?');
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
    'insurance_fee' => (int) ($row['insurance_fee'] ?? 0),
    'dependent_name' => (string) ($row['dependent_name'] ?? ''),
    'id_number' => (string) ($row['id_number'] ?? ''),
    'birth' => (string) ($row['birth'] ?? ''),
    'identity_type' => (string) ($row['identity_type'] ?? ''),
    'change_type' => (string) ($row['change_type'] ?? ''),
    'billing_note' => (string) ($row['billing_note'] ?? ''),
    'self_payment' => (int) ($row['self_payment'] ?? 0),
    'company_payment' => (int) ($row['company_payment'] ?? 0),
    'self_total' => (int) ($row['self_total'] ?? 0),
    'note' => (string) ($row['note'] ?? ''),
  ];
}

function normalize_amount($value): int {
  return (int) round((float) preg_replace('/[^\d.\-]/', '', (string) $value));
}
