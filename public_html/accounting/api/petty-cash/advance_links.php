<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/_entries.php';
require_once __DIR__ . '/_advance_links.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

switch ($method) {
  case 'POST':
    handle_create_link();
    break;
  case 'DELETE':
    handle_cancel_link();
    break;
  default:
    json_err('Method not allowed', 405);
}

function handle_create_link(): void {
  $input = json_decode(file_get_contents('php://input') ?: 'null', true);
  if (!is_array($input)) {
    $input = $_POST;
  }

  $advanceId = isset($input['advance_id']) ? (int) $input['advance_id'] : (isset($input['advanceId']) ? (int) $input['advanceId'] : 0);
  if ($advanceId <= 0) {
    json_err('缺少代墊款記錄編號');
  }

  $rawAmount = $input['amount'] ?? ($input['applied_amount'] ?? null);
  $attachRevenueId = isset($input['revenue_id']) ? (int) $input['revenue_id'] : (isset($input['revenueId']) ? (int) $input['revenueId'] : 0);
  $note = isset($input['note']) ? trim((string) $input['note']) : '';

  $pdo = pdo();
  ensure_entries_table($pdo);
  ensure_petty_advance_links_table($pdo);
  ensure_sales_revenue_table($pdo);

  $advance = fetch_entry_by_id($pdo, $advanceId);
  if (!$advance) {
    json_err('找不到指定的代墊款記錄', 404);
  }

  $remainingBefore = calculate_remaining_for_advance($pdo, $advance);
  if ($remainingBefore <= 0) {
    json_err('此代墊款已完成銷帳');
  }

  if ($rawAmount === null || $rawAmount === '') {
    $amount = $remainingBefore;
  } else {
    $amount = parse_non_negative_int_value($rawAmount, '銷帳金額');
  }

  if ($amount <= 0) {
    json_err('銷帳金額需大於 0');
  }
  if ($amount > $remainingBefore) {
    json_err('銷帳金額不可超過未銷餘額');
  }

  $entryDate = (string) ($advance['entry_date'] ?? '');
  $entryDt = DateTimeImmutable::createFromFormat('Y-m-d', $entryDate);
  if (!$entryDt) {
    json_err('代墊款缺少有效的登記日期，無法建立銷帳');
  }
  $targetYear = (int) $entryDt->format('Y');
  $targetMonth = (int) $entryDt->format('n');
  if ($targetYear < 2000 || $targetYear > 2100) {
    json_err('代墊款登記日期超出可處理範圍');
  }

  $customerCode = trim((string) ($advance['code'] ?? ''));
  $normalizedCustomerCode = normalize_customer_code($customerCode);
  if ($normalizedCustomerCode !== '') {
    $customerCode = $normalizedCustomerCode;
  }
  if ($customerCode === '') {
    json_err('代墊款缺少代號，無法對應到營收報表');
  }

  $remainingRow = null;
  try {
    $pdo->beginTransaction();

    $revenue = ensure_revenue_for_advance($pdo, $advance, $targetYear, $targetMonth, $customerCode, $amount, $note, $attachRevenueId);

    $insert = $pdo->prepare(
      'INSERT INTO petty_cash_advance_links
        (advance_entry_id, revenue_id, target_year, target_month, customer_code, applied_amount, note, status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
      $advanceId,
      $revenue['id'],
      $targetYear,
      $targetMonth,
      $customerCode,
      $amount,
      $note,
      ADVANCE_LINK_STATUS_ACTIVE,
    ]);
    $linkId = (int) $pdo->lastInsertId();

    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    json_err('建立銷帳連結失敗：' . $e->getMessage(), 500);
  }

  $links = fetch_advance_links_by_entry_ids($pdo, [$advanceId]);
  $activeLinks = $links[$advanceId] ?? [];
  $revenueSummaries = fetch_revenue_summaries($pdo, [$revenue['id']]);
  $remainingAfter = calculate_remaining_for_advance($pdo, $advance);

  json_ok([
    'endpoint' => 'petty-cash/advance_links',
    'message' => '已併入營收報表',
    'data' => [
      'advance_id' => $advanceId,
      'created_link_id' => $linkId,
      'remaining_amount' => $remainingAfter,
      'links' => $activeLinks,
      'revenue' => $revenueSummaries[$revenue['id']] ?? $revenue,
    ],
  ]);
}

function handle_cancel_link(): void {
  parse_str($_SERVER['QUERY_STRING'] ?? '', $queryParams);
  $input = json_decode(file_get_contents('php://input') ?: 'null', true);
  if (!is_array($input)) {
    $input = [];
  }
  $linkId = 0;
  if (isset($queryParams['id'])) {
    $linkId = (int) $queryParams['id'];
  }
  if (!$linkId && isset($input['id'])) {
    $linkId = (int) $input['id'];
  }
  if ($linkId <= 0) {
    json_err('缺少銷帳連結編號');
  }

  $pdo = pdo();
  ensure_petty_advance_links_table($pdo);
  ensure_sales_revenue_table($pdo);
  ensure_entries_table($pdo);

  $stmt = $pdo->prepare(
    'SELECT id, advance_entry_id, revenue_id, target_year, target_month, customer_code, applied_amount, status, note
     FROM petty_cash_advance_links
     WHERE id = ?
     LIMIT 1'
  );
  $stmt->execute([$linkId]);
  $link = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$link) {
    json_err('找不到指定的銷帳連結', 404);
  }
  if ($link['status'] !== ADVANCE_LINK_STATUS_ACTIVE) {
    json_err('此銷帳連結已被取消', 400);
  }

  $advanceId = (int) ($link['advance_entry_id'] ?? 0);
  $advance = $advanceId > 0 ? fetch_entry_by_id($pdo, $advanceId) : null;
  if (!$advance) {
    json_err('找不到對應的代墊款記錄', 404);
  }

  $revenueId = isset($link['revenue_id']) ? (int) $link['revenue_id'] : 0;
  $amount = (int) ($link['applied_amount'] ?? 0);

  try {
    $pdo->beginTransaction();

    if ($revenueId > 0) {
      $update = $pdo->prepare(
        'UPDATE sales_revenue
         SET warehouse_fee = GREATEST(warehouse_fee - ?, 0),
             advance_total = GREATEST(advance_total - ?, 0),
             updated_at = CURRENT_TIMESTAMP
         WHERE id = ?'
      );
      $update->execute([$amount, $amount, $revenueId]);
      $remainingRow = fetch_revenue_by_id($pdo, $revenueId);
      if ($remainingRow && should_delete_revenue_row($remainingRow)) {
        $delete = $pdo->prepare('DELETE FROM sales_revenue WHERE id = ?');
        $delete->execute([$revenueId]);
        $remainingRow = null;
        $revenueId = 0;
      }
    }

    $cancel = $pdo->prepare(
      'UPDATE petty_cash_advance_links
       SET status = ?, updated_at = CURRENT_TIMESTAMP
       WHERE id = ?'
    );
    $cancel->execute([ADVANCE_LINK_STATUS_CANCELLED, $linkId]);

    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    json_err('取消銷帳連結失敗：' . $e->getMessage(), 500);
  }

  $remaining = calculate_remaining_for_advance($pdo, $advance);
  $links = fetch_advance_links_by_entry_ids($pdo, [$advanceId]);
  $activeLinks = array_values(array_filter($links[$advanceId] ?? [], static function (array $row): bool {
    return ($row['status'] ?? '') === ADVANCE_LINK_STATUS_ACTIVE;
  }));
  $revenueSummary = [];
  if ($revenueId > 0) {
    $revenueSummary = $remainingRow ? [$revenueId => $remainingRow] : fetch_revenue_summaries($pdo, [$revenueId]);
  }

  json_ok([
    'endpoint' => 'petty-cash/advance_links',
    'message' => '已取消銷帳連結',
    'data' => [
      'advance_id' => $advanceId,
      'link_id' => $linkId,
      'remaining_amount' => $remaining,
      'links' => $activeLinks,
      'revenue' => $revenueId > 0 ? ($revenueSummary[$revenueId] ?? null) : null,
    ],
  ]);
}

function ensure_revenue_for_advance(
  PDO $pdo,
  array $advance,
  int $targetYear,
  int $targetMonth,
  string $customerCode,
  int $amount,
  string $note,
  int $preferredRevenueId = 0
): array {
  $revenue = null;
  if ($preferredRevenueId > 0) {
    $revenue = fetch_revenue_by_id($pdo, $preferredRevenueId);
    if (!$revenue) {
      json_err('指定的營收紀錄不存在', 404);
    }
    if ((int) $revenue['year'] !== $targetYear || (int) $revenue['month'] !== $targetMonth) {
      json_err('營收紀錄的月份與代墊款登記月不相符');
    }
    if (normalize_customer_code((string) $revenue['customer']) !== normalize_customer_code($customerCode)) {
      json_err('營收紀錄的客戶代號與代墊款不相符');
    }
  } else {
    $revenue = fetch_revenue_by_period($pdo, $targetYear, $targetMonth, $customerCode);
  }

  $customerName = fetch_customer_name($pdo, $customerCode);
  $displayName = $customerName !== '' ? $customerName : $customerCode;

  if ($revenue) {
    $updateParts = [
      'warehouse_fee = warehouse_fee + ?',
      'advance_total = advance_total + ?',
    ];
    $params = [$amount, $amount];

    if (should_update_customer_name($revenue['customer_name'] ?? '', $displayName, $customerCode)) {
      $updateParts[] = 'customer_name = ?';
      $params[] = $displayName;
    }

    $updateParts[] = 'updated_at = CURRENT_TIMESTAMP';
    $params[] = $revenue['id'];

    $stmt = $pdo->prepare('UPDATE sales_revenue SET ' . implode(', ', $updateParts) . ' WHERE id = ?');
    $stmt->execute($params);
    return fetch_revenue_by_id($pdo, (int) $revenue['id']);
  }

  // 建立新營收紀錄
  $customerName = $displayName;

  $insert = $pdo->prepare(
    'INSERT INTO sales_revenue
      (`year`, `month`, `customer`, `customer_name`, `freight`, `invoice_amount`, `tax`, `warehouse_fee`, `total`, `actual_received`, `advance_total`, `received_date`, `received_method`, `note`)
     VALUES
      (?, ?, ?, ?, 0, 0, 0, ?, 0, 0, ?, \'\', \'\', \'\')'
  );
  $insert->execute([
    $targetYear,
    $targetMonth,
    $customerCode,
    $customerName,
    $amount,
    $amount,
  ]);

  $newId = (int) $pdo->lastInsertId();
  return fetch_revenue_by_id($pdo, $newId);
}

function fetch_revenue_by_id(PDO $pdo, int $id): ?array {
  $stmt = $pdo->prepare(
    'SELECT id, year, month, customer, customer_name, freight, invoice_amount, tax, warehouse_fee, total, actual_received, advance_total, received_date, received_method, note
     FROM sales_revenue
     WHERE id = ?
     LIMIT 1'
  );
  $stmt->execute([$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    return null;
  }
  $row['id'] = (int) ($row['id'] ?? 0);
  $row['year'] = (int) ($row['year'] ?? 0);
  $row['month'] = (int) ($row['month'] ?? 0);
  $row['freight'] = (int) ($row['freight'] ?? 0);
  $row['invoice_amount'] = (int) ($row['invoice_amount'] ?? 0);
  $row['tax'] = (int) ($row['tax'] ?? 0);
  $row['warehouse_fee'] = (int) ($row['warehouse_fee'] ?? 0);
  $row['total'] = (int) ($row['total'] ?? 0);
  $row['actual_received'] = (int) ($row['actual_received'] ?? 0);
  $row['advance_total'] = (int) ($row['advance_total'] ?? 0);
  return $row;
}

function should_update_customer_name(string $current, string $next, string $customerCode): bool {
  $currentTrim = trim($current);
  $nextTrim = trim($next);
  if ($nextTrim === '') {
    return false;
  }
  if ($currentTrim === '') {
    return true;
  }
  if (normalize_customer_name($currentTrim) === normalize_customer_name($nextTrim)) {
    return false;
  }
  if ($customerCode !== '' && ($currentTrim === $customerCode || normalize_customer_code($currentTrim) === $customerCode)) {
    return true;
  }
  return false;
}

function should_delete_revenue_row(array $row): bool {
  return ($row['warehouse_fee'] ?? 0) <= 0
    && ($row['advance_total'] ?? 0) <= 0
    && ($row['freight'] ?? 0) <= 0
    && ($row['invoice_amount'] ?? 0) <= 0
    && ($row['tax'] ?? 0) <= 0
    && ($row['total'] ?? 0) <= 0
    && ($row['actual_received'] ?? 0) <= 0
    && trim((string) ($row['received_date'] ?? '')) === ''
    && trim((string) ($row['received_method'] ?? '')) === ''
    && trim((string) ($row['note'] ?? '')) === '';
}

function fetch_revenue_by_period(PDO $pdo, int $year, int $month, string $customerCode): ?array {
  $variants = [$customerCode];
  if (strpos($customerCode, '.0') === false) {
    $variants[] = $customerCode . '.0';
  }
  $conditions = array_fill(0, count($variants), 'customer = ?');
  $sql = 'SELECT id, year, month, customer, customer_name, freight, invoice_amount, tax, warehouse_fee, total, actual_received, advance_total, received_date, received_method, note
          FROM sales_revenue
          WHERE year = ? AND month = ? AND (' . implode(' OR ', $conditions) . ')
          LIMIT 1';
  $params = array_merge([$year, $month], $variants);
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    return null;
  }
  $row['id'] = (int) ($row['id'] ?? 0);
  $row['year'] = (int) ($row['year'] ?? 0);
  $row['month'] = (int) ($row['month'] ?? 0);
  $row['freight'] = (int) ($row['freight'] ?? 0);
  $row['invoice_amount'] = (int) ($row['invoice_amount'] ?? 0);
  $row['tax'] = (int) ($row['tax'] ?? 0);
  $row['warehouse_fee'] = (int) ($row['warehouse_fee'] ?? 0);
  $row['total'] = (int) ($row['total'] ?? 0);
  $row['actual_received'] = (int) ($row['actual_received'] ?? 0);
  $row['advance_total'] = (int) ($row['advance_total'] ?? 0);
  return $row;
}

function fetch_customer_name(PDO $pdo, string $code): string {
  $stmt = $pdo->prepare('SELECT `name` FROM `customers` WHERE `code` = ? LIMIT 1');
  $stmt->execute([$code]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row && isset($row['name'])) {
    $name = sales_normalize_text($row['name'], 150);
    if ($name !== '') {
      return $name;
    }
  }
  return '';
}
