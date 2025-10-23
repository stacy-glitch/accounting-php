<?php
require_once __DIR__ . '/../_helpers.php';

function md_payload(): array {
  static $cache = null;
  if ($cache !== null) {
    return $cache;
  }

  $raw = file_get_contents('php://input');
  if ($raw !== false && $raw !== '') {
    $json = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
      return $cache = array_map('md_trim_value', $json);
    }
    parse_str($raw, $parsed);
    if (is_array($parsed)) {
      return $cache = array_map('md_trim_value', $parsed);
    }
  }

  if (!empty($_POST)) {
    return $cache = array_map('md_trim_value', $_POST);
  }

  return $cache = [];
}

function md_trim_value($value) {
  if (is_string($value)) {
    return trim($value);
  }
  if ($value === null) {
    return '';
  }
  return $value;
}

function md_map_fields(array $source, array $fields): array {
  $result = [];
  foreach ($fields as $field) {
    $result[$field] = md_trim_value($source[$field] ?? '');
  }
  return $result;
}

function md_require_fields(array $values, array $labels): void {
  foreach ($labels as $key => $label) {
    if (!array_key_exists($key, $values) || $values[$key] === '') {
      json_err($label . '為必填');
    }
  }
}

function md_max_length(string $value, int $max, string $label): void {
  if ($value === '') {
    return;
  }
  if (mb_strlen($value) > $max) {
    json_err(sprintf('%s長度不可超過 %d 字元', $label, $max));
  }
}

function md_quote_identifier(string $identifier): string {
  if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
    throw new InvalidArgumentException('Invalid identifier');
  }
  return sprintf('`%s`', $identifier);
}

function md_get_action(): string {
  $payload = md_payload();
  $action = $payload['action'] ?? ($_REQUEST['action'] ?? '');
  return strtolower(trim((string)$action));
}

function md_get_id(array $payload = []): int {
  if (isset($payload['id'])) {
    $id = (int)$payload['id'];
  } elseif (isset($_REQUEST['id'])) {
    $id = (int)$_REQUEST['id'];
  } else {
    $payload = md_payload();
    $id = isset($payload['id']) ? (int)$payload['id'] : 0;
  }
  if ($id <= 0) {
    json_err('缺少有效的 id 參數');
  }
  return $id;
}

function md_assert_unique(PDO $pdo, string $table, string $column, string $value, ?int $ignoreId, string $message): void {
  $tableSql = md_quote_identifier($table);
  $columnSql = md_quote_identifier($column);
  $sql = "SELECT id FROM {$tableSql} WHERE {$columnSql} = ?" . ($ignoreId ? ' AND id <> ?' : '') . ' LIMIT 1';
  $params = [$value];
  if ($ignoreId) {
    $params[] = $ignoreId;
  }
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  if ($stmt->fetchColumn()) {
    json_err($message);
  }
}

function md_assert_unique_combo(PDO $pdo, string $table, array $columns, array $values, ?int $ignoreId, string $message): void {
  if (count($columns) !== count($values)) {
    json_err('unique 條件設定有誤', 500);
  }
  $tableSql = md_quote_identifier($table);
  $conditions = [];
  $params = [];
  foreach ($columns as $column) {
    $conditions[] = sprintf('%s = ?', md_quote_identifier($column));
    $params[] = $values[$column] ?? '';
  }
  $sql = sprintf(
    'SELECT id FROM %s WHERE %s%s LIMIT 1',
    $tableSql,
    implode(' AND ', $conditions),
    $ignoreId ? ' AND id <> ?' : ''
  );
  if ($ignoreId) {
    $params[] = $ignoreId;
  }
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  if ($stmt->fetchColumn()) {
    json_err($message);
  }
}

function md_fetch_by_id(PDO $pdo, string $table, array $columns, int $id): ?array {
  $tableSql = md_quote_identifier($table);
  $columnSql = implode(', ', array_map('md_quote_identifier', $columns));
  $stmt = $pdo->prepare(sprintf('SELECT %s FROM %s WHERE id = ? LIMIT 1', $columnSql, $tableSql));
  $stmt->execute([$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row !== false ? $row : null;
}
