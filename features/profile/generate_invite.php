<?php



declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap_path.php';

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
    header('Location: index.php?status=error&message=' . urlencode('Usuario nao autenticado.'));
    exit;
}

$code = strtoupper(bin2hex(random_bytes(4)));

$data = [
    'code' => $code,
    'created_by' => $user_id,
];

$ch = curl_init(SUPABASE_URL . '/rest/v1/invites');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_ANON_KEY,
    'Authorization: Bearer ' . $_SESSION['access_token'],
    'Content-Type: application/json',
    'Prefer: return=minimal',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $statusCode < 200 || $statusCode >= 300) {
    header('Location: index.php?status=error&message=' . urlencode('Nao foi possivel gerar convite.'));
    exit;
}

header('Location: index.php');
exit;
