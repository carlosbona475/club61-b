<?php
/**
 * Admin / role guard — Club61
 *
 * - isCurrentUserAdmin(): perfil atual via service key (cache por request)
 * - admin_invalidate_profile_cache(): após login / mutação de perfil
 * - admin_guard_profile_or_logout(): uso em páginas admin — perfil inexistente encerra sessão
 */

declare(strict_types=1);

require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/session.php';

/**
 * Busca uma linha em profiles (service role). Uma chamada HTTP por uid.
 *
 * @return array<string, mixed>|null
 */
function admin_fetch_profile_row_by_id(string $userId): ?array
{
    $userId = trim($userId);
    if ($userId === '' || !defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
        return null;
    }

    $url = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId) . '&select=id,role,status';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $statusCode < 200 || $statusCode >= 300) {
        return null;
    }

    $rows = json_decode((string) $response, true);
    if (!is_array($rows) || $rows === []) {
        return null;
    }

    return $rows[0];
}

/**
 * Perfil do usuário logado (cache em $GLOBALS por uid de sessão).
 *
 * @return array<string, mixed>|null
 */
function admin_get_current_profile_row(): ?array
{
    club61_session_start_safe();

    $uid = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : '';
    if ($uid === '') {
        return null;
    }

    if (
        isset($GLOBALS['_club61_profile_cache'])
        && is_array($GLOBALS['_club61_profile_cache'])
        && (string) ($GLOBALS['_club61_profile_cache']['uid'] ?? '') === $uid
    ) {
        return $GLOBALS['_club61_profile_cache']['row'];
    }

    $row = admin_fetch_profile_row_by_id($uid);
    $GLOBALS['_club61_profile_cache'] = ['uid' => $uid, 'row' => $row];

    return $row;
}

function admin_invalidate_profile_cache(): void
{
    unset($GLOBALS['_club61_profile_cache']);
}

/**
 * True apenas se role === 'admin' e não estiver banido (status === 'banned').
 */
function isCurrentUserAdmin(): bool
{
    $row = admin_get_current_profile_row();
    if ($row === null) {
        return false;
    }

    $role = strtolower(trim((string) ($row['role'] ?? '')));
    $status = trim((string) ($row['status'] ?? ''));

    if ($status === 'banned') {
        return false;
    }

    // Administradores (aceita legado 'Admin' / espaços; não confundir com 'member' / 'membro')
    return $role === 'admin';
}

/**
 * Para /features/admin/*: exige sessão com user_id; se não existir linha em profiles, encerra sessão.
 */
function admin_guard_profile_or_logout(): void
{
    club61_session_start_safe();

    if (empty($_SESSION['user_id'])) {
        header('Location: /features/auth/login.php');
        exit;
    }

    $row = admin_get_current_profile_row();
    if ($row === null) {
        admin_invalidate_profile_cache();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: /features/auth/login.php?status=error&message=' . urlencode('Perfil não encontrado. Faça login novamente.'));
        exit;
    }
}
