<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

auth_bootstrap_session();
auth_logout();

$target = '/accounting/login.php';
header('Location: ' . $target);
exit;
