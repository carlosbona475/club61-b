<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/city_rooms.php';
require_once __DIR__ . '/chat_backend.php';

/**
 * Resolve /chat/messages, /chat/send, /chat/enviar, … ou ?r=messages (sem mod_rewrite).
 */
function club61_chat_api_route(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($uri, PHP_URL_PATH) ?: '';
    if (preg_match('#/chat/(messages|send|enviar|react|online|presence|typing)/?$#', $path, $m)) {
        return $m[1] === 'enviar' ? 'send' : $m[1];
    }
    $r = trim((string) ($_GET['r'] ?? ''));
    if ($r === 'enviar') {
        return 'send';
    }

    return $r;
}

function club61_chat_json_response(int $status, array $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (array_key_exists('ok', $data) && !array_key_exists('success', $data)) {
        $data['success'] = (bool) ($data['ok'] === true);
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$route = club61_chat_api_route();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    club61_chat_json_response(503, [
        'ok' => false,
        'error' => 'service_unavailable',
        'message' => 'SUPABASE_SERVICE_KEY não configurada no servidor.',
    ]);
}

if ($route === '') {
    club61_chat_json_response(404, ['ok' => false, 'error' => 'not_found', 'message' => 'Rota de chat inválida. Use ?r=send ou /chat/send.']);
}

$uid = trim((string) ($_SESSION['user_id'] ?? ''));
if ($uid === '') {
    club61_chat_json_response(401, ['ok' => false, 'error' => 'auth']);
}

if ($route === 'messages' && $method === 'GET') {
    $salaId = trim((string) ($_GET['sala_id'] ?? ''));
    if (club61_city_room_by_slug($salaId) === null) {
        club61_chat_json_response(400, ['ok' => false, 'error' => 'sala']);
    }
    $after = trim((string) ($_GET['after'] ?? ''));
    /** Sem `after`: últimas mensagens (histórico). Com `after`: apenas mais novas que o timestamp. */
    $afterIso = $after !== '' ? $after : null;
    $rows = club61_chat_fetch_messages_for_sala($salaId, $afterIso, 120);
    $enriched = club61_chat_enrich_messages($rows);
    club61_chat_json_response(200, ['ok' => true, 'messages' => $enriched]);
}

if ($route === 'online' && $method === 'GET') {
    $salaId = trim((string) ($_GET['sala_id'] ?? ''));
    if (club61_city_room_by_slug($salaId) === null) {
        club61_chat_json_response(400, ['ok' => false, 'error' => 'sala']);
    }
    $list = club61_chat_online_in_sala($salaId, 120);
    club61_chat_json_response(200, ['ok' => true, 'users' => $list, 'count' => count($list)]);
}

if ($route === 'presence' && $method === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    if (!is_array($j)) {
        $j = [];
    }
    $salaId = trim((string) ($j['sala_id'] ?? $_POST['sala_id'] ?? ''));
    if (club61_city_room_by_slug($salaId) === null) {
        club61_chat_json_response(400, ['ok' => false, 'error' => 'sala']);
    }
    $res = club61_chat_upsert_presence($uid, $salaId);
    if (!$res['ok']) {
        club61_chat_json_response(500, ['ok' => false, 'error' => $res['error'] ?? 'presence']);
    }
    club61_chat_json_response(200, ['ok' => true]);
}

if ($route === 'typing' && $method === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    if (!is_array($j)) {
        $j = [];
    }
    $salaId = trim((string) ($j['sala_id'] ?? $_POST['sala_id'] ?? ''));
    if (club61_city_room_by_slug($salaId) === null) {
        club61_chat_json_response(400, ['ok' => false, 'error' => 'sala']);
    }
    club61_chat_typing_touch($salaId, $uid, 4);
    club61_chat_json_response(200, ['ok' => true]);
}

if ($route === 'typing' && $method === 'GET') {
    $salaId = trim((string) ($_GET['sala_id'] ?? ''));
    if (club61_city_room_by_slug($salaId) === null) {
        club61_chat_json_response(400, ['ok' => false, 'error' => 'sala']);
    }
    $list = club61_chat_typing_list($salaId, $uid);
    club61_chat_json_response(200, ['ok' => true, 'typing' => $list]);
}

if ($route === 'react' && $method === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    if (!is_array($j)) {
        $j = [];
    }
    $messageId = trim((string) ($j['message_id'] ?? $_POST['message_id'] ?? ''));
    $emoji = (string) ($j['emoji'] ?? $_POST['emoji'] ?? '');
    if ($messageId === '') {
        club61_chat_json_response(400, ['ok' => false, 'error' => 'message_id']);
    }
    $url = SUPABASE_URL . '/rest/v1/chat_messages?id=eq.' . rawurlencode($messageId) . '&select=id,sala_id';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => club61_chat_service_headers(false),
        CURLOPT_HTTPGET => true,
    ]);
    $rawM = curl_exec($ch);
    curl_close($ch);
    $dec = json_decode($rawM ?: '[]', true);
    $salaCheck = is_array($dec) && isset($dec[0]['sala_id']) ? (string) $dec[0]['sala_id'] : '';
    if ($salaCheck === '' || club61_city_room_by_slug($salaCheck) === null) {
        club61_chat_json_response(403, ['ok' => false, 'error' => 'message']);
    }
    $res = club61_chat_toggle_reaction($messageId, $uid, $emoji);
    if (!$res['ok']) {
        club61_chat_json_response(400, ['ok' => false, 'error' => $res['error'] ?? 'react']);
    }
    $re = club61_chat_reactions_grouped([$messageId]);
    $summary = $re[$messageId] ?? [];
    club61_chat_json_response(200, ['ok' => true, 'toggled' => $res['toggled'] ?? '', 'reactions' => $summary]);
}

if ($route === 'send' && $method === 'POST') {
    $content = '';
    $salaId = '';
    $mediaUrl = null;
    $tipo = 'texto';

    $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    $isMultipart = stripos($ct, 'multipart/form-data') !== false;

    if ($isMultipart) {
        $salaId = trim((string) ($_POST['sala_id'] ?? ''));
        $content = trim((string) ($_POST['mensagem'] ?? $_POST['message'] ?? $_POST['content'] ?? ''));
        $fileKey = null;
        foreach (['arquivo', 'media'] as $fk) {
            if (!empty($_FILES[$fk]) && is_array($_FILES[$fk]) && (int) ($_FILES[$fk]['error'] ?? 0) === UPLOAD_ERR_OK) {
                $fileKey = $fk;
                break;
            }
        }
        if ($fileKey !== null) {
            $file = $_FILES[$fileKey];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm'];
            $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'video/mp4' => 'mp4', 'video/webm' => 'webm'];
            $mime = '';
            $tmp = (string) ($file['tmp_name'] ?? '');
            if ($tmp !== '' && is_uploaded_file($tmp)) {
                if (function_exists('finfo_open')) {
                    $fi = finfo_open(FILEINFO_MIME_TYPE);
                    if ($fi !== false) {
                        $mime = (string) finfo_file($fi, $tmp);
                        finfo_close($fi);
                    }
                }
                if ($mime === '' && function_exists('mime_content_type')) {
                    try {
                        $mime = (string) mime_content_type($tmp);
                    } catch (Exception $e) {
                        $mime = '';
                    }
                }
                if ($mime === '') {
                    $mime = trim((string) ($file['type'] ?? ''));
                }
            }
            if ($mime !== '' && in_array($mime, $allowed, true) && (int) ($file['size'] ?? 0) <= 20 * 1024 * 1024) {
                $filename = uniqid('ch_', true) . '.' . ($extMap[$mime] ?? 'bin');
                $binary = file_get_contents($tmp);
                if ($binary !== false) {
                    $up = club61_chat_upload_media($binary, $mime, $filename);
                    if ($up !== null) {
                        $mediaUrl = $up;
                        $tipo = club61_chat_mime_to_tipo($mime);
                    }
                }
            }
        }
    } else {
        $raw = file_get_contents('php://input') ?: '';
        $j = json_decode($raw, true);
        if (is_array($j)) {
            $content = trim((string) ($j['mensagem'] ?? $j['message'] ?? $j['content'] ?? ''));
            $salaId = trim((string) ($j['sala_id'] ?? ''));
        }
    }

    if ($salaId === '') {
        $salaId = trim((string) ($_POST['sala_id'] ?? ''));
    }
    if ($content === '') {
        $content = trim((string) ($_POST['mensagem'] ?? $_POST['message'] ?? $_POST['content'] ?? ''));
    }

    if (club61_city_room_by_slug($salaId) === null) {
        club61_chat_json_response(400, [
            'ok' => false,
            'error' => 'sala',
            'message' => 'Sala inválida ou sala_id ausente.',
        ]);
    }
    if ($content === '' && $mediaUrl === null) {
        club61_chat_json_response(400, ['ok' => false, 'error' => 'empty', 'message' => 'Mensagem vazia.']);
    }
    if (strlen($content) > 1000) {
        club61_chat_json_response(400, ['ok' => false, 'error' => 'length', 'message' => 'Texto acima de 1000 caracteres.']);
    }

    $ins = club61_chat_insert_message($salaId, $uid, $content, $tipo, $mediaUrl);
    if (!$ins['ok']) {
        club61_chat_json_response(500, [
            'ok' => false,
            'error' => $ins['error'] ?? 'send',
            'message' => $ins['detail'] ?? 'Falha ao gravar mensagem.',
            'detail' => $ins['detail'] ?? null,
            'http_code' => $ins['http_code'] ?? null,
        ]);
    }

    $lastIso = null;
    $messagePayload = null;
    if (!empty($ins['id'])) {
        $u = SUPABASE_URL . '/rest/v1/chat_messages?id=eq.' . rawurlencode((string) $ins['id'])
            . '&select=created_at';
        $ch = curl_init($u);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => club61_chat_service_headers(false),
            CURLOPT_HTTPGET => true,
        ]);
        $rawT = curl_exec($ch);
        curl_close($ch);
        $row = json_decode($rawT ?: '[]', true);
        if (is_array($row) && isset($row[0]['created_at'])) {
            $lastIso = (string) $row[0]['created_at'];
        }
        $fresh = club61_chat_fetch_message_by_id((string) $ins['id']);
        if (is_array($fresh)) {
            $enriched = club61_chat_enrich_messages([$fresh]);
            if (isset($enriched[0]) && is_array($enriched[0])) {
                $messagePayload = $enriched[0];
            }
        }
    }

    club61_chat_json_response(200, [
        'ok' => true,
        'id' => $ins['id'] ?? null,
        'created_at' => $lastIso,
        'message' => $messagePayload,
    ]);
}

club61_chat_json_response(405, ['ok' => false, 'error' => 'method']);
