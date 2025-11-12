<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
  json_err('Method not allowed', 405);
}

$rocYear = filter_input(INPUT_GET, 'roc_year', FILTER_VALIDATE_INT);
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);

if (!is_int($rocYear) || $rocYear <= 0 || $rocYear > 300) {
  $rocYear = (int) (date('Y') - 1911);
}
if (!is_int($month) || $month < 1 || $month > 12) {
  $month = (int) date('n');
}

$gregorianYear = $rocYear + 1911;
[$prevRocYear, $prevMonth] = compute_previous_period($rocYear, $month);
$prevYearGregorian = $prevRocYear + 1911;

$pdo = pdo();
[$employees, $nameDirectory, $duplicateNames] = load_employees($pdo);
if (empty($employees)) {
  json_ok([
    'period' => [
      'roc_year' => $rocYear,
      'month' => $month,
      'prev_roc_year' => $prevRocYear,
      'prev_month' => $prevMonth,
    ],
    'fields' => payroll_fields(),
    'employees' => [],
    'warnings' => ['員工名單為空，請先在資料維護中匯入員工資料'],
  ]);
}

$warnings = [];
foreach ($duplicateNames as $nameKey => $codes) {
  $labels = array_map(static function ($code) use ($employees) {
    $name = $employees[$code]['name'] ?? '';
    return $name ? "{$name} (代號 {$code})" : $code;
  }, $codes);
  $warnings[] = '員工姓名重複：' . implode('、', $labels);
}

const INCOME_ALLOWANCES = [
  [
    'field' => 'telephone_subsidy',
    'amount' => 2000,
    'names' => ['陳姵如'],
  ],
  [
    'field' => 'telephone_subsidy',
    'amount' => 1000,
    'names' => ['連瑋晟', '連偉晟', '金志堅', '陳柯宏', '余仁浩', '江順介', '簡晨芸'],
  ],
  [
    'field' => 'retirement_subsidy',
    'amount' => 2000,
    'names' => ['余仁浩'],
  ],
];

const EXPENSE_ALLOWANCES = [
  [
    'field' => 'retirement_deposit',
    'amount' => 1000,
    'names' => ['黃森銘', '吳生財', '林春祥', '石偉輯', '周俊杰', '郭庭豪', '邱信宏'],
  ],
  [
    'field' => 'group_insurance',
    'amount' => 503,
    'names' => ['李進春', '林春祥', '黃志偉', '石偉輯', '陳秉宏', '郭庭豪', '邱信宏'],
  ],
  [
    'field' => 'group_insurance',
    'amount' => 1061,
    'names' => ['黃森銘', '蘇侯順', '周俊杰'],
  ],
  [
    'field' => 'transfer_fee',
    'amount' => 15,
    'names' => ['陳柯宏'],
  ],
];

$incomeTotals = [];
$expenseTotals = [];
$freightTotals = [];

// 運費
$freightData = fetch_driver_freight($pdo, $rocYear, $month, $employees, $nameDirectory, $warnings);
foreach ($freightData as $code => $amount) {
  $freightTotals[$code] = $amount;
  add_amount($incomeTotals, $code, 'freight', $amount);
  add_amount($expenseTotals, $code, 'freight_ten_percent', (int) round($amount * 0.1));
  add_amount($expenseTotals, $code, 'office_fee', min(6000, (int) round($amount * 0.05)));
  add_amount($expenseTotals, $code, 'invoice_fee', (int) round($amount * 0.08));
}

// 中油卡
$cpcData = fetch_cpc_amounts($pdo, $rocYear, $month, $employees, $nameDirectory, $warnings);
foreach ($cpcData as $code => $amount) {
  add_amount($expenseTotals, $code, 'cpc_card', $amount);
  if ($amount > 0) {
    add_amount($incomeTotals, $code, 'cpc_refund', (int) round($amount * 0.05));
  }
}

// 勞保 (前一月)
$laborData = fetch_labor_personal_share($pdo, $prevRocYear, $prevMonth, $employees, $nameDirectory, $warnings);
foreach ($laborData as $code => $amount) {
  add_amount($expenseTotals, $code, 'labor_insurance', $amount);
}

// 健保 (前一月)
$healthData = fetch_health_self_total($pdo, $prevRocYear, $prevMonth, $employees, $nameDirectory, $warnings);
foreach ($healthData as $code => $amount) {
  add_amount($expenseTotals, $code, 'health_insurance', $amount);
}

// 零用金各項
$pettySubjectMap = [
  '借支現金' => 'cash_advance',
  '燃料稅' => 'fuel_tax',
  '牌照稅' => 'license_tax',
];
$pettyData = fetch_petty_subject_totals($pdo, $gregorianYear, $month, $pettySubjectMap);
foreach ($pettyData as $fieldId => $rows) {
  foreach ($rows as $code => $amount) {
    add_amount($expenseTotals, $code, $fieldId, $amount);
  }
}

// 借支（基隆二信轉帳）
$klsbData = fetch_klsb_expenses($gregorianYear, $month, $employees, $nameDirectory, $warnings);
foreach ($klsbData as $code => $amount) {
  add_amount($expenseTotals, $code, 'klsb_transfer', $amount);
}

// 燃料稅/牌照稅/借支現金 已在零用金計入

// 靠行費（僅對有運費的司機）
foreach ($freightTotals as $code => $amount) {
  if ($amount > 0) {
    add_amount($expenseTotals, $code, 'affiliate_fee', 1000);
  }
}

// 固定津貼／補助
apply_named_allowances(INCOME_ALLOWANCES, $incomeTotals, $nameDirectory, $warnings);
apply_named_allowances(EXPENSE_ALLOWANCES, $expenseTotals, $nameDirectory, $warnings);

// 退休補助（收入：余仁浩 2000 已在 INCOME_ALLOWANCES）

// 團體保險與其他已處理

$fields = payroll_fields();
$employeesPayload = [];
foreach ($employees as $code => $info) {
  $employeesPayload[$code] = [
    'code' => $code,
    'name' => $info['name'],
    'income' => build_field_map($fields['income'], $incomeTotals[$code] ?? []),
    'expense' => build_field_map($fields['expense'], $expenseTotals[$code] ?? []),
  ];
}

json_ok([
  'period' => [
    'roc_year' => $rocYear,
    'month' => $month,
    'prev_roc_year' => $prevRocYear,
    'prev_month' => $prevMonth,
  ],
  'fields' => $fields,
  'employees' => $employeesPayload,
  'warnings' => array_values(array_unique(array_filter($warnings))),
]);

function payroll_fields(): array {
  return [
    'income' => [
      ['id' => 'freight', 'label' => '運費'],
      ['id' => 'cpc_refund', 'label' => '中油退稅'],
      ['id' => 'telephone_subsidy', 'label' => '電話補助'],
      ['id' => 'retirement_subsidy', 'label' => '退休補助'],
    ],
    'expense' => [
      ['id' => 'labor_insurance', 'label' => '勞保'],
      ['id' => 'health_insurance', 'label' => '健保'],
      ['id' => 'cash_advance', 'label' => '借支現金'],
      ['id' => 'klsb_transfer', 'label' => '借支二信轉帳'],
      ['id' => 'cpc_card', 'label' => '中油卡'],
      ['id' => 'fuel_tax', 'label' => '燃料稅'],
      ['id' => 'license_tax', 'label' => '牌照稅'],
      ['id' => 'freight_ten_percent', 'label' => '一成'],
      ['id' => 'office_fee', 'label' => '辦公費'],
      ['id' => 'invoice_fee', 'label' => '發票費'],
      ['id' => 'retirement_deposit', 'label' => '退休存款'],
      ['id' => 'affiliate_fee', 'label' => '靠行費'],
      ['id' => 'group_insurance', 'label' => '團體保險'],
      ['id' => 'transfer_fee', 'label' => '跨轉費'],
    ],
  ];
}

function load_employees(PDO $pdo): array {
  $stmt = $pdo->query('SELECT code, name FROM employees ORDER BY code');
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) {
    $rows = load_config_employees();
  }
  if (!$rows) {
    return [[], [], []];
  }
  $employees = [];
  $nameDirectory = [];
  $duplicates = [];
  foreach ($rows as $row) {
    $code = normalize_employee_code((string) ($row['code'] ?? ''));
    $name = trim((string) ($row['name'] ?? ''));
    if ($code === '' || $name === '') {
      continue;
    }
    $employees[$code] = [
      'code' => $code,
      'name' => $name,
    ];
    $key = normalize_person_name($name);
    if ($key === '') {
      continue;
    }
    $nameDirectory[$key] ??= [];
    if (!in_array($code, $nameDirectory[$key], true)) {
      $nameDirectory[$key][] = $code;
    }
    if (count($nameDirectory[$key]) > 1) {
      $duplicates[$key] = $nameDirectory[$key];
    }
  }
  return [$employees, $nameDirectory, $duplicates];
}

function normalize_employee_code(string $code): string {
  $value = trim($code);
  if ($value === '') {
    return '';
  }
  $value = str_replace([',', '，', ' '], '', $value);
  if (preg_match('/^\d+$/', $value)) {
    if (strlen($value) < 3) {
      $value = str_pad($value, 3, '0', STR_PAD_LEFT);
    }
    return $value;
  }
  return strtoupper($value);
}

function normalize_person_name(string $name): string {
  $value = preg_replace('/[\s　]/u', '', trim(mb_strtolower($name, 'UTF-8')));
  $value = str_replace(['司機', '.', '．', '‧', '・', '･', '-'], '', $value);
  return preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}]/u', '', $value);
}

function load_config_employees(): array {
  $path = __DIR__ . '/../../config/payroll_employees.php';
  if (!is_file($path)) {
    return [];
  }
  $data = require $path;
  if (!is_array($data)) {
    return [];
  }
  $rows = [];
  foreach ($data as $row) {
    if (!is_array($row)) {
      continue;
    }
    $code = isset($row['code']) ? (string) $row['code'] : '';
    $name = isset($row['name']) ? (string) $row['name'] : '';
    if ($code === '' || $name === '') {
      continue;
    }
    $rows[] = [
      'code' => $code,
      'name' => $name,
    ];
  }
  return $rows;
}

function compute_previous_period(int $rocYear, int $month): array {
  $month -= 1;
  if ($month <= 0) {
    $month += 12;
    $rocYear -= 1;
  }
  return [$rocYear, $month];
}

function add_amount(array &$bucket, $code, string $fieldId, int $amount): void {
  $code = (string) $code;
  if ($code === '' || $amount === 0) {
    return;
  }
  if (!isset($bucket[$code])) {
    $bucket[$code] = [];
  }
  $bucket[$code][$fieldId] = ($bucket[$code][$fieldId] ?? 0) + $amount;
}

function build_field_map(array $fields, array $values): array {
  $result = [];
  foreach ($fields as $field) {
    $id = $field['id'];
    if (isset($values[$id]) && $values[$id] !== 0) {
      $result[$id] = (int) $values[$id];
    }
  }
  return $result;
}

function fetch_driver_freight(
  PDO $pdo,
  int $rocYear,
  int $month,
  array $employees,
  array $nameDirectory,
  array &$warnings
): array {
  $stmt = $pdo->prepare(
    'SELECT driver_code, driver_name, SUM(freight) AS total
     FROM driver_summary_records
     WHERE roc_year = ? AND month = ?
     GROUP BY driver_code, driver_name'
  );
  $stmt->execute([$rocYear, $month]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) {
    return [];
  }
  $results = [];
  foreach ($rows as $row) {
    $code = normalize_employee_code((string) ($row['driver_code'] ?? ''));
    $driverName = trim((string) ($row['driver_name'] ?? ''));
    $amount = (int) ($row['total'] ?? 0);
    if ($amount === 0) {
      continue;
    }
    if ($code === '' || !isset($employees[$code])) {
      $code = resolve_code_by_name($driverName, $nameDirectory, $warnings, '司機金額總匯');
    }
    if ($code === null || $code === '' || !isset($employees[$code])) {
      $warnings[] = "司機金額總匯：找不到「{$driverName}」對應的員工代號";
      continue;
    }
    $results[$code] = ($results[$code] ?? 0) + $amount;
  }
  return $results;
}

function fetch_cpc_amounts(
  PDO $pdo,
  int $rocYear,
  int $month,
  array $employees,
  array $nameDirectory,
  array &$warnings
): array {
  $stmt = $pdo->prepare(
    'SELECT code, driver, SUM(amount) AS total
     FROM cpc_records
     WHERE roc_year = ? AND month = ?
     GROUP BY code, driver'
  );
  $stmt->execute([$rocYear, $month]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) {
    return [];
  }
  $results = [];
  foreach ($rows as $row) {
    $code = normalize_employee_code((string) ($row['code'] ?? ''));
    $driverName = trim((string) ($row['driver'] ?? ''));
    $amount = (int) ($row['total'] ?? 0);
    if ($amount === 0) {
      continue;
    }
    if ($code === '' || !isset($employees[$code])) {
      $code = resolve_code_by_name($driverName, $nameDirectory, $warnings, '中油卡名冊');
    }
    if ($code === null || $code === '' || !isset($employees[$code])) {
      $warnings[] = "中油卡名冊：找不到「{$driverName}」對應的員工代號";
      continue;
    }
    $results[$code] = ($results[$code] ?? 0) + $amount;
  }
  return $results;
}

function fetch_labor_personal_share(
  PDO $pdo,
  int $rocYear,
  int $month,
  array $employees,
  array $nameDirectory,
  array &$warnings
): array {
  $stmt = $pdo->prepare(
    'SELECT employee_code, employee_name, personal_share
     FROM labor_roster_records
     WHERE roc_year = ? AND month = ?'
  );
  $stmt->execute([$rocYear, $month]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) {
    return [];
  }
  $results = [];
  foreach ($rows as $row) {
    $code = normalize_employee_code((string) ($row['employee_code'] ?? ''));
    $name = trim((string) ($row['employee_name'] ?? ''));
    $amount = (int) ($row['personal_share'] ?? 0);
    if ($amount === 0) {
      continue;
    }
    if ($code === '' || !isset($employees[$code])) {
      $code = resolve_code_by_name($name, $nameDirectory, $warnings, '勞保名冊');
    }
    if ($code === null || $code === '' || !isset($employees[$code])) {
      $warnings[] = "勞保名冊：找不到「{$name}」對應的員工代號";
      continue;
    }
    $results[$code] = ($results[$code] ?? 0) + $amount;
  }
  return $results;
}

function fetch_health_self_total(
  PDO $pdo,
  int $rocYear,
  int $month,
  array $employees,
  array $nameDirectory,
  array &$warnings
): array {
  $stmt = $pdo->prepare(
    'SELECT dependent_name, self_total
     FROM health_roster_records
     WHERE roc_year = ? AND month = ?'
  );
  $stmt->execute([$rocYear, $month]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) {
    return [];
  }
  $results = [];
  foreach ($rows as $row) {
    $name = trim((string) ($row['dependent_name'] ?? ''));
    $amount = (int) ($row['self_total'] ?? 0);
    if ($amount === 0 || $name === '') {
      continue;
    }
    $code = resolve_code_by_name($name, $nameDirectory, $warnings, '健保名冊');
    if ($code === null || $code === '' || !isset($employees[$code])) {
      $warnings[] = "健保名冊：找不到「{$name}」對應的員工代號";
      continue;
    }
    $results[$code] = ($results[$code] ?? 0) + $amount;
  }
  return $results;
}

function fetch_petty_subject_totals(PDO $pdo, int $year, int $month, array $subjectMap): array {
  if (!$subjectMap) {
    return [];
  }
  $placeholders = implode(',', array_fill(0, count($subjectMap), '?'));
  $stmt = $pdo->prepare(
    "SELECT code, subject, SUM(expense) AS total
     FROM petty_cash_entries
     WHERE YEAR(entry_date) = ? AND MONTH(entry_date) = ? AND subject IN ($placeholders)
     GROUP BY code, subject"
  );
  $params = [$year, $month];
  foreach (array_keys($subjectMap) as $subject) {
    $params[] = $subject;
  }
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) {
    return [];
  }
  $results = [];
  foreach ($rows as $row) {
    $subject = trim((string) ($row['subject'] ?? ''));
    $field = $subjectMap[$subject] ?? null;
    if ($field === null) {
      continue;
    }
    $code = normalize_employee_code((string) ($row['code'] ?? ''));
    $amount = (int) ($row['total'] ?? 0);
    if ($code === '' || $amount === 0) {
      continue;
    }
    $results[$field][$code] = $amount;
  }
  return $results;
}

function fetch_klsb_expenses(
  int $year,
  int $month,
  array $employees,
  array $nameDirectory,
  array &$warnings
): array {
  $uploadsRoot = realpath(__DIR__ . '/../../uploads');
  if ($uploadsRoot === false) {
    return [];
  }
  $dir = $uploadsRoot . '/klsb/' . sprintf('%04d%02d', $year, $month);
  $snapshot = $dir . '/latest.json';
  if (!is_file($snapshot)) {
    return [];
  }
  $data = json_decode((string) file_get_contents($snapshot), true);
  if (!is_array($data) || empty($data['records']) || !is_array($data['records'])) {
    return [];
  }
  $results = [];
  foreach ($data['records'] as $record) {
    if (!is_array($record)) {
      continue;
    }
    $name = trim((string) ($record['reconciliation'] ?? ''));
    $amount = (int) ($record['expense'] ?? 0);
    if ($name === '' || $amount === 0) {
      continue;
    }
    $code = resolve_code_by_name($name, $nameDirectory, $warnings, '基隆二信');
    if ($code === null || $code === '' || !isset($employees[$code])) {
      $warnings[] = "基隆二信：找不到「{$name}」對應的員工代號";
      continue;
    }
    $results[$code] = ($results[$code] ?? 0) + $amount;
  }
  return $results;
}

function resolve_code_by_name(
  string $name,
  array $nameDirectory,
  array &$warnings,
  string $context
): ?string {
  $key = normalize_person_name($name);
  if ($key === '' || !isset($nameDirectory[$key])) {
    return null;
  }
  $codes = $nameDirectory[$key];
  if (count($codes) > 1) {
    $warnings[] = "{$context}：姓名「{$name}」對應多筆員工資料，請於資料維護中修正";
    return null;
  }
  return $codes[0];
}

function apply_named_allowances(array $assignments, array &$bucket, array $nameDirectory, array &$warnings): void {
  foreach ($assignments as $assignment) {
    $field = $assignment['field'];
    $amount = (int) ($assignment['amount'] ?? 0);
    $names = $assignment['names'] ?? [];
    if ($field === '' || $amount === 0 || !$names) {
      continue;
    }
    foreach ($names as $name) {
      $code = resolve_code_by_name($name, $nameDirectory, $warnings, $field);
      if ($code === null || $code === '') {
        $warnings[] = "{$field}：找不到「{$name}」對應的員工代號";
        continue;
      }
      add_amount($bucket, $code, $field, $amount);
    }
  }
}
