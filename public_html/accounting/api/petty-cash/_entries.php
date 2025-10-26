<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';

function ensure_entries_table(PDO $pdo): void {
  static $ensured = false;
  if ($ensured) {
    return;
  }

  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `petty_cash_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entry_date` DATE NOT NULL,
  `transaction_date` DATE DEFAULT NULL,
  `transaction_month` VARCHAR(10) DEFAULT NULL,
  `code` VARCHAR(50) NOT NULL,
  `subject` VARCHAR(150) NOT NULL,
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  `income` INT NOT NULL DEFAULT 0,
  `expense` INT NOT NULL DEFAULT 0,
  `advance` INT NOT NULL DEFAULT 0,
  `advance_status` VARCHAR(50) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entry_date` (`entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

  $pdo->exec($sql);
  $ensured = true;
}

function fetch_entries_by_period(PDO $pdo, int $year, int $month): array {
  $stmt = $pdo->prepare(
    'SELECT id, entry_date, transaction_date, transaction_month, code, subject, note, income, expense, advance, advance_status
     FROM petty_cash_entries
     WHERE YEAR(entry_date) = ? AND MONTH(entry_date) = ?
     ORDER BY entry_date ASC, id ASC'
  );
  $stmt->execute([$year, $month]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) {
    return [];
  }
  return array_map(static function (array $row): array {
    return [
      'id' => (int) $row['id'],
      'entry_date' => $row['entry_date'],
      'transaction_date' => $row['transaction_date'] ?: null,
      'transaction_month' => $row['transaction_month'] ?: '',
      'code' => (string) $row['code'],
      'subject' => (string) $row['subject'],
      'note' => (string) $row['note'],
      'income' => (int) $row['income'],
      'expense' => (int) $row['expense'],
      'advance' => (int) $row['advance'],
      'advance_status' => (string) $row['advance_status'],
    ];
  }, $rows);
}

function insert_entry(PDO $pdo, array $data): int {
  $stmt = $pdo->prepare(
    'INSERT INTO petty_cash_entries
      (entry_date, transaction_date, transaction_month, code, subject, note, income, expense, advance, advance_status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
  );
  $stmt->execute([
    $data['entry_date'],
    $data['transaction_date'] ?: null,
    $data['transaction_month'] ?: null,
    $data['code'],
    $data['subject'],
    $data['note'],
    $data['income'],
    $data['expense'],
    $data['advance'],
    $data['advance_status'],
  ]);

  return (int) $pdo->lastInsertId();
}
