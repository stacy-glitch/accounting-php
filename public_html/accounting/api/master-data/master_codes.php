<?php
declare(strict_types=1);

require_once __DIR__ . '/_utils.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = pdo();

try {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $action = md_get_action();

  if ($method !== 'GET') {
    json_err('Method not allowed', 405);
  }

  if ($action === '' || $action === 'list') {
    list_master_codes($pdo);
    return;
  }

  json_err('Unsupported action', 400);
} catch (InvalidArgumentException $e) {
  json_err($e->getMessage(), 400);
} catch (Throwable $e) {
  json_err($e->getMessage(), 500);
}

function list_master_codes(PDO $pdo): void {
  $sources = [
    'customers' => fetch_customers($pdo),
    'vehicles' => fetch_vehicles($pdo),
    'employees' => fetch_employees($pdo),
  ];

  $map = [];

  foreach ($sources as $type => $items) {
    foreach ($items as $item) {
      $code = normalize_code(strval($item['code'] ?? ''));
      if ($code === '') {
        continue;
      }

      $label = build_label($type, $item);
      if ($label === '') {
        continue;
      }

      if (!isset($map[$code])) {
        $map[$code] = [
          'code' => $item['code'],
          'value' => $item['code'],
          'normalized_code' => $code,
          'label' => $label,
          'name' => $item['name'] ?? '',
          'sources' => [],
        ];
      } else {
        $currentLabel = trim((string) $map[$code]['label']);
        if ($currentLabel === '' && $label !== '') {
          $map[$code]['label'] = $label;
        }
        if (($map[$code]['name'] ?? '') === '' && ($item['name'] ?? '') !== '') {
          $map[$code]['name'] = $item['name'];
        }
      }

      $map[$code]['sources'][] = [
        'type' => $type,
        'raw' => $item,
      ];
    }
  }

  $records = array_values($map);

  usort($records, static function (array $a, array $b): int {
    $aCode = trim(strval($a['code']));
    $bCode = trim(strval($b['code']));

    $aNum = preg_match('/^\d+$/', $aCode) ? (int) $aCode : null;
    $bNum = preg_match('/^\d+$/', $bCode) ? (int) $bCode : null;

    if ($aNum !== null && $bNum !== null) {
      return $aNum <=> $bNum;
    }

    return strcasecmp($aCode, $bCode);
  });

  json_ok([
    'endpoint' => 'master_codes',
    'count' => count($records),
    'data' => $records,
  ]);
}

function fetch_customers(PDO $pdo): array {
  $orderClause = md_code_order_clause('code');
  $stmt = $pdo->query(
    "SELECT code, name FROM `customers` ORDER BY {$orderClause} LIMIT 500"
  );
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  return array_map(static function (array $row): array {
    return [
      'code' => trim((string) ($row['code'] ?? '')),
      'name' => trim((string) ($row['name'] ?? '')),
    ];
  }, $rows);
}

function fetch_vehicles(PDO $pdo): array {
  $orderClause = md_code_order_clause('code');
  $stmt = $pdo->query(
    "SELECT code, model, brand, driver, license FROM `vehicles` ORDER BY {$orderClause} LIMIT 500"
  );
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  return array_map(static function (array $row): array {
    return [
      'code' => trim((string) ($row['code'] ?? '')),
      'name' => trim((string) ($row['driver'] ?? '')),
      'license' => trim((string) ($row['license'] ?? '')),
      'model' => trim((string) ($row['model'] ?? '')),
      'brand' => trim((string) ($row['brand'] ?? '')),
      'driver' => trim((string) ($row['driver'] ?? '')),
    ];
  }, $rows);
}

function fetch_employees(PDO $pdo): array {
  $orderClause = md_code_order_clause('code');
  $stmt = $pdo->query(
    "SELECT code, name FROM `employees` ORDER BY {$orderClause} LIMIT 500"
  );
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  return array_map(static function (array $row): array {
    return [
      'code' => trim((string) ($row['code'] ?? '')),
      'name' => trim((string) ($row['name'] ?? '')),
    ];
  }, $rows);
}

function normalize_code(string $code): string {
  $trimmed = trim($code);
  if ($trimmed === '') {
    return '';
  }

  $normalized = ltrim($trimmed, '0');
  if ($normalized === '') {
    return '0';
  }

  return strtoupper($normalized);
}

function build_label(string $type, array $item): string {
  $code = trim((string) ($item['code'] ?? ''));
  $name = trim((string) ($item['name'] ?? ''));

  switch ($type) {
    case 'customers':
      return $name !== '' ? $name : '';
    case 'employees':
      return $name !== '' ? $name : '';
    case 'vehicles':
      $parts = array_filter([
        trim((string) ($item['license'] ?? '')),
        trim((string) ($item['driver'] ?? '')),
        trim((string) ($item['model'] ?? '')),
      ], static function ($part): bool {
        return $part !== '';
      });
      if (!empty($parts)) {
        return implode(' / ', $parts);
      }
      return $name !== '' ? $name : '';
    default:
      return $name !== '' ? $name : $code;
  }
}
