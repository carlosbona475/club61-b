<?php

declare(strict_types=1);

require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/feed_interactions.php';

function profile_stats_service_ok(): bool
{
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
        return false;
    }

    return function_exists('club61_supabase_jwt_role')
        && club61_supabase_jwt_role((string) SUPABASE_SERVICE_KEY) === 'service_role';
}

function profile_count_posts(string $userId): int
{
    if ($userId === '' || !profile_stats_service_ok()) {
        return 0;
    }
    $url = SUPABASE_URL . '/rest/v1/posts?user_id=eq.' . urlencode($userId) . '&select=id';
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

function profile_count_likes_received(string $userId): int
{
    if ($userId === '' || !profile_stats_service_ok()) {
        return 0;
    }
    $url = SUPABASE_URL . '/rest/v1/posts?user_id=eq.' . urlencode($userId) . '&select=id&limit=2000';
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
    if (!is_array($rows)) {
        return 0;
    }
    $ids = [];
    foreach ($rows as $r) {
        if (isset($r['id'])) {
            $ids[] = (int) $r['id'];
        }
    }
    if ($ids === []) {
        return 0;
    }
    $map = feed_get_likes_count_map($ids);
    $sum = 0;
    foreach ($map as $c) {
        $sum += (int) $c;
    }

    return $sum;
}
