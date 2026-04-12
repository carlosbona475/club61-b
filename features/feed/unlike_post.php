<?php



declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /features/feed/index.php');
    exit;
}

if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Sessão expirada.'));
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$post_id = $_POST['post_id'] ?? null;

if ($user_id === null || $post_id === null || !is_numeric($post_id)) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Dados invalidos para descurtir.'));
    exit;
}

$url = SUPABASE_URL . '/rest/v1/likes?user_id=eq.' . urlencode((string) $user_id) . '&post_id=eq.' . urlencode((string) ((int) $post_id));

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_ANON_KEY,
    'Authorization: Bearer ' . $_SESSION['access_token'],
]);

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $statusCode < 200 || $statusCode >= 300) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Nao foi possivel remover a curtida.'));
    exit;
}

$returnTo = isset($_POST['return_to']) ? trim((string) $_POST['return_to']) : '';
if ($returnTo !== '' && strpos($returnTo, '/') === 0) {
    $sep = strpos($returnTo, '?') !== false ? '&' : '?';
    header('Location: ' . $returnTo . $sep . 'status=ok&message=' . urlencode('Curtida removida.'));
    exit;
}

header('Location: /features/feed/index.php?status=ok&message=' . urlencode('Curtida removida.'));
exit;
