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
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Dados invalidos para descurtir.'));
    exit;
}

$url = SUPABASE_URL . '/rest/v1/likes?user_id=eq.' . urlencode((string) $user_id) . '&post_id=eq.' . urlencode((string) ((int) $post_id));

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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

header('Location: /features/feed/index.php?status=ok&message=' . urlencode('Curtida removida.'));
exit;
