<?php

/**
 * Comentário async — insere em post_comments e devolve HTML escapado + JSON.
 * POST: post_id, comment, csrf — rate limit 1 / 3s por sessão.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

header('Content-Type: application/json; charset=utf-8');

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/feed_interactions.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';

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

if (!feed_sk_available() || !feed_post_comments_table_ready()) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'comments_unavailable',
        'hint' => 'Execute sql/social_feed_upgrade.sql no Supabase para criar post_comments.',
    ]);

    exit;
}

$now = time();
$last = isset($_SESSION['feed_comment_last_ts']) ? (int) $_SESSION['feed_comment_last_ts'] : 0;
if ($last > 0 && ($now - $last) < 3) {
    http_response_code(429);
    echo json_encode([
        'ok' => false,
        'error' => 'rate_limit',
        'retry_after' => 3 - ($now - $last),
    ]);

    exit;
}

$postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_post']);

    exit;
}

$rawComment = isset($_POST['comment']) ? (string) $_POST['comment'] : '';
$collapsed = preg_replace('/\s+/u', ' ', $rawComment);
$comment = trim($collapsed !== null ? $collapsed : $rawComment);
if ($comment === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_comment']);

    exit;
}

$len = function_exists('mb_strlen') ? mb_strlen($comment, 'UTF-8') : strlen($comment);
if ($len > 2000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_comment']);

    exit;
}

if (!feed_post_exists($postId)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'post_not_found']);

    exit;
}

$body = json_encode([
    'post_id' => $postId,
    'user_id' => $userId,
    'comment_text' => $comment,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init(SUPABASE_URL . '/rest/v1/post_comments');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation',
    ],
]);
$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($raw === false || $code < 200 || $code >= 300) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'insert_failed']);

    exit;
}

$_SESSION['feed_comment_last_ts'] = $now;

$rows = json_decode((string) $raw, true);
$row = is_array($rows) && isset($rows[0]) ? $rows[0] : null;
$cid = is_array($row) && isset($row['id']) ? (string) $row['id'] : null;

$prof = feed_fetch_profiles_by_ids([(string) $userId]);
$u = $prof[$userId] ?? [];
$display = club61_display_id_label(isset($u['display_id']) ? (string) $u['display_id'] : null);

$html = feed_render_comment_line_html($cid, (string) $display, $comment);

echo json_encode([
    'ok' => true,
    'post_id' => $postId,
    'html' => $html,
    'csrf' => feed_csrf_token(),
], JSON_UNESCAPED_UNICODE);

exit;
