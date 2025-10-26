<?php
declare(strict_types=1);

require_once __DIR__ . '/_balances.php';

$pdo = pdo();
ensure_opening_balance_table($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$year = isset($_REQUEST['year']) ? (int) $_REQUEST['year'] : (int) date('Y');
$month = isset($_REQUEST['month']) ? (int) $_REQUEST['month'] : (int) date('n');

validate_period($year, $month);

if ($method === 'GET') {
  $record = fetch_opening_balance($pdo, $year, $month);
  json_ok([
    'endpoint' => 'petty-cash/opening_balance',
    'data' => $record,
  ]);
}

if ($method !== 'POST') {
  json_err('Method not allowed', 405);
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
  $payload = $_POST;
}

$amount = $payload['opening_balance'] ?? null;
$note = isset($payload['note']) ? trim((string) $payload['note']) : '';

if (!is_finite_number($amount)) {
  json_err('期初餘額必須為數字');
}

$amount = normalize_opening_amount($amount);

$stmt = $pdo->prepare(
  "INSERT INTO `petty_cash_opening_balances` (`year`, `month`, `opening_balance`, `note`)
   VALUES (?, ?, ?, ?)
   ON DUPLICATE KEY UPDATE `opening_balance` = VALUES(`opening_balance`), `note` = VALUES(`note`), `updated_at` = CURRENT_TIMESTAMP"
);
$stmt->execute([$year, $month, $amount, $note]);

$record = fetch_opening_balance($pdo, $year, $month);

json_ok([
  'endpoint' => 'petty-cash/opening_balance',
  'message' => '期初餘額已更新',
  'data' => $record,
]);

function normalize_opening_amount($value): int {
  $numeric = (int) round((float) str_replace(',', '', (string) $value));
  if ($numeric < 0) {
    json_err('期初餘額不可為負數');
  }
  return $numeric;
}
