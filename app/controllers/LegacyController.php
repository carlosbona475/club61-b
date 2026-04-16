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
     * GET /admin — painel (Apache normalmente reescreve /admin → features/admin/index.php).
     * Útil se um front controller invocar rotas definidas em routes/web.php.
     */
    public function adminPanel(): void
    {
        require dirname(__DIR__, 2) . '/features/admin/index.php';
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

        echo json_encode(['success' => true, 'ok' => true, 'id' => $ins['id'] ?? null], JSON_UNESCAPED_UNICODE);
    }
}
