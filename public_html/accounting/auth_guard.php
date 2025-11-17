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
