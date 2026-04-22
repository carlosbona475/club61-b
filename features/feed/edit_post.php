<?php

/**
 * Edita a legenda (caption) de um post do próprio usuário.
 * POST JSON: { post_id, caption, csrf }
 * Resposta: { success: bool, message?: string, caption?: string, csrf: string }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

header('Content-Type: application/json; charset=utf-8');

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/feed_interactions.php';

function club61_edit_post_respond(array $data, int $status = 200): void
{
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    if (!isset($data['csrf'])) {
        $data['csrf'] = feed_csrf_token();
    }
    $json = json_encode($data, $flags);
    if ($json === false) {
        http_response_code(500);
        echo '{"success":false,"message":"json_encode_failed"}';
        exit;
    }
    http_response_code($status);
    echo $json;
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    club61_edit_post_respond(['success' => false, 'message' => 'method_not_allowed'], 405);
}

$rawBody = file_get_contents('php://input');
$payload = [];
if (is_string($rawBody) && $rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}
if ($payload === [] && !empty($_POST)) {
    $payload = $_POST;
}

$csrf = isset($payload['csrf']) ? (string) $payload['csrf'] : '';
if (!feed_csrf_validate($csrf)) {
    club61_edit_post_respond(['success' => false, 'message' => 'Sessão expirada. Atualize a página.'], 403);
}

$userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
if ($userId === '') {
    club61_edit_post_respond(['success' => false, 'message' => 'Usuário não autenticado.'], 401);
}

if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    club61_edit_post_respond(['success' => false, 'message' => 'Serviço indisponível.'], 503);
}

$postId = isset($payload['post_id']) ? trim((string) $payload['post_id']) : '';
if ($postId === '') {
    club61_edit_post_respond(['success' => false, 'message' => 'Post inválido.'], 400);
}

$rawCaption = isset($payload['caption']) ? (string) $payload['caption'] : '';
$caption = strip_tags($rawCaption);
$caption = preg_replace('/\s+/u', ' ', $caption);
if (!is_string($caption)) {
    $caption = '';
}
$caption = trim($caption);
$len = function_exists('mb_strlen') ? mb_strlen($caption, 'UTF-8') : strlen($caption);
if ($len > 2000) {
    $caption = function_exists('mb_substr') ? mb_substr($caption, 0, 2000, 'UTF-8') : substr($caption, 0, 2000);
}

if (!feed_post_exists($postId)) {
    club61_edit_post_respond(['success' => false, 'message' => 'Post não encontrado.'], 404);
}

$url = SUPABASE_URL . '/rest/v1/posts?id=eq.' . rawurlencode($postId) . '&user_id=eq.' . rawurlencode($userId);
$body = json_encode(['caption' => $caption], JSON_UNESCAPED_UNICODE);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'PATCH',
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => [
        'apikey: '               . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal',
    ],
]);
curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code < 200 || $code >= 300) {
    club61_edit_post_respond(['success' => false, 'message' => 'Não foi possível editar (HTTP ' . $code . ').'], 500);
}

club61_edit_post_respond(['success' => true, 'caption' => $caption], 200);
