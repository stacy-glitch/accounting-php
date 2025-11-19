<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

auth_bootstrap_session();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';
  $user = auth_attempt_login((string) $username, (string) $password);
  if ($user) {
    auth_set_session($user);
    $redirect = '/accounting/sales/mega-bank.php';
    if (!empty($_GET['return'])) {
      $candidate = filter_var((string) $_GET['return'], FILTER_SANITIZE_URL);
      if ($candidate && strpos($candidate, '/accounting/') === 0) {
        $redirect = $candidate;
      }
    }
    header('Location: ' . $redirect);
    exit;
  }
  $error = '帳號或密碼錯誤';
}

?><!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>足達貨運 | 登入</title>
  <link rel="stylesheet" href="/accounting/assets/css/admin.css?v=20251228">
  <style>
    body {
      margin: 0;
      font-family: 'PingFang TC', 'Microsoft JhengHei', sans-serif;
      background: #202127;
      color: #fff;
    }
    .login-layout {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 32px 16px;
    }
    .login-brand {
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 24px;
    }
    .login-brand__logo {
      width: 64px;
      height: 46px;
      background: url('https://cdn.jsdelivr.net/gh/peiyinglin/assets/judacargo-truck.png') no-repeat center/contain;
    }
    .login-brand__title {
      font-size: 40px;
      font-weight: 600;
      color: #e86d4a;
      letter-spacing: 4px;
    }
    .login-card {
      width: 100%;
      max-width: 420px;
      background: #f5f5f5;
      border-radius: 12px;
      padding: 36px;
      box-shadow: 0 12px 32px rgba(0,0,0,0.35);
    }
    .login-card h1 {
      text-align: center;
      margin: 0 0 24px;
      color: #0f3057;
      font-size: 22px;
      letter-spacing: 2px;
    }
    .login-field {
      margin-bottom: 18px;
    }
    .login-field label {
      display: block;
      margin-bottom: 6px;
      color: #425466;
      font-size: 14px;
    }
    .login-field input {
      width: 100%;
      border: 1px solid #ccd5e0;
      border-radius: 6px;
      padding: 10px 12px;
      font-size: 15px;
      background: #fff;
      outline: none;
    }
    .login-field input:focus {
      border-color: #3385ff;
      box-shadow: 0 0 0 3px rgba(51,133,255,0.15);
    }
    .login-error {
      text-align: center;
      color: #d64541;
      margin-bottom: 18px;
    }
    .login-actions {
      margin-top: 24px;
    }
    .btn-login {
      width: 100%;
      border: none;
      background: #2f6f92;
      color: #fff;
      font-size: 15px;
      padding: 12px 0;
      border-radius: 6px;
      cursor: pointer;
      letter-spacing: 2px;
    }
    .btn-login:hover {
      background: #22516a;
    }
    .login-footer {
      margin-top: 16px;
      text-align: center;
      font-size: 14px;
      color: #e86d4a;
    }
  </style>
</head>
<body>
  <div class="login-layout">
    <div class="login-brand">
      <div class="login-brand__logo" aria-hidden="true"></div>
      <div class="login-brand__title">足達貨運</div>
    </div>
    <div class="login-card">
      <h1>登入系統</h1>
      <?php if ($error): ?>
        <p class="login-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>
      <form method="post" autocomplete="off">
        <div class="login-field">
          <label for="username">帳號</label>
          <input id="username" name="username" type="text" required>
        </div>
        <div class="login-field">
          <label for="password">密碼</label>
          <input id="password" name="password" type="password" required>
        </div>
        <div class="login-actions">
          <button type="submit" class="btn-login">登入</button>
        </div>
      </form>
    </div>
    <div class="login-footer">
      公司電話：(02)2423-3615　傳真電話：(02)2429-3051
    </div>
  </div>
</body>
</html>
