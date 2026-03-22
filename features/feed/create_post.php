<?php

require_once __DIR__ . '/../../auth_guard.php';
require_once __DIR__ . '/../../config/supabase.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /features/feed/index.php');
    exit;
}

$caption = trim($_POST['caption'] ?? '');
$file = $_FILES['image'] ?? null;

if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Envie uma imagem valida.'));
    exit;
}

$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$maxBytes = 5 * 1024 * 1024;

$mimeType = mime_content_type($file['tmp_name']);
if (!in_array($mimeType, $allowedMimeTypes, true)) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Formato de imagem nao permitido.'));
    exit;
}

if (($file['size'] ?? 0) > $maxBytes) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Imagem muito grande (max 5MB).'));
    exit;
}

$extensionMap = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

$filename = uniqid('post_', true) . '.' . $extensionMap[$mimeType];
$token = $_SESSION['access_token'];
$userId = $_SESSION['user_id'];
$binary = file_get_contents($file['tmp_name']);

if ($binary === false) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Falha ao ler a imagem.'));
    exit;
}

$storageUrl = SUPABASE_URL . '/storage/v1/object/posts/' . rawurlencode($filename);
$storageCh = curl_init($storageUrl);
curl_setopt_array($storageCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $binary,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: ' . $mimeType,
    ],
]);

$storageResponse = curl_exec($storageCh);
$storageStatusCode = curl_getinfo($storageCh, CURLINFO_HTTP_CODE);
$storageError = curl_error($storageCh);
curl_close($storageCh);

if ($storageResponse === false || $storageStatusCode < 200 || $storageStatusCode >= 300) {
    $errorMessage = 'Erro ao enviar imagem para o Storage.';
    if ($storageError !== '') {
        $errorMessage = 'Falha de comunicacao com o Storage.';
    }

    header('Location: /features/feed/index.php?status=error&message=' . urlencode($errorMessage));
    exit;
}

$payload = [
    'user_id' => $userId,
    'image_url' => SUPABASE_URL . '/storage/v1/object/public/posts/' . rawurlencode($filename),
    'caption' => $caption === '' ? null : $caption,
];

$ch = curl_init(SUPABASE_URL . '/rest/v1/posts');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $token,
        'Prefer: return=representation',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);

$rawResponse = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($rawResponse === false || $statusCode < 200 || $statusCode >= 300) {
    $errorMessage = 'Erro ao criar post.';
    if ($curlError !== '') {
        $errorMessage = 'Erro de comunicacao com Supabase.';
    }

    header('Location: /features/feed/index.php?status=error&message=' . urlencode($errorMessage));
    exit;
}

header('Location: /features/feed/index.php?status=ok&message=' . urlencode('Post criado com sucesso.'));
exit;
