<?php
/**
 * Seguidores — tabela `followers` (follower_id → following_id).
 * Requer SUPABASE_SERVICE_KEY para contagens e verificações em servidor.
 */

declare(strict_types=1);

require_once __DIR__ . '/supabase.php';

function followers_service_ok(): bool
{
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
        return false;
    }

    return function_exists('club61_supabase_jwt_role')
        && club61_supabase_jwt_role((string) SUPABASE_SERVICE_KEY) === 'service_role';
}

/**
 * Quantos perfis o utilizador segue (linhas onde follower_id = user).
 */
function getFollowingCount(string $userId): int
{
    if ($userId === '' || !followers_service_ok()) {
        return 0;
    }
    $url = SUPABASE_URL . '/rest/v1/followers?follower_id=eq.' . urlencode($userId) . '&select=id';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Accept: application/json',
            'Prefer: count=exact',
            'Range: 0-0',
        ],
    ]);
    $resp = curl_exec($ch);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $hs = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $hs < 200 || $hs >= 300) {
        return 0;
    }
    $headers = substr($resp, 0, $headerSize);
    if (preg_match('/content-range:\s*[^/]*\/(\d+)/i', $headers, $m)) {
        return (int) $m[1];
    }

    return 0;
}

/**
 * Número de seguidores do utilizador (linhas onde following_id = user).
 */
function getFollowersCount(string $userId): int
{
    if ($userId === '' || !followers_service_ok()) {
        return 0;
    }
    $url = SUPABASE_URL . '/rest/v1/followers?following_id=eq.' . urlencode($userId) . '&select=id';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Accept: application/json',
            'Prefer: count=exact',
            'Range: 0-0',
        ],
    ]);
    $resp = curl_exec($ch);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $hs = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $hs < 200 || $hs >= 300) {
        return 0;
    }
    $headers = substr($resp, 0, $headerSize);
    if (preg_match('/content-range:\s*[^/]*\/(\d+)/i', $headers, $m)) {
        return (int) $m[1];
    }

    return 0;
}

/**
 * O utilizador $followerId segue $followingId?
 */
function followers_is_following(string $followerId, string $followingId): bool
{
    if ($followerId === '' || $followingId === '' || $followerId === $followingId || !followers_service_ok()) {
        return false;
    }
    $url = SUPABASE_URL . '/rest/v1/followers?follower_id=eq.' . urlencode($followerId)
        . '&following_id=eq.' . urlencode($followingId) . '&select=id&limit=1';
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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return false;
    }
    $rows = json_decode((string) $raw, true);

    return is_array($rows) && $rows !== [];
}

/**
 * Conjunto de ids que $followerId segue (para UI em lote).
 *
 * @return array<string, true>
 */
function followers_get_following_id_set(string $followerId): array
{
    $out = [];
    if ($followerId === '' || !followers_service_ok()) {
        return $out;
    }
    $url = SUPABASE_URL . '/rest/v1/followers?follower_id=eq.' . urlencode($followerId) . '&select=following_id&limit=1000';
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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return $out;
    }
    $rows = json_decode((string) $raw, true);
    if (!is_array($rows)) {
        return $out;
    }
    foreach ($rows as $r) {
        if (!empty($r['following_id'])) {
            $out[(string) $r['following_id']] = true;
        }
    }

    return $out;
}
