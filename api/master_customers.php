<?php
require __DIR__.'/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$db = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query('SELECT code, name, tax_id, contact, phone FROM customers ORDER BY code');
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [];

    if (!empty($_FILES)) {
        require_once __DIR__.'/util.php';
        try {
            $rows = parse_csv_upload($_FILES[array_key_first($_FILES)], ['code', 'name', 'tax_id', 'contact', 'phone']);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit;
        }
        foreach ($rows as $row) {
            $payload[] = [
                'code' => $row['code'] ?? '',
                'name' => $row['name'] ?? '',
                'tax_id' => $row['tax_id'] ?? '',
                'contact' => $row['contact'] ?? '',
                'phone' => $row['phone'] ?? '',
            ];
        }
    } else {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload']);
            exit;
        }
        foreach ($data as $row) {
            $payload[] = [
                'code' => $row['code'] ?? '',
                'name' => $row['name'] ?? '',
                'tax_id' => $row['tax_id'] ?? '',
                'contact' => $row['contact'] ?? '',
                'phone' => $row['phone'] ?? '',
            ];
        }
    }

    $db->beginTransaction();
    $db->exec('TRUNCATE TABLE customers');
    $stmt = $db->prepare('INSERT INTO customers (code, name, tax_id, contact, phone) VALUES (?, ?, ?, ?, ?)');
    foreach ($payload as $row) {
        $stmt->execute([
            $row['code'],
            $row['name'],
            $row['tax_id'],
            $row['contact'],
            $row['phone'],
        ]);
    }
    $db->commit();
    echo json_encode(['ok' => true, 'count' => count($payload)]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
