<?php
require_once __DIR__ . '/../_helpers.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = pdo();
  $tables = [
    ['table' => 'customers', 'column' => 'code', 'index' => 'uniq_customers_code'],
    ['table' => 'vehicles', 'column' => 'code', 'index' => 'uniq_vehicles_code'],
    ['table' => 'employees', 'column' => 'code', 'index' => 'uniq_employees_code'],
  ];

  $results = [];

  foreach ($tables as $item) {
    $table = $item['table'];
    $column = $item['column'];
    $index = $item['index'];

    normalize_table_codes($pdo, $table, $column);
    $deleted = dedupe_table($pdo, $table, $column);
    $indexStatus = ensure_unique_index($pdo, $table, $column, $index);

    $results[] = [
      'table' => $table,
      'column' => $column,
      'removed' => $deleted,
      'index_status' => $indexStatus,
    ];
  }

  json_ok(['results' => $results]);
} catch (Throwable $e) {
  json_err('dedupe failed: ' . $e->getMessage(), 500);
}

function dedupe_table(PDO $pdo, string $table, string $column): int
{
  $sql = sprintf(
    'DELETE t1 FROM `%1$s` AS t1 INNER JOIN `%1$s` AS t2 ON t1.`%2$s` = t2.`%2$s` AND t1.id > t2.id',
    str_replace('`', '``', $table),
    str_replace('`', '``', $column)
  );
  return (int) $pdo->exec($sql);
}

function normalize_table_codes(PDO $pdo, string $table, string $column): void
{
  $tableName = str_replace('`', '``', $table);
  $columnName = str_replace('`', '``', $column);

  // Trim whitespace
  $pdo->exec(sprintf(
    'UPDATE `%1$s` SET `%2$s` = TRIM(`%2$s`) WHERE `%2$s` <> TRIM(`%2$s`)',
    $tableName,
    $columnName
  ));

  // Remove decimal part
  $pdo->exec(sprintf(
    'UPDATE `%1$s` SET `%2$s` = SUBSTRING_INDEX(`%2$s`, \'.\', 1) WHERE `%2$s` LIKE \'%%.%%\'',
    $tableName,
    $columnName
  ));

  // Replace empty with 0
  $pdo->exec(sprintf(
    'UPDATE `%1$s` SET `%2$s` = \'0\' WHERE `%2$s` = \'\' OR `%2$s` IS NULL',
    $tableName,
    $columnName
  ));

  // Pad to 3 digits if purely numeric and shorter than 3
  $pdo->exec(sprintf(
    'UPDATE `%1$s` SET `%2$s` = LPAD(`%2$s`, 3, \'0\') WHERE `%2$s` REGEXP \'^[0-9]{1,2}$\'',
    $tableName,
    $columnName
  ));
}

function ensure_unique_index(PDO $pdo, string $table, string $column, string $index): string
{
  try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $sql = sprintf(
      'ALTER TABLE `%1$s` ADD UNIQUE INDEX `%3$s`(`%2$s`)',
      str_replace('`', '``', $table),
      str_replace('`', '``', $column),
      str_replace('`', '``', $index)
    );
    $pdo->exec($sql);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    return 'created';
  } catch (PDOException $e) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $code = (int) $e->getCode();
    if ($code === 1061 || strpos($e->getMessage(), 'duplicate key name') !== false) {
      return 'exists';
    }
    if ($code === 1062 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
      return 'duplicate_values';
    }
    return 'error: ' . $e->getMessage();
  }
  $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}
