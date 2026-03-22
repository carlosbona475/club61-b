<?php

session_start();
require_once __DIR__ . '/../../auth_guard.php';
require_once __DIR__ . '/../../config/supabase.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$follower_id = $_SESSION['user_id'] ?? null;
$followed_id = $_POST['followed_id'] ?? null;

if ($follower_id === null || $followed_id === null || trim((string) $followed_id) === '') {
    header('Location: index.php?status=error&message=' . urlencode('Dados invalidos para seguir.'));
    exit;
}

if ((string) $follower_id === (string) $followed_id) {
    header('Location: index.php?status=error&message=' . urlencode('Voce nao pode seguir a si mesmo.'));
    exit;
}

$data = [
    'follower_id' => $follower_id,
    'followed_id' => $followed_id,
];

$ch = curl_init(SUPABASE_URL . '/rest/v1/followers');
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
    header('Location: index.php?status=error&message=' . urlencode('Nao foi possivel seguir este usuario.'));
    exit;
}

$returnTo = isset($_POST['return_to']) ? trim((string) $_POST['return_to']) : '';
if ($returnTo !== '' && strpos($returnTo, '/') === 0) {
    header('Location: ' . $returnTo);
    exit;
}

header('Location: index.php?user=' . urlencode((string) $followed_id));
exit;
