<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
switch ($method) {
  case 'GET':
    handle_get();
    break;
  case 'POST':
    handle_save();
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
    json_err('請提供正確的ROC年份');
  }
  if (!is_int($month) || $month < 1 || $month > 12) {
    json_err('請提供正確的月份');
  }

  $pdo = pdo();
  ensure_summary_table($pdo);
  $rows = fetch_summary($pdo, $rocYear, $month);
  json_ok(['records' => $rows]);
}

function handle_save(): void {
  $payload = json_decode(file_get_contents('php://input'), true);
  if (!is_array($payload)) {
    json_err('Invalid payload');
  }
  $rocYear = isset($payload['roc_year']) ? (int) $payload['roc_year'] : 0;
  $month = isset($payload['month']) ? (int) $payload['month'] : 0;
  if ($rocYear <= 0 || $month < 1 || $month > 12) {
    json_err('請提供正確的期間');
  }
  $code = trim((string) ($payload['code'] ?? ''));
  if ($code === '') {
    json_err('缺少代號，無法儲存');
  }
  $driver = trim((string) ($payload['driver'] ?? ''));
  $paperAmount = normalize_amount($payload['paper_amount'] ?? 0);
  $note = trim((string) ($payload['note'] ?? ''));

  $pdo = pdo();
  ensure_summary_table($pdo);
  $stmt = $pdo->prepare(
    'INSERT INTO cpc_summary (roc_year, month, code, driver, paper_amount, note)
     VALUES (?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE driver = VALUES(driver), paper_amount = VALUES(paper_amount), note = VALUES(note)'
  );
  $stmt->execute([$rocYear, $month, $code, $driver, $paperAmount, $note]);

  $rows = fetch_summary($pdo, $rocYear, $month);
  json_ok(['records' => $rows, 'message' => 'saved']);
}

function handle_delete(): void {
  $rocYear = filter_input(INPUT_GET, 'roc_year', FILTER_VALIDATE_INT);
  $month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);
  $code = isset($_GET['code']) ? trim((string) $_GET['code']) : '';

  if ((!is_int($rocYear) || $rocYear <= 0) || (!is_int($month) || $month < 1 || $month > 12) || $code === '') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (is_array($payload)) {
      $rocYear = isset($payload['roc_year']) ? (int) $payload['roc_year'] : $rocYear;
      $month = isset($payload['month']) ? (int) $payload['month'] : $month;
      $code = isset($payload['code']) ? trim((string) $payload['code']) : $code;
    }
  }

  if ($rocYear <= 0 || $month < 1 || $month > 12 || $code === '') {
    json_err('缺少刪除條件');
  }

  $pdo = pdo();
  ensure_summary_table($pdo);
  $stmt = $pdo->prepare('DELETE FROM cpc_summary WHERE roc_year = ? AND month = ? AND code = ?');
  $stmt->execute([$rocYear, $month, $code]);

  $rows = fetch_summary($pdo, $rocYear, $month);
  json_ok(['records' => $rows, 'message' => 'deleted']);
}

function fetch_summary(PDO $pdo, int $rocYear, int $month): array {
  $groupExpr = "CASE
      WHEN code IS NOT NULL AND code <> '' THEN code
      WHEN license_plate IS NOT NULL AND license_plate <> '' THEN license_plate
      WHEN driver IS NOT NULL AND driver <> '' THEN driver
      ELSE CONCAT('row-', id)
    END";

  $sql = "SELECT agg.code,
                 agg.driver,
                 agg.oil_amount,
                 agg.record_count,
                 COALESCE(summary.paper_amount, 0) AS paper_amount,
                 COALESCE(summary.note, '') AS note,
                 summary.id AS summary_id
          FROM (
            SELECT $groupExpr AS code,
                   MAX(driver) AS driver,
                   SUM(amount) AS oil_amount,
                   COUNT(*) AS record_count
            FROM cpc_records
            WHERE roc_year = :roc_year AND month = :month
            GROUP BY $groupExpr
          ) AS agg
          LEFT JOIN cpc_summary AS summary
            ON summary.roc_year = :roc_year AND summary.month = :month AND summary.code = agg.code
          ORDER BY agg.code";

  $stmt = $pdo->prepare($sql);
  $stmt->execute(['roc_year' => $rocYear, 'month' => $month]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  return array_map(static function ($row) {
    return [
      'code' => (string) ($row['code'] ?? ''),
      'driver' => (string) ($row['driver'] ?? ''),
      'oil_amount' => (int) ($row['oil_amount'] ?? 0),
      'record_count' => (int) ($row['record_count'] ?? 0),
      'paper_amount' => (int) ($row['paper_amount'] ?? 0),
      'note' => (string) ($row['note'] ?? ''),
      'summary_id' => (int) ($row['summary_id'] ?? 0),
    ];
  }, $rows);
}

function normalize_amount($value): int {
  if ($value === null) {
    return 0;
  }
  if (is_numeric($value)) {
    return (int) round((float) $value);
  }
  $filtered = preg_replace('/[^\d.\-]/', '', (string) $value);
  if ($filtered === '' || !is_numeric($filtered)) {
    return 0;
  }
  return (int) round((float) $filtered);
}

function ensure_summary_table(PDO $pdo): void {
  static $created = false;
  if ($created) {
    return;
  }
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS cpc_summary (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  roc_year INT NOT NULL,
  month TINYINT NOT NULL,
  code VARCHAR(100) NOT NULL,
  driver VARCHAR(100) DEFAULT '',
  paper_amount INT DEFAULT 0,
  note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_period_code (roc_year, month, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
  $pdo->exec($sql);
  $created = true;
}
