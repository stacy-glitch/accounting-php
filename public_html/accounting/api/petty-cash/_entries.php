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
  `invoice_path` VARCHAR(255) DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entry_date` (`entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

  $pdo->exec($sql);
  ensure_column_exists($pdo, 'petty_cash_entries', 'invoice_path', "ADD COLUMN `invoice_path` VARCHAR(255) DEFAULT '' AFTER `advance_status`");
  $ensured = true;
}

function fetch_entries_by_period(PDO $pdo, int $year, int $month): array {
  $stmt = $pdo->prepare(
    'SELECT id, entry_date, transaction_date, transaction_month, code, subject, note, income, expense, advance, advance_status, invoice_path
     FROM petty_cash_entries
     WHERE YEAR(entry_date) = ? AND MONTH(entry_date) = ?
     ORDER BY entry_date ASC, id ASC'
  );
  $stmt->execute([$year, $month]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) {
    return [];
  }
  return array_map('normalize_entry_row', $rows);
}

function insert_entry(PDO $pdo, array $data): int {
  $stmt = $pdo->prepare(
    'INSERT INTO petty_cash_entries
      (entry_date, transaction_date, transaction_month, code, subject, note, income, expense, advance, advance_status, invoice_path)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
    $data['invoice_path'] ?? '',
  ]);

  return (int) $pdo->lastInsertId();
}

function update_entry(PDO $pdo, int $id, array $data): void {
  $stmt = $pdo->prepare(
    'UPDATE petty_cash_entries
     SET entry_date = ?, transaction_date = ?, transaction_month = ?, code = ?, subject = ?, note = ?, income = ?, expense = ?, advance = ?, advance_status = ?, invoice_path = ?
     WHERE id = ?'
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
    $data['invoice_path'] ?? '',
    $id,
  ]);
}

function delete_entry(PDO $pdo, int $id): bool {
  $stmt = $pdo->prepare('DELETE FROM petty_cash_entries WHERE id = ?');
  $stmt->execute([$id]);
  return $stmt->rowCount() > 0;
}

function fetch_entry_by_id(PDO $pdo, int $id): ?array {
  $stmt = $pdo->prepare(
    'SELECT id, entry_date, transaction_date, transaction_month, code, subject, note, income, expense, advance, advance_status, invoice_path
     FROM petty_cash_entries
     WHERE id = ?
     LIMIT 1'
  );
  $stmt->execute([$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    return null;
  }
  return normalize_entry_row($row);
}

function normalize_entry_row(array $row): array {
  $invoicePath = (string) ($row['invoice_path'] ?? '');
  return [
    'id' => (int) ($row['id'] ?? 0),
    'entry_date' => $row['entry_date'],
    'transaction_date' => $row['transaction_date'] ?: null,
    'transaction_month' => $row['transaction_month'] ?: '',
    'code' => (string) ($row['code'] ?? ''),
    'subject' => (string) ($row['subject'] ?? ''),
    'note' => (string) ($row['note'] ?? ''),
    'income' => (int) ($row['income'] ?? 0),
    'expense' => (int) ($row['expense'] ?? 0),
    'advance' => (int) ($row['advance'] ?? 0),
    'advance_status' => (string) ($row['advance_status'] ?? ''),
    'invoice_path' => $invoicePath,
    'invoice_url' => invoice_public_url($invoicePath),
  ];
}

function delete_entries_by_period(PDO $pdo, int $year, int $month): int {
  $stmt = $pdo->prepare('DELETE FROM petty_cash_entries WHERE YEAR(entry_date) = ? AND MONTH(entry_date) = ?');
  $stmt->execute([$year, $month]);
  return $stmt->rowCount();
}

function normalize_transaction_month_value(string $value): string {
  $value = trim($value);
  if ($value === '') {
    return '';
  }

  if (preg_match('/^\d{5}$/', $value)) {
    return $value;
  }

  if (preg_match('/^\d{4}$/', $value)) {
    $roc = (int) substr($value, 0, 2);
    $month = (int) substr($value, 2, 2);
    if ($roc > 0 && $month >= 1 && $month <= 12) {
      return sprintf('%03d%02d', $roc, $month);
    }
    return '';
  }

  if (preg_match('/^(\d{2,3})\D+(\d{1,2})$/u', $value, $matches)) {
    $roc = (int) $matches[1];
    $month = (int) $matches[2];
    if ($roc > 0 && $month >= 1 && $month <= 12) {
      return sprintf('%03d%02d', $roc, $month);
    }
    return '';
  }

  if (preg_match('/^(\d{4})[\/\-](\d{1,2})$/', $value, $matches)) {
    $year = (int) $matches[1];
    $month = (int) $matches[2];
    if ($year >= 1911 && $month >= 1 && $month <= 12) {
      return sprintf('%03d%02d', $year - 1911, $month);
    }
  }

  $digits = preg_replace('/\D+/', '', $value);
  if (strlen($digits) === 5) {
    $roc = (int) substr($digits, 0, 3);
    $month = (int) substr($digits, 3, 2);
    if ($roc > 0 && $month >= 1 && $month <= 12) {
      return sprintf('%03d%02d', $roc, $month);
    }
  }

  if (strlen($digits) === 4) {
    $roc = (int) substr($digits, 0, 2);
    $month = (int) substr($digits, 2, 2);
    if ($roc > 0 && $month >= 1 && $month <= 12) {
      return sprintf('%03d%02d', $roc, $month);
    }
  }

  if (strlen($digits) === 3) {
    $roc = (int) substr($digits, 0, 2);
    $month = (int) substr($digits, 2, 1);
    if ($roc > 0 && $month >= 1 && $month <= 9) {
      return sprintf('%03d%02d', $roc, $month);
    }
  }

  return '';
}

function normalize_transaction_month_from_iso(?string $isoDate): string {
  if (!$isoDate) {
    return '';
  }
  $dt = DateTime::createFromFormat('Y-m-d', $isoDate);
  if (!$dt) {
    return '';
  }
  $year = (int) $dt->format('Y');
  $month = (int) $dt->format('n');
  $roc = $year - 1911;
  if ($roc <= 0 || $month < 1 || $month > 12) {
    return '';
  }
  return sprintf('%03d%02d', $roc, $month);
}

function ensure_column_exists(PDO $pdo, string $table, string $column, string $alterSql): void {
  $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
  $stmt->execute([$column]);
  if (!$stmt->fetch()) {
    $pdo->exec('ALTER TABLE `' . $table . '` ' . $alterSql);
  }
}

function invoice_public_url(string $path): string {
  $path = trim($path);
  if ($path === '') {
    return '';
  }
  $clean = ltrim($path, '/');
  return '../' . $clean;
}
