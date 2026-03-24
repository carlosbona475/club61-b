<?php
/**
 * Pedidos de mensagem (message_requests) — REST com service role.
 */

declare(strict_types=1);

require_once __DIR__ . '/supabase.php';

function mr_service_available(): bool
{
    return defined('SUPABASE_URL') && defined('SUPABASE_SERVICE_KEY') && SUPABASE_SERVICE_KEY !== '';
}

/**
 * Retorna a linha do par (qualquer direção) ou null.
 *
 * @return array{status:string,from_user:string,to_user:string,id?:string}|null
 */
function mr_fetch_pair_direct(string $from, string $to): ?array
{
    $url = SUPABASE_URL . '/rest/v1/message_requests?from_user=eq.' . urlencode($from)
        . '&to_user=eq.' . urlencode($to) . '&select=id,from_user,to_user,status&limit=1';
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
    $raw = curl_exec($ch);
    curl_close($ch);
    $rows = json_decode((string) $raw, true);
    if (!is_array($rows) || $rows === []) {
        return null;
    }

    return isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
}

function mr_find_pair_row(string $userA, string $userB): ?array
{
    if (!mr_service_available() || $userA === '' || $userB === '' || $userA === $userB) {
        return null;
    }
    $r = mr_fetch_pair_direct($userA, $userB);
    if ($r !== null) {
        return $r;
    }

    return mr_fetch_pair_direct($userB, $userA);
}

function mr_can_open_dm(string $currentUserId, string $otherUserId): bool
{
    $row = mr_find_pair_row($currentUserId, $otherUserId);
    if ($row === null) {
        return false;
    }

    return isset($row['status']) && (string) $row['status'] === 'accepted';
}

/**
 * Label para botão no perfil: request | pending | accepted | rejected
 */
function mr_profile_button_state(string $viewerId, string $profileUserId): string
{
    if ($viewerId === '' || $profileUserId === '' || $viewerId === $profileUserId) {
        return 'hidden';
    }
    $row = mr_find_pair_row($viewerId, $profileUserId);
    if ($row === null) {
        return 'request';
    }
    $st = (string) ($row['status'] ?? '');
    $from = (string) ($row['from_user'] ?? '');
    if ($st === 'accepted') {
        return 'accepted';
    }
    if ($st === 'pending') {
        return $from === $viewerId ? 'pending_sent' : 'pending_inbox';
    }
    if ($st === 'rejected') {
        return 'request';
    }

    return 'request';
}
