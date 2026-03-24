<?php

declare(strict_types=1);

/**
 * Cabeçalhos HTTP de segurança (defesa em profundidade; complementar a .htaccess).
 */
function club61_security_headers(): void
{
    static $sent = false;
    if ($sent || headers_sent()) {
        return;
    }
    $sent = true;

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: accelerometer=(), camera=(), microphone=(), geolocation=(), usb=(), payment=()');
    header('X-XSS-Protection: 0');
    // Não bloqueia scripts inline existentes; restringe framing, base e destino de formulários.
    header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}
