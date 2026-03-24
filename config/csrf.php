<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

/**
 * CSRF global baseado em sessão (token por sessão).
 */
function csrf_token(): string
{
    club61_session_start_safe();
    if (!empty($_SESSION['_csrf']) && empty($_SESSION['csrf_token']) && is_string($_SESSION['_csrf'])) {
        $_SESSION['csrf_token'] = $_SESSION['_csrf'];
    }
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_validate(?string $token): bool
{
    club61_session_start_safe();
    $s = $_SESSION['csrf_token'] ?? ($_SESSION['_csrf'] ?? '');

    return is_string($token) && $s !== '' && hash_equals($s, $token);
}

/**
 * Gera novo token (após login ou rotação de sessão).
 */
function csrf_rotate(): void
{
    club61_session_start_safe();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['_csrf']);
}

/**
 * Campo hidden para formulários HTML.
 */
function csrf_field(): string
{
    $t = csrf_token();

    return '<input type="hidden" name="csrf" value="' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Valida POST['csrf'] e termina com redirecionamento ou 403 JSON.
 *
 * @param 'redirect'|'json' $onFail
 */
function csrf_require_post(string $redirectUrl = '/features/feed/index.php', string $onFail = 'redirect'): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $sent = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    if (csrf_validate($sent)) {
        return;
    }
    if ($onFail === 'json') {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(403);
        }
        echo json_encode(['ok' => false, 'error' => 'csrf'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $q = str_contains($redirectUrl, '?') ? '&' : '?';
    header('Location: ' . $redirectUrl . $q . 'status=error&message=' . rawurlencode('Sessão expirada. Atualize a página.'));
    exit;
}
