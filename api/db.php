<?php
// /public_html/accounting/api/db.php
// Database connection helper using PDO (UTF-8, persistent connection optional)

define('DB_HOST', 'localhost');
define('DB_NAME', 'judacarg_web');
define('DB_USER', 'judacarg');
define('DB_PASS', 'REPLACE_WITH_DB_PASSWORD'); // ← 在部署前改成實際資料庫密碼

define('DB_DSN', 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4');

function get_db_connection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => '資料庫連線失敗']);
            exit;
        }
    }
    return $pdo;
}
