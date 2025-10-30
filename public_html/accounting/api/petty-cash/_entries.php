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
  `advance_income` INT NOT NULL DEFAULT 0,
  `advance_expense` INT NOT NULL DEFAULT 0,
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
  ensure_column_exists($pdo, 'petty_cash_entries', 'advance_income', "ADD COLUMN `advance_income` INT NOT NULL DEFAULT 0 AFTER `expense`");
  ensure_column_exists($pdo, 'petty_cash_entries', 'advance_expense', "ADD COLUMN `advance_expense` INT NOT NULL DEFAULT 0 AFTER `advance_income`");
  ensure_column_exists($pdo, 'petty_cash_entries', 'invoice_path', "ADD COLUMN `invoice_path` VARCHAR(255) DEFAULT '' AFTER `advance_status`");
  $ensured = true;
}

function fetch_entries_by_period(PDO $pdo, int $year, int $month): array {
  $stmt = $pdo->prepare(
    'SELECT id, entry_date, transaction_date, transaction_month, code, subject, note, income, expense, advance_income, advance_expense, advance, advance_status, invoice_path
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
  $legacyAdvance = isset($data['advance']) ? (int) $data['advance'] : null;
  $advanceIncome = isset($data['advance_income']) ? (int) $data['advance_income'] : 0;
  $advanceExpense = isset($data['advance_expense']) ? (int) $data['advance_expense'] : 0;
  $advanceValue = $legacyAdvance !== null ? $legacyAdvance : $advanceExpense;

  $stmt = $pdo->prepare(
    'INSERT INTO petty_cash_entries
      (entry_date, transaction_date, transaction_month, code, subject, note, income, expense, advance_income, advance_expense, advance, advance_status, invoice_path)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
    $advanceIncome,
    $advanceExpense,
    max(0, $advanceValue),
    $data['advance_status'],
    $data['invoice_path'] ?? '',
  ]);

  return (int) $pdo->lastInsertId();
}

function update_entry(PDO $pdo, int $id, array $data): void {
  $legacyAdvance = isset($data['advance']) ? (int) $data['advance'] : null;
  $advanceIncome = isset($data['advance_income']) ? (int) $data['advance_income'] : 0;
  $advanceExpense = isset($data['advance_expense']) ? (int) $data['advance_expense'] : 0;
  $advanceValue = $legacyAdvance !== null ? $legacyAdvance : $advanceExpense;

  $stmt = $pdo->prepare(
    'UPDATE petty_cash_entries
     SET entry_date = ?, transaction_date = ?, transaction_month = ?, code = ?, subject = ?, note = ?, income = ?, expense = ?, advance_income = ?, advance_expense = ?, advance = ?, advance_status = ?, invoice_path = ?
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
    $advanceIncome,
    $advanceExpense,
    max(0, $advanceValue),
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
    'SELECT id, entry_date, transaction_date, transaction_month, code, subject, note, income, expense, advance_income, advance_expense, advance, advance_status, invoice_path
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
  $advanceIncome = (int) ($row['advance_income'] ?? 0);
  $advanceExpense = (int) ($row['advance_expense'] ?? 0);
  $legacyAdvance = (int) ($row['advance'] ?? 0);
  if ($advanceExpense === 0 && $advanceIncome === 0 && $legacyAdvance !== 0) {
    $advanceExpense = $legacyAdvance;
  }
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
    'advance_income' => $advanceIncome,
    'advance_expense' => $advanceExpense,
    'advance' => $legacyAdvance ?: $advanceExpense,
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

function normalize_entry_payload(array $payload, array $context = []): array {
  $existing = $context['existing'] ?? null;

  $entryDate = require_iso_date($payload['entry_date'] ?? '', '登記日期');

  $transactionDateRaw = trim((string) ($payload['transaction_date'] ?? ''));
  $transactionMonthRaw = trim((string) ($payload['transaction_month'] ?? ''));

  if ($transactionDateRaw !== '' && $transactionMonthRaw !== '') {
    json_err('實際交易日期與實際交易年月僅能擇一填寫');
  }

  $transactionDate = null;
  if ($transactionDateRaw !== '') {
    $transactionDate = require_iso_date($transactionDateRaw, '實際交易日期');
  }

  if ($transactionMonthRaw !== '') {
    $transactionMonth = normalize_transaction_month_value($transactionMonthRaw);
    if ($transactionMonth === '') {
      json_err('實際交易年月格式錯誤');
    }
  } else {
    $transactionMonth = normalize_transaction_month_from_iso($transactionDate ?? $entryDate);
  }

  $code = trim((string) ($payload['code'] ?? ''));
  if ($code === '') {
    json_err('代號不得為空');
  }

  $subject = trim((string) ($payload['subject'] ?? ''));
  if ($subject === '') {
    json_err('會計科目不得為空');
  }

  $note = trim((string) ($payload['note'] ?? ''));

  $income = parse_non_negative_int_value($payload['income'] ?? 0, '收入金額');
  $expense = parse_non_negative_int_value($payload['expense'] ?? 0, '支出金額');

  $advanceIncomeInput = $payload['advance_income'] ?? $payload['advanceIncome'] ?? null;
  $advanceExpenseInput = $payload['advance_expense'] ?? $payload['advanceExpense'] ?? null;
  if ($advanceIncomeInput === null && $advanceExpenseInput === null && array_key_exists('advance', $payload)) {
    $advanceIncome = 0;
    $advanceExpense = parse_non_negative_int_value($payload['advance'], '代墊款');
  } else {
    $advanceIncome = parse_non_negative_int_value($advanceIncomeInput ?? 0, '代墊款收入');
    $advanceExpense = parse_non_negative_int_value($advanceExpenseInput ?? 0, '代墊款支出');
  }

  if ($income === 0 && $expense === 0 && $advanceIncome === 0 && $advanceExpense === 0) {
    json_err('收入、支出、代墊款不可同時為 0');
  }

  $advanceStatus = trim((string) ($payload['advance_status'] ?? $payload['advanceStatus'] ?? ($existing['advance_status'] ?? '')));

  return [
    'entry_date' => $entryDate,
    'transaction_date' => $transactionDate,
    'transaction_month' => $transactionMonth,
    'code' => $code,
    'subject' => $subject,
    'note' => $note,
    'income' => $income,
    'expense' => $expense,
    'advance_income' => $advanceIncome,
    'advance_expense' => $advanceExpense,
    'advance' => $advanceExpense,
    'advance_status' => $advanceStatus,
  ];
}

function require_iso_date(string $value, string $label): string {
  $value = trim($value);
  if ($value === '') {
    json_err($label . '不可為空');
  }
  $dt = DateTime::createFromFormat('Y-m-d', $value);
  if (!$dt) {
    json_err($label . '格式錯誤');
  }
  return $dt->format('Y-m-d');
}

function parse_non_negative_int_value($value, string $label): int {
  if (is_string($value)) {
    $value = str_replace(',', '', trim($value));
  }
  if ($value === '' || $value === null) {
    return 0;
  }
  if (!is_numeric($value)) {
    json_err($label . '格式錯誤，僅能輸入整數');
  }
  $int = (int) round((float) $value);
  if ($int < 0) {
    json_err($label . '不可為負數');
  }
  return $int;
}
