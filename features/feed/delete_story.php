<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/feed_interactions.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
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

$storyId = isset($_POST['story_id']) ? (string) $_POST['story_id'] : '';
if ($storyId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_story']);

    exit;
}

if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'service_unavailable']);

    exit;
}

// Verificar se o story pertence ao utilizador
$ch = curl_init(SUPABASE_URL . '/rest/v1/stories?id=eq.' . rawurlencode($storyId) . '&user_id=eq.' . rawurlencode($userId) . '&select=id');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
    ],
]);
$raw = curl_exec($ch);
curl_close($ch);
$rows = json_decode((string) $raw, true);
if (!is_array($rows) || $rows === []) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);

    exit;
}

// Deletar o story
$ch = curl_init(SUPABASE_URL . '/rest/v1/stories?id=eq.' . rawurlencode($storyId) . '&user_id=eq.' . rawurlencode($userId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Prefer: return=minimal',
    ],
]);
curl_exec($ch);
$delCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($delCode < 200 || $delCode >= 300) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'delete_failed']);

    exit;
}

echo json_encode(['ok' => true]);

exit;
