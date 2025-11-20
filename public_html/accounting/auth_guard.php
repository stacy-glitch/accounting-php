<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
  return;
}

require_once __DIR__ . '/auth.php';

auth_bootstrap_session();

if (auth_is_login_route()) {
  return;
}

$user = auth_user();
if (!$user) {
  auth_handle_unauthorized();
}

if (!auth_is_api_request()) {
  ob_start(static function ($buffer) {
    if (stripos($buffer, 'layout__logout') !== false) {
      return $buffer;
    }
    $logout = '<a class="layout__logout" href="/accounting/logout.php" data-logout-button="true" style="position:fixed;top:16px;right:16px;background:#d9a07d;color:#fff6f0;padding:8px 18px;border-radius:20px;text-decoration:none;z-index:9999;letter-spacing:2px;">登出</a>';
    if (stripos($buffer, '</body>') !== false) {
      return preg_replace('/<\/body>/i', $logout . '</body>', $buffer, 1);
    }
    return $buffer . $logout;
  });
  register_shutdown_function(static function () {
    if (ob_get_level() > 0) {
      ob_end_flush();
    }
  });
}
