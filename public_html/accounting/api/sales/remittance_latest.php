<?php
declare(strict_types=1);

require_once __DIR__ . '/../_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
  json_err('Method not allowed', 405);
}

$uploadsRoot = realpath(__DIR__ . '/../../uploads');
if ($uploadsRoot === false) {
  json_ok([
    'records' => [],
    'parse_error' => '',
    'filename' => '',
    'uploaded_at' => '',
  ]);
}

$latestPath = $uploadsRoot . '/remittance/latest.json';
if (!is_file($latestPath)) {
  json_ok([
    'records' => [],
    'parse_error' => '',
    'filename' => '',
    'uploaded_at' => '',
  ]);
}

$raw = file_get_contents($latestPath);
$data = json_decode($raw, true);
if (!is_array($data)) {
  $data = [];
}

json_ok([
  'records' => $data['records'] ?? [],
  'parse_error' => $data['parse_error'] ?? '',
  'filename' => $data['filename'] ?? '',
  'uploaded_at' => $data['uploaded_at'] ?? '',
]);
