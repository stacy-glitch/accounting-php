<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/_entries.php';
require_once __DIR__ . '/../sales/_revenue.php';

const ADVANCE_LINK_STATUS_ACTIVE = 'linked';
const ADVANCE_LINK_STATUS_CANCELLED = 'cancelled';

function ensure_petty_advance_links_table(PDO $pdo): void {
  static $ensured = false;
  if ($ensured) {
    return;
  }

  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `petty_cash_advance_links` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `advance_entry_id` BIGINT UNSIGNED NOT NULL,
  `revenue_id` BIGINT UNSIGNED DEFAULT NULL,
  `target_year` SMALLINT NOT NULL,
  `target_month` TINYINT NOT NULL,
  `customer_code` VARCHAR(50) NOT NULL,
  `applied_amount` INT NOT NULL DEFAULT 0,
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT 'linked',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_advance_entry_id` (`advance_entry_id`),
  KEY `idx_revenue_id` (`revenue_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

  $pdo->exec($sql);
  $ensured = true;
}

function fetch_advance_links_by_entry_ids(PDO $pdo, array $entryIds): array {
  $ids = array_values(array_filter(array_map(static function ($value) {
    return (int) $value;
  }, $entryIds)));
  if (!$ids) {
    return [];
  }
  ensure_petty_advance_links_table($pdo);

  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $pdo->prepare(
    "SELECT id, advance_entry_id, revenue_id, target_year, target_month, customer_code, applied_amount, status, note, created_at, updated_at
     FROM petty_cash_advance_links
     WHERE advance_entry_id IN ($placeholders)"
  );
  $stmt->execute($ids);

  $result = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $entryId = (int) ($row['advance_entry_id'] ?? 0);
    if ($entryId === 0) {
      continue;
    }
    if (!isset($result[$entryId])) {
      $result[$entryId] = [];
    }
    $result[$entryId][] = normalize_advance_link_row($row);
  }
  return $result;
}

function normalize_advance_link_row(array $row): array {
  return [
    'id' => (int) ($row['id'] ?? 0),
    'advance_entry_id' => (int) ($row['advance_entry_id'] ?? 0),
    'revenue_id' => isset($row['revenue_id']) ? (int) $row['revenue_id'] : null,
    'target_year' => (int) ($row['target_year'] ?? 0),
    'target_month' => (int) ($row['target_month'] ?? 0),
    'customer_code' => (string) ($row['customer_code'] ?? ''),
    'applied_amount' => (int) ($row['applied_amount'] ?? 0),
    'status' => (string) ($row['status'] ?? ''),
    'note' => (string) ($row['note'] ?? ''),
    'created_at' => $row['created_at'] ?? null,
    'updated_at' => $row['updated_at'] ?? null,
  ];
}

function fetch_revenue_summaries(PDO $pdo, array $ids): array {
  $ids = array_values(array_filter(array_map(static function ($value) {
    return (int) $value;
  }, $ids)));
  if (!$ids) {
    return [];
  }
  if (!$ids) {
    return [];
  }
  ensure_petty_advance_links_table($pdo);

  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $pdo->prepare(
    "SELECT r.id,
            r.year,
            r.month,
            r.customer,
            r.customer_name,
            r.freight,
            r.invoice_amount,
            r.tax,
            r.warehouse_fee,
            r.total,
            r.actual_received,
            r.advance_total,
            r.received_date,
            r.received_method,
            r.note
     FROM sales_revenue r
     WHERE r.id IN ($placeholders)"
  );
  $stmt->execute($ids);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) {
    return [];
  }

  // fetch linked details
  $detailStmt = $pdo->prepare(
    "SELECT link.revenue_id,
            link.advance_entry_id,
            link.applied_amount,
            entry.entry_date,
            entry.transaction_date,
            entry.note
     FROM petty_cash_advance_links link
     INNER JOIN petty_cash_entries entry ON entry.id = link.advance_entry_id
     WHERE link.revenue_id IN ($placeholders) AND link.status = ?"
  );
  $detailParams = array_merge($ids, [ADVANCE_LINK_STATUS_ACTIVE]);
  $detailStmt->execute($detailParams);
  $detailRows = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

  $detailsByRevenue = [];
  foreach ($detailRows as $detail) {
    $revId = (int) ($detail['revenue_id'] ?? 0);
    if (!$revId) {
      continue;
    }
    if (!isset($detailsByRevenue[$revId])) {
      $detailsByRevenue[$revId] = [];
    }
    $detailsByRevenue[$revId][] = [
      'advance_entry_id' => (int) ($detail['advance_entry_id'] ?? 0),
      'amount' => (int) ($detail['applied_amount'] ?? 0),
      'entry_date' => $detail['entry_date'] ?? '',
      'transaction_date' => $detail['transaction_date'] ?? '',
      'note' => $detail['note'] ?? '',
    ];
  }

  $result = [];
  foreach ($rows as $row) {
    $id = (int) ($row['id'] ?? 0);
    if (!$id) {
      continue;
    }
    $result[$id] = [
      'id' => $id,
      'year' => (int) ($row['year'] ?? 0),
      'month' => (int) ($row['month'] ?? 0),
      'customer' => (string) ($row['customer'] ?? ''),
      'customer_name' => (string) ($row['customer_name'] ?? ''),
      'freight' => (int) ($row['freight'] ?? 0),
      'invoice_amount' => (int) ($row['invoice_amount'] ?? 0),
      'tax' => (int) ($row['tax'] ?? 0),
      'warehouse_fee' => (int) ($row['warehouse_fee'] ?? 0),
      'total' => (int) ($row['total'] ?? 0),
      'actual_received' => (int) ($row['actual_received'] ?? 0),
      'advance_total' => (int) ($row['advance_total'] ?? 0),
      'received_date' => (string) ($row['received_date'] ?? ''),
      'received_method' => (string) ($row['received_method'] ?? ''),
      'note' => (string) ($row['note'] ?? ''),
      'advance_details' => $detailsByRevenue[$id] ?? [],
    ];
  }
  return $result;
}

function calculate_remaining_for_advance(PDO $pdo, array $advance): int {
  $base = (int) ($advance['advance_expense'] ?? $advance['advance'] ?? 0);
  $repay = (int) ($advance['advance_income'] ?? 0);
  $income = (int) ($advance['income'] ?? 0);
  $paid = max($repay, 0) + max($income, 0);

  ensure_petty_advance_links_table($pdo);
  $stmt = $pdo->prepare(
    'SELECT SUM(applied_amount) AS linked FROM petty_cash_advance_links WHERE advance_entry_id = ? AND status = ?'
  );
  $stmt->execute([(int) $advance['id'], ADVANCE_LINK_STATUS_ACTIVE]);
  $linked = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['linked'] ?? 0);

  $remaining = $base - $paid - $linked;
  return max(0, $remaining);
}
