<?php

/**
 * Alterna curtida em story_likes (Supabase REST, service role).
 * JSON: { ok, status, likes_count, liked, csrf } ou erro.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

header('Content-Type: application/json; charset=utf-8');

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/feed_interactions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$csrf = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
if (!feed_csrf_validate($csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

$userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
if ($userId === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit;
}

if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_config']);
    exit;
}

$storyId = trim((string) ($_POST['story_id'] ?? ''));
if ($storyId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_story']);
    exit;
}

$baseHeaders = [
    'apikey: ' . SUPABASE_SERVICE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
    'Accept: application/json',
];

$checkUrl = SUPABASE_URL . '/rest/v1/story_likes?story_id=eq.' . rawurlencode($storyId)
    . '&user_id=eq.' . rawurlencode($userId) . '&select=user_id';

$ch = curl_init($checkUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => $baseHeaders,
]);
$checkRaw = curl_exec($ch);
$checkCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$hadLike = false;
if ($checkCode >= 200 && $checkCode < 300) {
    $checkRows = json_decode((string) $checkRaw, true);
    $hadLike = is_array($checkRows) && $checkRows !== [];
}

if ($hadLike) {
    $delUrl = SUPABASE_URL . '/rest/v1/story_likes?story_id=eq.' . rawurlencode($storyId)
        . '&user_id=eq.' . rawurlencode($userId);
    $ch = curl_init($delUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge($baseHeaders, ['Prefer: return=minimal']),
    ]);
    curl_exec($ch);
    $delCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($delCode < 200 || $delCode >= 300) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'toggle_failed']);
        exit;
    }
    $status = 'unliked';
} else {
    $payload = json_encode([
        'story_id' => $storyId,
        'user_id' => $userId,
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init(SUPABASE_URL . '/rest/v1/story_likes');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge($baseHeaders, [
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ]),
    ]);
    curl_exec($ch);
    $insCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($insCode < 200 || $insCode >= 300) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'toggle_failed']);
        exit;
    }
    $status = 'liked';
}

$countUrl = SUPABASE_URL . '/rest/v1/story_likes?story_id=eq.' . rawurlencode($storyId) . '&select=user_id';
$ch = curl_init($countUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => $baseHeaders,
]);
$countRaw = curl_exec($ch);
curl_close($ch);

$rows = json_decode((string) $countRaw, true) ?? [];
$likesCount = is_array($rows) ? count($rows) : 0;
$likedNow = $status === 'liked';

echo json_encode([
    'ok' => true,
    'status' => $status,
    'likes_count' => $likesCount,
    'liked' => $likedNow,
    'csrf' => feed_csrf_token(),
]);
exit;
