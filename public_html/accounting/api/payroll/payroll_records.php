<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';

const PAYROLL_ROWS_LIMIT = 11;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
switch (strtoupper($method)) {
  case 'GET':
    handle_get();
    break;
  case 'POST':
    handle_post();
    break;
  case 'DELETE':
    handle_delete();
    break;
  default:
    json_err('Method not allowed', 405);
}

function handle_get(): void {
  $rocYear = filter_input(INPUT_GET, 'roc_year', FILTER_VALIDATE_INT);
  $month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);

  if (!is_int($rocYear) || $rocYear <= 0) {
    $rocYear = (int) (date('Y') - 1911);
  }
  if (!is_int($month) || $month < 1 || $month > 12) {
    $month = (int) date('n');
  }

  $pdo = pdo();
  $stmt = $pdo->prepare(
    'SELECT id, roc_year, month, employee_code, employee_name, expenses_json, incomes_json, note, expense_total, income_total, net_total, created_at, updated_at
     FROM payroll_records
     WHERE roc_year = ? AND month = ?
     ORDER BY employee_name, employee_code'
  );
  $stmt->execute([$rocYear, $month]);
  $records = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $records[] = present_record($row);
  }
  json_ok([
    'records' => $records,
  ]);
}

function handle_post(): void {
  $payload = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($payload)) {
    json_err('Invalid payload');
  }

  $rocYear = (int) ($payload['roc_year'] ?? 0);
  $month = (int) ($payload['month'] ?? 0);
  $employeeCode = trim((string) ($payload['employee_id'] ?? ''));
  $employeeName = trim((string) ($payload['employee_name'] ?? ''));
  $note = trim((string) ($payload['note'] ?? ''));
  $expenses = normalize_entries($payload['expenses'] ?? []);
  $incomes = normalize_entries($payload['incomes'] ?? []);

  if ($rocYear <= 0 || $rocYear > 300) {
    json_err('請輸入有效的民國年');
  }
  if ($month < 1 || $month > 12) {
    json_err('請輸入有效的月份');
  }
  if ($employeeCode === '') {
    json_err('缺少員工代號');
  }

  $expenseTotal = array_reduce(
    $expenses,
    static fn ($carry, $item) => $carry + normalize_amount($item['amount']),
    0
  );
  $incomeTotal = array_reduce(
    $incomes,
    static fn ($carry, $item) => $carry + normalize_amount($item['amount']),
    0
  );
  $netTotal = $incomeTotal - $expenseTotal;

  $pdo = pdo();
  $stmt = $pdo->prepare(
    'INSERT INTO payroll_records
      (roc_year, month, employee_code, employee_name, expenses_json, incomes_json, note, expense_total, income_total, net_total, created_at, updated_at)
     VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
     ON DUPLICATE KEY UPDATE
      employee_name = VALUES(employee_name),
      expenses_json = VALUES(expenses_json),
      incomes_json = VALUES(incomes_json),
      note = VALUES(note),
      expense_total = VALUES(expense_total),
      income_total = VALUES(income_total),
      net_total = VALUES(net_total),
      updated_at = NOW()'
  );
  $stmt->execute([
    $rocYear,
    $month,
    $employeeCode,
    $employeeName,
    json_encode($expenses, JSON_UNESCAPED_UNICODE),
    json_encode($incomes, JSON_UNESCAPED_UNICODE),
    $note,
    $expenseTotal,
    $incomeTotal,
    $netTotal,
  ]);

  $record = fetch_record($pdo, $rocYear, $month, $employeeCode);
  if (!$record) {
    json_err('儲存成功但讀取資料失敗，請重新整理');
  }
  json_ok([
    'message' => '薪資紀錄已儲存',
    'record' => present_record($record),
  ]);
}

function handle_delete(): void {
  $payload = json_decode((string) file_get_contents('php://input'), true);
  $id = isset($payload['id']) ? (int) $payload['id'] : (int) ($_GET['id'] ?? 0);
  if ($id <= 0) {
    json_err('缺少刪除目標');
  }
  $pdo = pdo();
  $stmt = $pdo->prepare('DELETE FROM payroll_records WHERE id = ?');
  $stmt->execute([$id]);
  if ($stmt->rowCount() === 0) {
    json_err('資料不存在或已刪除', 404);
  }
  json_ok(['message' => '已刪除']);
}

function normalize_entries($list): array {
  $entries = [];
  if (!is_array($list)) {
    $list = [];
  }
  for ($i = 0; $i < PAYROLL_ROWS_LIMIT; $i += 1) {
    $item = $list[$i] ?? [];
    $label = trim((string) ($item['label'] ?? ''));
    $rawAmount = $item['amount'] ?? '';
    $amountString = trim($rawAmount === null ? '' : (string) $rawAmount);
    $entries[] = [
      'label' => $label,
      'amount' => $amountString,
    ];
  }
  return $entries;
}

function normalize_amount($value): int {
  if ($value === '' || $value === null) {
    return 0;
  }
  $filtered = preg_replace('/[^\d.\-]/', '', (string) $value);
  return (int) round((float) ($filtered === '' ? 0 : $filtered));
}

function fetch_record(PDO $pdo, int $rocYear, int $month, string $employeeCode): ?array {
  $stmt = $pdo->prepare(
    'SELECT id, roc_year, month, employee_code, employee_name, expenses_json, incomes_json, note, expense_total, income_total, net_total, created_at, updated_at
     FROM payroll_records
     WHERE roc_year = ? AND month = ? AND employee_code = ?
     LIMIT 1'
  );
  $stmt->execute([$rocYear, $month, $employeeCode]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

function present_record(array $row): array {
  $expenses = decode_entries($row['expenses_json'] ?? '[]');
  $incomes = decode_entries($row['incomes_json'] ?? '[]');
  return [
    'id' => (int) ($row['id'] ?? 0),
    'year' => (int) ($row['roc_year'] ?? 0),
    'month' => (int) ($row['month'] ?? 0),
    'employeeId' => (string) ($row['employee_code'] ?? ''),
    'employeeName' => (string) ($row['employee_name'] ?? ''),
    'expenses' => $expenses,
    'incomes' => $incomes,
    'note' => (string) ($row['note'] ?? ''),
    'expenseTotal' => (int) ($row['expense_total'] ?? 0),
    'incomeTotal' => (int) ($row['income_total'] ?? 0),
    'net' => (int) ($row['net_total'] ?? 0),
    'savedAt' => (string) ($row['updated_at'] ?? $row['created_at'] ?? ''),
  ];
}

function decode_entries($json): array {
  $data = json_decode((string) $json, true);
  if (!is_array($data)) {
    $data = [];
  }
  $entries = [];
  for ($i = 0; $i < PAYROLL_ROWS_LIMIT; $i += 1) {
    $item = $data[$i] ?? [];
    $label = trim((string) ($item['label'] ?? ''));
    $rawAmount = $item['amount'] ?? '';
    $amountString = trim($rawAmount === null ? '' : (string) $rawAmount);
    $entries[] = [
      'label' => $label,
      'amount' => $amountString,
    ];
  }
  return $entries;
}
