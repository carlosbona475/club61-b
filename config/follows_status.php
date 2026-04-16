<?php

/**
 * Seguir com aprovação — tabela `follows` (status: pendente | aceito | recusado).
 */

declare(strict_types=1);

require_once __DIR__ . '/supabase.php';

function club61_follows_service_ok(): bool
{
    return defined('SUPABASE_URL') && defined('SUPABASE_SERVICE_KEY') && SUPABASE_SERVICE_KEY !== ''
        && function_exists('club61_supabase_jwt_role')
        && club61_supabase_jwt_role((string) SUPABASE_SERVICE_KEY) === 'service_role';
}

function club61_follows_headers(bool $json = false): array
{
    $h = [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Accept: application/json',
    ];
    if ($json) {
        $h[] = 'Content-Type: application/json';
    }

    return $h;
}

/** Contagem exata via Content-Range */
function club61_follows_count_exact(string $filter): int
{
    if (!club61_follows_service_ok()) {
        return 0;
    }
    $url = SUPABASE_URL . '/rest/v1/follows?select=id&' . $filter;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge(club61_follows_headers(false), [
            'Prefer: count=exact',
            'Range: 0-0',
        ]),
    ]);
    $resp = curl_exec($ch);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if ($resp === false || $hs <= 0) {
        return 0;
    }
    $headers = substr($resp, 0, (int) $hs);
    if (preg_match('/content-range:\s*[^/]*\/(\d+)/i', $headers, $m)) {
        return (int) $m[1];
    }

    return 0;
}

function club61_follows_count_followers_accepted(string $profileId): int
{
    if ($profileId === '') {
        return 0;
    }

    return club61_follows_count_exact(
        'following_id=eq.' . urlencode($profileId) . '&status=eq.aceito'
    );
}

function club61_follows_count_following_accepted(string $profileId): int
{
    if ($profileId === '') {
        return 0;
    }

    return club61_follows_count_exact(
        'follower_id=eq.' . urlencode($profileId) . '&status=eq.aceito'
    );
}

/**
 * Estado da relação viewer → target (para UI do botão).
 *
 * @return 'none'|'pendente'|'aceito'|'recusado'
 */
function club61_follows_relation_state(string $viewerId, string $targetId): string
{
    if ($viewerId === '' || $targetId === '' || $viewerId === $targetId || !club61_follows_service_ok()) {
        return 'none';
    }
    $url = SUPABASE_URL . '/rest/v1/follows?follower_id=eq.' . urlencode($viewerId)
        . '&following_id=eq.' . urlencode($targetId) . '&select=status&limit=1';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => club61_follows_headers(false),
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $rows = json_decode((string) $raw, true);
    if (!is_array($rows) || $rows === []) {
        return 'none';
    }
    $s = isset($rows[0]['status']) ? (string) $rows[0]['status'] : 'none';

    return in_array($s, ['pendente', 'aceito', 'recusado'], true) ? $s : 'none';
}

function club61_follows_pending_incoming_count(string $userId): int
{
    if ($userId === '') {
        return 0;
    }

    return club61_follows_count_exact(
        'following_id=eq.' . urlencode($userId) . '&status=eq.pendente'
    );
}

/**
 * @return list<array{follower_id:string, created_at:?string}>
 */
function club61_follows_pending_incoming_list(string $userId, int $limit = 50): array
{
    if ($userId === '' || !club61_follows_service_ok()) {
        return [];
    }
    $url = SUPABASE_URL . '/rest/v1/follows?following_id=eq.' . urlencode($userId)
        . '&status=eq.pendente&select=follower_id,created_at&order=created_at.desc&limit=' . max(1, min(100, $limit));
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => club61_follows_headers(false),
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $rows = json_decode((string) $raw, true);

    return is_array($rows) ? $rows : [];
}

/**
 * @return array{ok:bool, error?:string, followers_count?:int}
 */
function club61_follows_enviar(string $followerId, string $followingId): array
{
    if ($followerId === '' || $followingId === '' || $followerId === $followingId || !club61_follows_service_ok()) {
        return ['ok' => false, 'error' => 'invalid'];
    }
    $st = club61_follows_relation_state($followerId, $followingId);
    if ($st === 'aceito' || $st === 'pendente') {
        return [
            'ok' => true,
            'followers_count' => club61_follows_count_followers_accepted($followingId),
        ];
    }
    $payload = json_encode([
        'follower_id' => $followerId,
        'following_id' => $followingId,
        'status' => 'pendente',
    ], JSON_UNESCAPED_SLASHES);
    $ch = curl_init(SUPABASE_URL . '/rest/v1/follows');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge(club61_follows_headers(true), ['Prefer: return=minimal']),
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) {
        return [
            'ok' => true,
            'followers_count' => club61_follows_count_followers_accepted($followingId),
        ];
    }
    $patchUrl = SUPABASE_URL . '/rest/v1/follows?follower_id=eq.' . urlencode($followerId)
        . '&following_id=eq.' . urlencode($followingId);
    $patchBody = json_encode(['status' => 'pendente'], JSON_UNESCAPED_SLASHES);
    $chP = curl_init($patchUrl);
    curl_setopt_array($chP, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $patchBody,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge(club61_follows_headers(true), ['Prefer: return=minimal']),
    ]);
    curl_exec($chP);
    $c2 = curl_getinfo($chP, CURLINFO_HTTP_CODE);
    curl_close($chP);
    if ($c2 < 200 || $c2 >= 300) {
        return ['ok' => false, 'error' => 'insert_failed'];
    }

    return [
        'ok' => true,
        'followers_count' => club61_follows_count_followers_accepted($followingId),
    ];
}

/**
 * @return array{ok:bool, error?:string}
 */
function club61_follows_aceitar(string $profileOwnerId, string $followerId): array
{
    if ($profileOwnerId === '' || $followerId === '' || !club61_follows_service_ok()) {
        return ['ok' => false, 'error' => 'invalid'];
    }
    $url = SUPABASE_URL . '/rest/v1/follows?follower_id=eq.' . urlencode($followerId)
        . '&following_id=eq.' . urlencode($profileOwnerId) . '&status=eq.pendente';
    $body = json_encode(['status' => 'aceito'], JSON_UNESCAPED_SLASHES);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge(club61_follows_headers(true), ['Prefer: return=minimal']),
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        return ['ok' => false, 'error' => 'patch_failed'];
    }

    return ['ok' => true];
}

/**
 * @return array{ok:bool, error?:string}
 */
function club61_follows_recusar(string $profileOwnerId, string $followerId): array
{
    if ($profileOwnerId === '' || $followerId === '' || !club61_follows_service_ok()) {
        return ['ok' => false, 'error' => 'invalid'];
    }
    $url = SUPABASE_URL . '/rest/v1/follows?follower_id=eq.' . urlencode($followerId)
        . '&following_id=eq.' . urlencode($profileOwnerId) . '&status=eq.pendente';
    $body = json_encode(['status' => 'recusado'], JSON_UNESCAPED_SLASHES);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge(club61_follows_headers(true), ['Prefer: return=minimal']),
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        return ['ok' => false, 'error' => 'patch_failed'];
    }

    return ['ok' => true];
}
