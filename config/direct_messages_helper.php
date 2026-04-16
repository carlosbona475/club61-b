<?php

/**
 * direct_messages — leituras e contagens (service_role).
 */

declare(strict_types=1);

require_once __DIR__ . '/supabase.php';

function club61_dm_headers(bool $json = false): array
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

function club61_dm_service_ok(): bool
{
    return defined('SUPABASE_URL') && defined('SUPABASE_SERVICE_KEY') && SUPABASE_SERVICE_KEY !== ''
        && function_exists('club61_supabase_jwt_role')
        && club61_supabase_jwt_role((string) SUPABASE_SERVICE_KEY) === 'service_role';
}

function club61_dm_unread_count(string $userId): int
{
    if ($userId === '' || !club61_dm_service_ok()) {
        return 0;
    }
    $url = SUPABASE_URL . '/rest/v1/direct_messages?receiver_id=eq.' . urlencode($userId)
        . '&read_at=is.null&select=id';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge(club61_dm_headers(false), [
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
