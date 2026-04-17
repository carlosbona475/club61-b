<?php

declare(strict_types=1);

namespace Club61\Controllers;

/**
 * Rotas legadas / compatibilidade. Ver routes/web.php (POST /post/delete).
 */
final class LegacyController
{
    /**
     * POST /post/delete — exclui post do autor autenticado (Supabase via feed_delete_owned_post).
     * JSON: { success: bool, message?: string, csrf?: string }
     */
    public function deletePost(): void
    {
        require_once dirname(__DIR__, 2) . '/config/feed_interactions.php';

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        $input = [];
        $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (stripos($ct, 'application/json') !== false) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }

        $postId = isset($input['post_id']) ? (int) $input['post_id'] : (int) ($_POST['post_id'] ?? 0);
        $csrf = isset($input['csrf']) ? (string) $input['csrf'] : (string) ($_POST['csrf'] ?? '');

        if (!feed_csrf_validate($csrf)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Sessão expirada. Atualize a página.',
                'csrf' => feed_csrf_token(),
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
        if ($userId === '') {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Não autenticado.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        if ($postId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados inválidos'], JSON_UNESCAPED_UNICODE);

            return;
        }

        $result = feed_delete_owned_post($userId, $postId);
        if (!$result['success']) {
            $msg = $result['message'] ?? 'Erro ao excluir.';
            $code = str_contains($msg, 'não encontrad') ? 404 : (str_contains($msg, 'permissão') ? 403 : 400);
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);

            return;
        }

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /feed/delete-story — apaga story do autor (CSRF + sessão).
     *
     * @see features/feed/delete_story.php
     */
    public function feedDeleteStory(): void
    {
        require dirname(__DIR__, 2) . '/features/feed/delete_story.php';
    }

    /**
     * POST /post/reagir — alterna reação (emoji) em post_likes.
     * JSON: { post_id, emoji, csrf? } → { success, acao?, message? }
     */
    public function reagirPost(): void
    {
        require_once dirname(__DIR__, 2) . '/config/feed_interactions.php';

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        $raw = file_get_contents('php://input') ?: '';
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            $body = [];
        }

        $postId = isset($body['post_id']) ? (int) $body['post_id'] : 0;
        $emoji = isset($body['emoji']) ? (string) $body['emoji'] : feed_default_like_emoji();
        $csrf = isset($body['csrf']) ? (string) $body['csrf'] : '';

        if (!feed_csrf_validate($csrf)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Sessão expirada. Atualize a página.',
                'csrf' => feed_csrf_token(),
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
        if ($userId === '') {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Não autenticado'], JSON_UNESCAPED_UNICODE);

            return;
        }

        if ($postId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'post_id inválido'], JSON_UNESCAPED_UNICODE);

            return;
        }

        if (!feed_reaction_emoji_valid($emoji)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Emoji inválido'], JSON_UNESCAPED_UNICODE);

            return;
        }

        if (!feed_post_exists($postId)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Post não encontrado'], JSON_UNESCAPED_UNICODE);

            return;
        }

        $r = feed_reagir_toggle($userId, $postId, $emoji);
        if (!$r['success']) {
            http_response_code(503);
            echo json_encode([
                'success' => false,
                'message' => 'Não foi possível registar a reação. Verifique post_likes no Supabase.',
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        echo json_encode([
            'success' => true,
            'acao' => $r['acao'] ?? null,
            'csrf' => feed_csrf_token(),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /post/reacoes?post_id= — lista reações (emoji, user_id, is_minha).
     */
    public function reacoesPost(): void
    {
        require_once dirname(__DIR__, 2) . '/config/feed_interactions.php';

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            echo json_encode(['reacoes' => []], JSON_UNESCAPED_UNICODE);

            return;
        }

        $postId = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
        if ($postId <= 0) {
            echo json_encode(['reacoes' => []], JSON_UNESCAPED_UNICODE);

            return;
        }

        $userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
        $rows = feed_fetch_post_reactions($postId);
        $reacoes = [];
        foreach ($rows as $row) {
            $reacoes[] = [
                'emoji' => $row['emoji'],
                'user_id' => $row['user_id'],
                'is_minha' => $userId !== '' && $row['user_id'] === $userId,
            ];
        }

        echo json_encode(['reacoes' => $reacoes], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /admin — painel (Apache normalmente reescreve /admin → features/admin/index.php).
     * Útil se um front controller invocar rotas definidas em routes/web.php.
     */
    public function adminPanel(): void
    {
        require dirname(__DIR__, 2) . '/features/admin/index.php';
    }

    /**
     * GET /chat/mensagens — lista mensagens da sala (incremental com ?after= ou ?after_id=).
     * Resposta: { ok, success, mensagens, messages } (mesmo array em mensagens e messages).
     */
    public function buscarMensagens(): void
    {
        require_once dirname(__DIR__, 2) . '/config/paths.php';
        require_once CLUB61_ROOT . '/config/session.php';
        club61_session_start_safe();
        require_once CLUB61_ROOT . '/config/supabase.php';
        require_once CLUB61_ROOT . '/config/city_rooms.php';
        require_once dirname(__DIR__, 2) . '/features/chat/chat_backend.php';

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'mensagens' => [], 'messages' => []], JSON_UNESCAPED_UNICODE);

            return;
        }

        $uid = trim((string) ($_SESSION['user_id'] ?? ''));
        if ($uid === '') {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'auth', 'mensagens' => [], 'messages' => []], JSON_UNESCAPED_UNICODE);

            return;
        }

        if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
            http_response_code(503);
            echo json_encode(['ok' => false, 'mensagens' => [], 'messages' => []], JSON_UNESCAPED_UNICODE);

            return;
        }

        $salaId = trim((string) ($_GET['sala_id'] ?? ''));
        if (club61_city_room_by_slug($salaId) === null) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensagens' => [], 'messages' => []], JSON_UNESCAPED_UNICODE);

            return;
        }

        $after = trim((string) ($_GET['after'] ?? ''));
        $afterId = trim((string) ($_GET['after_id'] ?? ''));
        $idCandidate = $afterId !== '' ? $afterId : '';
        if ($idCandidate === '' && $after !== '' && preg_match('/^[0-9a-f-]{36}$/i', $after)) {
            $idCandidate = $after;
        }

        $afterIso = null;
        if ($idCandidate !== '') {
            $probe = club61_chat_fetch_message_by_id($idCandidate);
            if (is_array($probe) && isset($probe['created_at'], $probe['sala_id'])
                && (string) $probe['sala_id'] === $salaId) {
                $afterIso = (string) $probe['created_at'];
            }
        } elseif ($after !== '' && !preg_match('/^[0-9a-f-]{36}$/i', $after)) {
            $afterIso = $after;
        }

        $rows = club61_chat_fetch_messages_for_sala($salaId, $afterIso, 120);
        $enriched = club61_chat_enrich_messages($rows);

        echo json_encode([
            'ok' => true,
            'success' => true,
            'mensagens' => $enriched,
            'messages' => $enriched,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * POST /chat/enviar — mensagem (texto) e/ou mídia (campo `arquivo` ou `media`) para sala de bate-papo.
     * JSON: { success: bool, ok?: bool, message?: string }
     */
    public function enviarMensagem(): void
    {
        require_once dirname(__DIR__, 2) . '/config/paths.php';
        require_once CLUB61_ROOT . '/config/session.php';
        club61_session_start_safe();
        require_once CLUB61_ROOT . '/config/supabase.php';
        require_once CLUB61_ROOT . '/config/city_rooms.php';
        require_once dirname(__DIR__, 2) . '/features/chat/chat_backend.php';

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'ok' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        $uid = trim((string) ($_SESSION['user_id'] ?? ''));
        if ($uid === '') {
            http_response_code(401);
            echo json_encode(['success' => false, 'ok' => false, 'message' => 'Não autenticado'], JSON_UNESCAPED_UNICODE);

            return;
        }

        if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
            http_response_code(503);
            echo json_encode(['success' => false, 'ok' => false, 'message' => 'Chat indisponível no servidor.'], JSON_UNESCAPED_UNICODE);

            return;
        }

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
            foreach (['arquivo', 'media'] as $key) {
                if (!empty($_FILES[$key]) && is_array($_FILES[$key]) && (int) ($_FILES[$key]['error'] ?? 0) === UPLOAD_ERR_OK) {
                    $fileKey = $key;
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
                        } catch (\Exception $e) {
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
            http_response_code(400);
            echo json_encode(['success' => false, 'ok' => false, 'message' => 'Sala inválida ou sala_id ausente.'], JSON_UNESCAPED_UNICODE);

            return;
        }
        if ($content === '' && $mediaUrl === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'ok' => false, 'message' => 'Mensagem vazia.'], JSON_UNESCAPED_UNICODE);

            return;
        }
        if (strlen($content) > 1000) {
            http_response_code(400);
            echo json_encode(['success' => false, 'ok' => false, 'message' => 'Texto acima de 1000 caracteres.'], JSON_UNESCAPED_UNICODE);

            return;
        }

        $ins = club61_chat_insert_message($salaId, $uid, $content, $tipo, $mediaUrl);
        if (!$ins['ok']) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'ok' => false,
                'message' => $ins['detail'] ?? 'Falha ao gravar mensagem.',
            ], JSON_UNESCAPED_UNICODE);

            return;
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

        echo json_encode([
            'success' => true,
            'ok' => true,
            'id' => $ins['id'] ?? null,
            'created_at' => $lastIso,
            'message' => $messagePayload,
        ], JSON_UNESCAPED_UNICODE);
    }
}
