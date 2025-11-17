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
  <title>登入 | Accounting</title>
  <link rel="stylesheet" href="/accounting/assets/css/admin.css?v=20251228">
  <style>
    .login {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f7f7f7;
      padding: 24px;
    }
    .login-card {
      width: 100%;
      max-width: 360px;
      background: #fff;
      border: 1px solid #e1e1e1;
      border-radius: 12px;
      box-shadow: 0 6px 12px rgba(0,0,0,0.08);
      padding: 28px 24px;
    }
    .login-card h1 {
      margin: 0 0 12px;
      font-size: 20px;
      text-align: center;
    }
    .login-card p.desc {
      margin: 0 0 16px;
      color: #666;
      font-size: 14px;
      text-align: center;
    }
    .login-field {
      margin-bottom: 14px;
    }
    .login-field label {
      display: block;
      margin-bottom: 6px;
      color: #333;
      font-size: 14px;
    }
    .login-field input {
      width: 100%;
      height: 38px;
      padding: 8px 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 14px;
    }
    .login-error {
      margin: 0 0 12px;
      color: #c0392b;
      font-size: 14px;
      text-align: center;
    }
    .login-actions {
      margin-top: 18px;
      display: flex;
      gap: 8px;
      justify-content: center;
    }
    .btn-login {
      min-width: 120px;
    }
  </style>
</head>
<body>
  <div class="login">
    <div class="login-card">
      <h1>會計系統登入</h1>
      <p class="desc">請輸入帳號與密碼</p>
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
          <button type="submit" class="btn btn--success btn-login">登入</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
