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
    'SELECT id, roc_year, month, employee_code, employee_name, birth, labor_salary, health_salary, change_type, change_date, personal_share, company_share, note
     FROM labor_roster_records
     WHERE roc_year = ? AND month = ?
     ORDER BY employee_name'
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
    'employee_name' => trim((string) ($payload['employee_name'] ?? '')),
    'birth' => trim((string) ($payload['birth'] ?? '')),
    'labor_salary' => normalize_amount($payload['labor_salary'] ?? 0),
    'health_salary' => normalize_amount($payload['health_salary'] ?? 0),
    'change_type' => trim((string) ($payload['change_type'] ?? '')),
    'change_date' => trim((string) ($payload['change_date'] ?? '')),
    'personal_share' => normalize_amount($payload['personal_share'] ?? 0),
    'company_share' => normalize_amount($payload['company_share'] ?? 0),
    'note' => trim((string) ($payload['note'] ?? '')),
  ];
  if ($fields['employee_name'] === '') {
    json_err('姓名不可為空');
  }
  $pdo = pdo();
  $stmt = $pdo->prepare(
    'UPDATE labor_roster_records
     SET employee_name = ?, birth = ?, labor_salary = ?, health_salary = ?, change_type = ?, change_date = ?, personal_share = ?, company_share = ?, note = ?
     WHERE id = ?'
  );
  $stmt->execute([
    $fields['employee_name'],
    $fields['birth'],
    $fields['labor_salary'],
    $fields['health_salary'],
    $fields['change_type'],
    $fields['change_date'],
    $fields['personal_share'],
    $fields['company_share'],
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
  $stmt = $pdo->prepare('DELETE FROM labor_roster_records WHERE id = ?');
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
    'employee_code' => (string) ($row['employee_code'] ?? ''),
    'employee_name' => (string) ($row['employee_name'] ?? ''),
    'birth' => (string) ($row['birth'] ?? ''),
    'labor_salary' => (int) ($row['labor_salary'] ?? 0),
    'health_salary' => (int) ($row['health_salary'] ?? 0),
    'change_type' => (string) ($row['change_type'] ?? ''),
    'change_date' => (string) ($row['change_date'] ?? ''),
    'personal_share' => (int) ($row['personal_share'] ?? 0),
    'company_share' => (int) ($row['company_share'] ?? 0),
    'note' => (string) ($row['note'] ?? ''),
  ];
}

function normalize_amount($value): int {
  $num = (int) round((float) preg_replace('/[^\d.\-]/', '', (string) $value));
  return $num;
}
