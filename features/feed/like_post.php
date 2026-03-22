<?php

session_start();
require_once __DIR__ . '/../../auth_guard.php';
require_once __DIR__ . '/../../config/supabase.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /features/feed/index.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$post_id = $_POST['post_id'] ?? null;

if ($user_id === null || $post_id === null || !is_numeric($post_id)) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Dados invalidos para curtida.'));
    exit;
}

$data = [
    'user_id' => $user_id,
    'post_id' => (int) $post_id,
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
