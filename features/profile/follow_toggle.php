<?php
/**
 * Toggle seguir / deixar de seguir (JSON).
 * POST: following_id (uuid), csrf
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth_guard.php';
require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/followers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);

    exit;
}

if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'csrf']);

    exit;
}

$followerId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
if ($followerId === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth']);

    exit;
}

$followingId = isset($_POST['following_id']) ? trim((string) $_POST['following_id']) : '';
if ($followingId === '' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $followingId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_target']);

    exit;
}

if ($followerId === $followingId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'cannot_follow_self']);

    exit;
}

if (!followers_service_ok()) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'service_unavailable']);

    exit;
}

$now = time();
$last = isset($_SESSION['follow_toggle_last_ts']) ? (int) $_SESSION['follow_toggle_last_ts'] : 0;
if ($last > 0 && ($now - $last) < 2) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'rate_limit', 'retry_after' => 2 - ($now - $last)]);

    exit;
}

$checkUrl = SUPABASE_URL . '/rest/v1/followers?follower_id=eq.' . urlencode($followerId)
    . '&following_id=eq.' . urlencode($followingId) . '&select=id';
$ch = curl_init($checkUrl);
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
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'query_failed']);

    exit;
}
$rows = json_decode((string) $raw, true);
$exists = is_array($rows) && $rows !== [];

$headersWrite = [
    'apikey: ' . SUPABASE_SERVICE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
    'Content-Type: application/json',
    'Prefer: return=minimal',
];

if ($exists) {
    $delUrl = SUPABASE_URL . '/rest/v1/followers?follower_id=eq.' . urlencode($followerId)
        . '&following_id=eq.' . urlencode($followingId);
    $ch = curl_init($delUrl);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $headersWrite,
    ]);
    curl_exec($ch);
    $delCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($delCode < 200 || $delCode >= 300) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'unfollow_failed']);

        exit;
    }
} else {
    $body = json_encode([
        'follower_id' => $followerId,
        'following_id' => $followingId,
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init(SUPABASE_URL . '/rest/v1/followers');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $headersWrite,
    ]);
    curl_exec($ch);
    $insCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($insCode < 200 || $insCode >= 300) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'follow_failed']);

        exit;
    }
}

$_SESSION['follow_toggle_last_ts'] = $now;

$following = !$exists;
$followersCount = getFollowersCount($followingId);

$newCsrf = csrf_token();

echo json_encode([
    'ok' => true,
    'following' => $following,
    'followers_count' => $followersCount,
    'csrf' => $newCsrf,
]);
