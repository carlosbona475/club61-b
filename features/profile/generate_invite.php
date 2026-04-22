<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';
require_once CLUB61_ROOT . '/config/csrf.php';

if (!isCurrentUserAdmin()) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Acesso negado'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    header('Location: index.php?status=error&message=' . urlencode('Sessão expirada. Atualize a página.'));
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if ($user_id === null || $user_id === '') {
    header('Location: index.php?status=error&message=' . urlencode('Usuário não autenticado.'));
    exit;
}

if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    header('Location: index.php?status=error&message=' . urlencode('Serviço indisponível (service key ausente).'));
    exit;
}

$code    = strtolower(bin2hex(random_bytes(4)));
$expires = gmdate('c', time() + 30 * 86400); // 30 dias

$data = [
    'code'       => $code,
    'created_by' => (string) $user_id,
    'status'     => 'available',
    'expires_at' => $expires,
];

$ch = curl_init(SUPABASE_URL . '/rest/v1/invites');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'apikey: '               . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal',
    ],
    CURLOPT_POSTFIELDS     => json_encode($data, JSON_UNESCAPED_UNICODE),
]);
$response   = curl_exec($ch);
$statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $statusCode < 200 || $statusCode >= 300) {
    header('Location: index.php?status=error&message=' . urlencode('Não foi possível gerar convite (HTTP ' . $statusCode . ').'));
    exit;
}

header('Location: index.php?status=ok&message=' . urlencode('Convite gerado: ' . $code) . '&tab=convites');
exit;
