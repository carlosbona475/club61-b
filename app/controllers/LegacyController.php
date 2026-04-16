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
}
