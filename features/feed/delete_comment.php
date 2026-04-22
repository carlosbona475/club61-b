<?php

/**
 * Exclui um comentário próprio.
 * POST: comment_id, csrf
 * Resposta JSON: { ok: bool, message?: string, csrf: string }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

header('Content-Type: application/json; charset=utf-8');

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/feed_interactions.php';

function club61_delete_comment_respond(array $data, int $status = 200): void
{
    if (!isset($data['csrf'])) {
        $data['csrf'] = feed_csrf_token();
    }
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($data, $flags);
    if ($json === false) {
        http_response_code(500);
        echo '{"ok":false,"message":"json_encode_failed"}';
        exit;
    }
    http_response_code($status);
    echo $json;
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    club61_delete_comment_respond(['ok' => false, 'message' => 'method_not_allowed'], 405);
}

$csrf = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
if (!feed_csrf_validate($csrf)) {
    club61_delete_comment_respond(['ok' => false, 'message' => 'Sessão expirada. Atualize a página.'], 403);
}

$userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
if ($userId === '') {
    club61_delete_comment_respond(['ok' => false, 'message' => 'Usuário não autenticado.'], 401);
}

$commentId = isset($_POST['comment_id']) ? trim((string) $_POST['comment_id']) : '';
if ($commentId === '') {
    club61_delete_comment_respond(['ok' => false, 'message' => 'Comentário inválido.'], 400);
}

if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    club61_delete_comment_respond(['ok' => false, 'message' => 'Serviço indisponível.'], 503);
}

$table = feed_post_comments_table_ready() ? 'post_comments' : (feed_comments_table_ready() ? 'comments' : '');
if ($table === '') {
    club61_delete_comment_respond(['ok' => false, 'message' => 'Comentários indisponíveis.'], 503);
}

$url = SUPABASE_URL . '/rest/v1/' . $table
    . '?id=eq.' . rawurlencode($commentId)
    . '&user_id=eq.' . rawurlencode($userId);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'DELETE',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => [
        'apikey: '               . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Prefer: return=minimal',
    ],
]);
curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code < 200 || $code >= 300) {
    club61_delete_comment_respond(['ok' => false, 'message' => 'Falha ao excluir (HTTP ' . $code . ').'], 500);
}

club61_delete_comment_respond(['ok' => true]);
