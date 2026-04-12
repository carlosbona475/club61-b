<?php

/**
 * Toggle curtida — post_likes (service) ou tabela legado likes (JWT).
 * JSON: { ok, status: "liked"|"unliked", likes_count, liked, csrf } ou erro.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap_path.php';

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
$accessToken = isset($_SESSION['access_token']) ? (string) $_SESSION['access_token'] : '';

if ($userId === '' || $accessToken === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth']);

    exit;
}

$postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_post']);

    exit;
}

if (!feed_post_exists($postId)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'post_not_found']);

    exit;
}

$status = null;
$triedPostLikes = false;

if (feed_sk_available() && feed_post_likes_table_ready()) {
    $triedPostLikes = true;
    $r = feed_toggle_post_likes_row($userId, $postId);
    if ($r['success'] && $r['status'] !== null) {
        $status = $r['status'];
    }
}

if ($status === null) {
    $r = feed_toggle_legacy_likes_row($userId, $accessToken, $postId);
    if (!$r['success'] || $r['status'] === null) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => $r['error'] ?? 'toggle_failed',
            'hint' => $triedPostLikes
                ? 'post_likes falhou e likes também falhou — verifique RLS/tabelas.'
                : 'Não foi possível curtir (tabela likes / sessão).',
        ]);

        exit;
    }
    $status = $r['status'];
}

$count = getLikesCount($postId);
$likedNow = $status === 'liked';

echo json_encode([
    'ok' => true,
    'status' => $status,
    'likes_count' => $count,
    'liked' => $likedNow,
    'csrf' => feed_csrf_token(),
]);
