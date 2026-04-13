<?php
declare(strict_types=1);

/**
 * Atualiza is_private e message_permission via REST (service_role).
 */

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';
require_once CLUB61_ROOT . '/config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /features/profile/settings.php');
    exit;
}

$userId = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : '';
if ($userId === '' || !csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    header('Location: /features/profile/settings.php?status=error&message=' . urlencode('Sessão inválida ou CSRF.'));
    exit;
}

if (!supabase_service_role_available()) {
    header('Location: /features/profile/settings.php?status=error&message=' . urlencode('Serviço indisponível.'));
    exit;
}

$isPrivate = isset($_POST['is_private']) && (string) $_POST['is_private'] === '1';
$perm = isset($_POST['message_permission']) ? trim((string) $_POST['message_permission']) : 'all';
$allowedPerm = ['all', 'following_only', 'none'];
if (!in_array($perm, $allowedPerm, true)) {
    $perm = 'all';
}

$patchUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId);
$payload = json_encode([
    'is_private' => $isPrivate,
    'message_permission' => $perm,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($patchUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => array_merge(supabase_service_rest_headers(true), [
        'Prefer: return=minimal',
    ]),
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code < 200 || $code >= 300) {
    $detail = is_string($body) ? substr($body, 0, 200) : '';
    header('Location: /features/profile/settings.php?status=error&message=' . urlencode('Não foi possível salvar privacidade. ' . $detail));
    exit;
}

header('Location: /features/profile/settings.php?status=ok&message=' . urlencode('Privacidade atualizada.'));
exit;
