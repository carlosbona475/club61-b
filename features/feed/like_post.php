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
$post_id = trim((string) ($_POST['post_id'] ?? ''));

if ($user_id === null || $post_id === '') {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Dados invalidos para curtida.'));
    exit;
}

$data = [
    'user_id' => $user_id,
    'post_id' => $post_id,
];

$ch = curl_init(SUPABASE_URL . '/rest/v1/likes');

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Nao foi possivel curtir o post.'));
    exit;
}

$returnTo = isset($_POST['return_to']) ? trim((string) $_POST['return_to']) : '';
if ($returnTo !== '' && strpos($returnTo, '/') === 0) {
    $sep = strpos($returnTo, '?') !== false ? '&' : '?';
    header('Location: ' . $returnTo . $sep . 'status=ok&message=' . urlencode('Post curtido.'));
    exit;
}

header('Location: /features/feed/index.php?status=ok&message=' . urlencode('Post curtido.'));
exit;
