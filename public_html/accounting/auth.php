<?php
declare(strict_types=1);

require_once __DIR__ . '/api/_helpers.php';

const AUTH_SESSION_NAME = 'acct_auth';
const AUTH_IDLE_TIMEOUT = 600; // 10 分鐘

function auth_bootstrap_session(): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_name(AUTH_SESSION_NAME);
    session_set_cookie_params([
      'lifetime' => 0,
      'path' => '/',
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
    session_start();
  }
}

function auth_pdo(): PDO {
  return pdo();
}

function auth_ensure_users_table(PDO $pdo): void {
  static $initialized = false;
  if ($initialized) {
    return;
  }
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS `users` (
      `id` int unsigned NOT NULL AUTO_INCREMENT,
      `username` varchar(64) NOT NULL,
      `password_hash` varchar(255) NOT NULL,
      `display_name` varchar(100) NOT NULL,
      `role` varchar(32) NOT NULL DEFAULT 'admin',
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  $defaults = [
    ['username' => 'Stacy', 'display_name' => 'Stacy', 'hash' => '$2y$10$OhRutu5Ksbxu.UV4k8/USuvE3C4haQOhI/5nK1RJXrinI.9SRlosG'],
    ['username' => '簡晨芸', 'display_name' => '簡晨芸', 'hash' => '$2y$10$4FLQOZ2ZuyoCW08YRE4f5uxn6J1VL0k2j4ZMpVz84N9cIBGwVN8KK'],
  ];
  $stmt = $pdo->prepare("
    INSERT INTO `users` (`username`, `password_hash`, `display_name`, `role`)
    VALUES (:username, :hash, :display_name, 'admin')
    ON DUPLICATE KEY UPDATE
      `password_hash` = VALUES(`password_hash`),
      `display_name` = VALUES(`display_name`)
  ");
  foreach ($defaults as $user) {
    $stmt->execute([
      ':username' => $user['username'],
      ':hash' => $user['hash'],
      ':display_name' => $user['display_name'],
    ]);
  }
  $initialized = true;
}

function auth_attempt_login(string $username, string $password): ?array {
  $username = trim($username);
  if ($username === '' || $password === '') {
    return null;
  }
  $pdo = auth_pdo();
  auth_ensure_users_table($pdo);

  $stmt = $pdo->prepare('SELECT id, username, display_name, role, password_hash FROM users WHERE username = :username LIMIT 1');
  $stmt->execute([':username' => $username]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$user || !password_verify($password, (string) $user['password_hash'])) {
    return null;
  }
  return [
    'id' => (int) $user['id'],
    'username' => (string) $user['username'],
    'display_name' => (string) $user['display_name'],
    'role' => (string) $user['role'],
  ];
}

function auth_set_session(array $user): void {
  $_SESSION['user'] = $user;
  $_SESSION['last_activity'] = time();
}

function auth_user(): ?array {
  if (empty($_SESSION['user']) || !is_array($_SESSION['user'])) {
    return null;
  }
  if (!isset($_SESSION['last_activity'])) {
    return null;
  }
  if ((time() - (int) $_SESSION['last_activity']) > AUTH_IDLE_TIMEOUT) {
    auth_logout(false);
    return null;
  }
  $_SESSION['last_activity'] = time();
  return $_SESSION['user'];
}

function auth_logout(bool $destroySession = true): void {
  $_SESSION = [];
  if ($destroySession && session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
  }
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
}

function auth_is_api_request(): bool {
  $script = $_SERVER['SCRIPT_NAME'] ?? '';
  return strpos($script, '/api/') !== false;
}

function auth_is_login_route(): bool {
  $script = $_SERVER['SCRIPT_NAME'] ?? '';
  $basename = basename($script);
  return $basename === 'login.php' || $basename === 'logout.php';
}

function auth_handle_unauthorized(): void {
  if (auth_is_api_request()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => '未登入或登入逾時', 'ts' => date('c')], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $target = '/accounting/login.php';
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  if ($uri && strpos($uri, 'login.php') === false) {
    $target .= '?return=' . urlencode($uri);
  }
  header('Location: ' . $target);
  exit;
}
