<?php
/**
 * Form legado — redireciona; o fluxo principal é follow_toggle.php (AJAX).
 * Mantém POST followed_id para compatibilidade (usa coluna following_id na API).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth_guard.php';
require_once dirname(__DIR__, 2) . '/config/supabase.php';
require_once dirname(__DIR__, 2) . '/config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /features/profile/index.php');

    exit;
}

if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Sessão expirada. Atualize a página.'));

    exit;
}

$follower_id = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
$followed_id = isset($_POST['followed_id']) ? trim((string) $_POST['followed_id']) : '';

if ($follower_id === '' || $followed_id === '' || $follower_id === $followed_id) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Dados inválidos.'));

    exit;
}

if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Serviço indisponível.'));

    exit;
}

$body = json_encode([
    'follower_id' => $follower_id,
    'following_id' => $followed_id,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init(SUPABASE_URL . '/rest/v1/followers');
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
        'Prefer: return=minimal',
    ],
]);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code < 200 || $code >= 300) {
    header('Location: /features/profile/index.php?user=' . urlencode($followed_id) . '&status=error&message=' . urlencode('Não foi possível seguir.'));

    exit;
}

header('Location: /features/profile/index.php?user=' . urlencode($followed_id) . '&status=ok&message=' . urlencode('A seguir.'));

exit;
