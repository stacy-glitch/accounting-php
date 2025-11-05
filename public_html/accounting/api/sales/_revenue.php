<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';

function ensure_sales_revenue_table(PDO $pdo): void {
  static $ensured = false;
  if ($ensured) {
    return;
  }

  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `sales_revenue` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `year` SMALLINT NOT NULL,
  `month` TINYINT NOT NULL,
  `customer` VARCHAR(50) NOT NULL,
  `customer_name` VARCHAR(150) NOT NULL DEFAULT '',
  `freight` INT NOT NULL DEFAULT 0,
  `invoice_amount` INT NOT NULL DEFAULT 0,
  `tax` INT NOT NULL DEFAULT 0,
  `warehouse_fee` INT NOT NULL DEFAULT 0,
  `total` INT NOT NULL DEFAULT 0,
  `actual_received` INT NOT NULL DEFAULT 0,
  `advance_total` INT NOT NULL DEFAULT 0,
  `received_date` VARCHAR(20) NOT NULL DEFAULT '',
  `received_method` VARCHAR(50) NOT NULL DEFAULT '',
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_period_customer` (`year`, `month`, `customer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

  $pdo->exec($sql);
  ensure_sales_column_exists($pdo, 'sales_revenue', 'advance_total', "ADD COLUMN `advance_total` INT NOT NULL DEFAULT 0 AFTER `actual_received`");
  $ensured = true;
}

function ensure_sales_alias_table(PDO $pdo): void {
  static $ensured = false;
  if ($ensured) {
    return;
  }

  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `sales_customer_aliases` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `alias_key` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(255) NOT NULL DEFAULT '',
  `customer_code` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_alias_key` (`alias_key`),
  KEY `idx_customer_code` (`customer_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

  $pdo->exec($sql);
  $ensured = true;
}

function fetch_customer_map(PDO $pdo): array {
  $stmt = $pdo->query('SELECT `code`, `name` FROM `customers`');
  $byCode = [];
  $byName = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $code = normalize_customer_code((string) ($row['code'] ?? ''));
    if ($code === '') {
      continue;
    }
    $name = trim((string) ($row['name'] ?? ''));
    $byCode[$code] = $name;
    if ($name !== '') {
      $byName[normalize_customer_name($name)] = $code;
    }
  }
  return [
    'by_code' => $byCode,
    'by_name' => $byName,
  ];
}

function fetch_sales_alias_map(PDO $pdo): array {
  ensure_sales_alias_table($pdo);
  $stmt = $pdo->query('SELECT `alias_key`, `display_name`, `customer_code` FROM `sales_customer_aliases`');
  $byAlias = [];
  $byCode = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $aliasKey = (string) ($row['alias_key'] ?? '');
    $code = normalize_customer_code((string) ($row['customer_code'] ?? ''));
    $name = trim((string) ($row['display_name'] ?? ''));
    if ($aliasKey === '' || $code === '') {
      continue;
    }
    $byAlias[$aliasKey] = [
      'code' => $code,
      'name' => $name,
    ];
    if ($name !== '') {
      $byCode[$code] = $name;
    } elseif (!isset($byCode[$code])) {
      $byCode[$code] = '';
    }
  }

  return [
    'by_alias' => $byAlias,
    'by_code' => $byCode,
  ];
}

function save_sales_aliases(PDO $pdo, array $aliases): void {
  if (empty($aliases)) {
    return;
  }
  ensure_sales_alias_table($pdo);
  $stmt = $pdo->prepare(
    'INSERT INTO `sales_customer_aliases` (`alias_key`, `display_name`, `customer_code`)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE `display_name` = VALUES(`display_name`), `customer_code` = VALUES(`customer_code`), `updated_at` = CURRENT_TIMESTAMP'
  );
  foreach ($aliases as $aliasKey => $info) {
    if (!is_array($info)) {
      continue;
    }
    $display = isset($info['name']) ? (string) $info['name'] : '';
    $code = isset($info['code']) ? normalize_customer_code((string) $info['code']) : '';
    if ($aliasKey === '' || $code === '') {
      continue;
    }
    $stmt->execute([$aliasKey, $display, $code]);
  }
}

function normalize_customer_code(string $code): string {
  $code = sales_remove_bom($code);
  $trimmed = trim($code);
  if ($trimmed === '') {
    return '';
  }
  if (substr($trimmed, -2) === '.0') {
    $trimmed = substr($trimmed, 0, -2);
  }
  return $trimmed;
}

function normalize_customer_name(string $name): string {
  $name = sales_remove_bom($name);
  $normalized = mb_strtolower(trim($name), 'UTF-8');
  return preg_replace('/\s+/u', '', $normalized);
}

function sales_remove_bom(string $text): string {
  if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) {
    return substr($text, 3) ?: '';
  }
  return $text;
}

function sales_limit_length(string $text, int $maxLength): string {
  if ($maxLength <= 0) {
    return '';
  }
  if (mb_strlen($text, 'UTF-8') <= $maxLength) {
    return $text;
  }
  return mb_substr($text, 0, $maxLength, 'UTF-8');
}

function sales_parse_amount($value): int {
  if (is_int($value)) {
    return $value;
  }
  if (is_float($value)) {
    return (int) round($value);
  }
  $text = trim((string) $value);
  if ($text === '') {
    return 0;
  }
  $normalized = str_replace([',', '$', ' '], '', $text);
  if ($normalized === '' || !is_numeric($normalized)) {
    return 0;
  }
  return (int) round((float) $normalized);
}

function sales_normalize_text($value, ?int $maxLength = null): string {
  $text = trim(sales_remove_bom((string) $value));
  if ($maxLength !== null && $maxLength > 0) {
    $text = sales_limit_length($text, $maxLength);
  }
  return $text;
}

function sales_normalize_received_date($value, int $defaultYear = 0, int $defaultMonth = 0): string {
  $defaultMonth = $defaultMonth >= 1 && $defaultMonth <= 12 ? $defaultMonth : 0;
  $text = sales_normalize_text($value, 50);
  if ($text === '') {
    return '';
  }

  if (preg_match('/^\d+(\.\d+)?$/', $text)) {
    $serial = (float) $text;
    $date = sales_excel_serial_to_date($serial);
    if ($date instanceof DateTimeImmutable) {
      return sales_limit_length($date->format('n/j'), 20);
    }
  }

  $normalizedSeparators = str_replace(
    ['年', '月', '日', '．', '.', '-', '—', '～', '~'],
    ['/', '/', '', '/', '/', '/', '/', '/', '/'],
    $text
  );
  $parts = array_values(array_filter(array_map('trim', explode('/', $normalizedSeparators)), 'strlen'));

  if (count($parts) >= 3) {
    $year = (int) $parts[0];
    $month = (int) $parts[1];
    $day = (int) $parts[2];
    if ($year > 0 && $year < 1911) {
      $year += 1911;
    } elseif ($year > 0 && $year < 100) {
      $year += 2000;
    }
    if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
      return sales_format_month_day($month, $day);
    }
  }

  if (count($parts) === 2) {
    $month = (int) $parts[0];
    $day = (int) $parts[1];
    if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
      return sales_format_month_day($month, $day);
    }
  }

  if (count($parts) === 1 && $defaultMonth !== 0) {
    $day = (int) $parts[0];
    if ($day >= 1 && $day <= 31) {
      return sales_format_month_day($defaultMonth, $day);
    }
  }

  $digitsOnly = preg_replace('/\D+/', '', $text);
  if ($digitsOnly !== '') {
    $length = strlen($digitsOnly);
    if ($length === 8) {
      $year = (int) substr($digitsOnly, 0, 4);
      $month = (int) substr($digitsOnly, 4, 2);
      $day = (int) substr($digitsOnly, 6, 2);
      if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
        return sales_format_month_day($month, $day);
      }
    } elseif ($length === 7) {
      $year = (int) substr($digitsOnly, 0, 3) + 1911;
      $month = (int) substr($digitsOnly, 3, 2);
      $day = (int) substr($digitsOnly, 5, 2);
      if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
        return sales_format_month_day($month, $day);
      }
    } elseif ($length === 6) {
      $year = (int) substr($digitsOnly, 0, 2);
      $month = (int) substr($digitsOnly, 2, 2);
      $day = (int) substr($digitsOnly, 4, 2);
      $year += $year >= 70 ? 1900 : 2000;
      if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
        return sales_format_month_day($month, $day);
      }
    } elseif ($length === 4) {
      $month = (int) substr($digitsOnly, 0, 2);
      $day = (int) substr($digitsOnly, 2, 2);
      if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
        return sales_format_month_day($month, $day);
      }
    } elseif ($length === 2 && $defaultMonth !== 0) {
      $day = (int) $digitsOnly;
      if ($day >= 1 && $day <= 31) {
        return sales_format_month_day($defaultMonth, $day);
      }
    }
  }

  return sales_limit_length($text, 20);
}

function sales_format_month_day(int $month, int $day): string {
  $normalizedMonth = max(1, min(12, $month));
  $normalizedDay = max(1, min(31, $day));
  return sales_limit_length(sprintf('%d/%d', $normalizedMonth, $normalizedDay), 20);
}

function sales_excel_serial_to_date(float $serial): ?DateTimeImmutable {
  if ($serial <= 0) {
    return null;
  }
  $days = (int) floor($serial);
  $base = new DateTimeImmutable('1899-12-30');
  if ($days >= 60) {
    $days -= 1;
  }
  $date = $base->modify('+' . $days . ' days');
  if ($date === false) {
    return null;
  }
  return $date;
}

function ensure_sales_column_exists(PDO $pdo, string $table, string $column, string $alterSql): void {
  $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
  $stmt->execute([$column]);
  if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    $pdo->exec('ALTER TABLE `' . $table . '` ' . $alterSql);
  }
}
