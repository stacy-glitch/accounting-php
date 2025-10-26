<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';

function ensure_opening_balance_table(PDO $pdo): void {
  static $ensured = false;
  if ($ensured) {
    return;
  }
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `petty_cash_opening_balances` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `year` SMALLINT NOT NULL,
  `month` TINYINT NOT NULL,
  `opening_balance` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_year_month` (`year`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
  $pdo->exec($sql);
  $ensured = true;
}

function validate_period(int $year, int $month): void {
  if ($year < 1911 || $year > 2100) {
    json_err('年份格式有誤');
  }
  if ($month < 1 || $month > 12) {
    json_err('月份格式有誤');
  }
}

function fetch_opening_balance(PDO $pdo, int $year, int $month): array {
  $stmt = $pdo->prepare(
    "SELECT `year`, `month`, `opening_balance`, `note`, `updated_at`
     FROM `petty_cash_opening_balances`
     WHERE `year` = ? AND `month` = ?
     LIMIT 1"
  );
  $stmt->execute([$year, $month]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    return [
      'year' => $year,
      'month' => $month,
      'opening_balance' => 0.0,
      'note' => '',
      'updated_at' => null,
    ];
  }
  $row['opening_balance'] = (int) $row['opening_balance'];
  return $row;
}

function is_finite_number($value): bool {
  if ($value === null) {
    return false;
  }
  if (is_string($value)) {
    $value = trim($value);
    if ($value === '') {
      return false;
    }
    $value = str_replace(',', '', $value);
  }
  if (!is_numeric($value)) {
    return false;
  }
  return is_finite((float) $value);
}
