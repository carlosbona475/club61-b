<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/security_headers.php';
require_once __DIR__ . '/../../config/session.php';

club61_security_headers();
club61_session_start_safe();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: /features/auth/login.php');
exit;
