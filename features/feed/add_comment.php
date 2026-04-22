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

/** Só com `CLUB61_DEBUG=1` (ou `true`) no .env — inclui corpo do erro do Supabase em insert_failed. */
function club61_add_comment_debug_enabled(): bool
{
    $v = getenv('CLUB61_DEBUG');
    if ($v === false || $v === '') {
        return false;
    }

    return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);
}

/**
 * Resposta JSON sempre com corpo válido (evita json_encode false → resposta vazia → JSON.parse "Unexpected end of input").
 *
 * @param array<string,mixed> $data
 */
function club61_add_comment_json_response(array $data, int $status = 200): void
{
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($data, $flags);
    if ($json === false) {
        http_response_code(500);
        echo '{"ok":false,"error":"json_encode_failed"}';
        exit;
    }
    http_response_code($status);
    echo $json;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    club61_add_comment_json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$csrf = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
if (!feed_csrf_validate($csrf)) {
    club61_add_comment_json_response(['ok' => false, 'error' => 'csrf'], 403);
}

$userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
if ($userId === '') {
    club61_add_comment_json_response(['ok' => false, 'error' => 'auth'], 401);
}

if (!feed_sk_available() || (!feed_post_comments_table_ready() && !feed_comments_table_ready())) {
    club61_add_comment_json_response([
        'ok' => false,
        'error' => 'comments_unavailable',
        'hint' => 'Comentários indisponíveis no banco (post_comments/comments).',
    ], 503);
}

$now = time();
$last = isset($_SESSION['feed_comment_last_ts']) ? (int) $_SESSION['feed_comment_last_ts'] : 0;
if ($last > 0 && ($now - $last) < 3) {
    club61_add_comment_json_response([
        'ok' => false,
        'error' => 'rate_limit',
        'retry_after' => 3 - ($now - $last),
    ], 429);
}

$postId = trim((string) ($_POST['post_id'] ?? ''));
if ($postId === '') {
    club61_add_comment_json_response(['ok' => false, 'error' => 'invalid_post'], 400);
}

$rawComment = isset($_POST['comment']) ? (string) $_POST['comment'] : '';
$collapsed = preg_replace('/\s+/u', ' ', $rawComment);
$comment = trim($collapsed !== null ? $collapsed : $rawComment);
if ($comment === '') {
    club61_add_comment_json_response(['ok' => false, 'error' => 'invalid_comment'], 400);
}

$len = function_exists('mb_strlen') ? mb_strlen($comment, 'UTF-8') : strlen($comment);
if ($len > 2000) {
    club61_add_comment_json_response(['ok' => false, 'error' => 'invalid_comment'], 400);
}

if (!feed_post_exists($postId)) {
    club61_add_comment_json_response(['ok' => false, 'error' => 'post_not_found'], 404);
}

$commentTable = feed_post_comments_table_ready() ? 'post_comments' : 'comments';
$commentPayload = [
    'post_id' => $postId,
    'user_id' => $userId,
];
if ($commentTable === 'post_comments') {
    $commentPayload['comment_text'] = $comment;
} else {
    $commentPayload['text'] = $comment;
}
$jsonFlags = JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}
$body = json_encode($commentPayload, $jsonFlags);
if ($body === false) {
    club61_add_comment_json_response(['ok' => false, 'error' => 'invalid_comment_encoding'], 400);
}

$ch = curl_init(SUPABASE_URL . '/rest/v1/' . $commentTable);
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
    $fail = ['ok' => false, 'error' => 'insert_failed'];
    if (club61_add_comment_debug_enabled()) {
        $body = is_string($raw) ? $raw : '';
        $fail['rest_status'] = $code;
        $fail['curl_ok'] = $raw !== false;
        $fail['table'] = $commentTable;
        $fail['supabase_response'] = function_exists('mb_substr')
            ? mb_substr($body, 0, 2000, 'UTF-8')
            : substr($body, 0, 2000);
    }
    club61_add_comment_json_response($fail, 500);
}

$_SESSION['feed_comment_last_ts'] = $now;

$rows = json_decode((string) $raw, true);
$row = is_array($rows) && isset($rows[0]) ? $rows[0] : null;
$cid = is_array($row) && isset($row['id']) ? (string) $row['id'] : null;

$prof = feed_fetch_profiles_by_ids([(string) $userId]);
$u = $prof[$userId] ?? [];
$display = club61_display_id_label(isset($u['display_id']) ? (string) $u['display_id'] : null);

$html = feed_render_comment_line_html(
    $cid,
    (string) $display,
    $comment,
    isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '',
    $postId
);

club61_add_comment_json_response([
    'ok' => true,
    'post_id' => $postId,
    'html' => $html,
    'csrf' => feed_csrf_token(),
], 200);
