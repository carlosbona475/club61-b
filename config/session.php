<?php

declare(strict_types=1);

/**
 * Sessão com parâmetros de cookie alinhados a produção (HttpOnly, SameSite, Secure em HTTPS).
 */

function club61_request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

    return strtolower((string) $xfp) === 'https';
}

/** @var bool */
$GLOBALS['club61_session_bootstrapped'] = false;

/**
 * Deve ser chamado antes do primeiro session_start() em cada pedido.
 */
function club61_session_bootstrap(): void
{
    if (!empty($GLOBALS['club61_session_bootstrapped'])) {
        return;
    }
    $GLOBALS['club61_session_bootstrapped'] = true;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $secure = club61_request_is_https();

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function club61_session_start_safe(): void
{
    club61_session_bootstrap();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}
