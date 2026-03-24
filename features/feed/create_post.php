<?php

declare(strict_types=1);

require_once __DIR__ . '/../../auth_guard.php';
require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../config/profile_helper.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/upload_validate.php';
require_once __DIR__ . '/../../config/post_input.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /features/feed/index.php');
    exit;
}

if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Sessão expirada. Atualize a página.'));
    exit;
}

$caption = club61_normalize_post_caption($_POST['caption'] ?? null);

$file = $_FILES['image'] ?? null;
$maxBytes = 5 * 1024 * 1024;
$allowedMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$check = club61_validate_image_upload(is_array($file) ? $file : null, $maxBytes, $allowedMap);
if (!$check['ok']) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode($check['error']));
    exit;
}

$mimeType = $check['mime'];
$filename = bin2hex(random_bytes(16)) . '.' . $check['ext'];
$token = $_SESSION['access_token'];
$userId = $_SESSION['user_id'];
$binary = file_get_contents($file['tmp_name']);

if ($binary === false) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Falha ao ler a imagem.'));
    exit;
}

$storageUrl = SUPABASE_URL . '/storage/v1/object/posts/' . $filename;
$storageCh = curl_init($storageUrl);
curl_setopt_array($storageCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $binary,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => [
        'apikey: '         . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: '   . $mimeType,
        'x-upsert: true',
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

// user_id como string (UUID no Supabase Auth)
$payload = [
    'user_id' => (string) $userId,
    'image_url' => SUPABASE_URL . '/storage/v1/object/public/posts/' . $filename,
    'caption' => $caption === '' ? null : $caption,
];

// Mesmo padrão do Storage: service_role no servidor evita bloqueio por RLS no INSERT.
$ch = curl_init(SUPABASE_URL . '/rest/v1/posts');
if (supabase_service_role_available()) {
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => array_merge(supabase_service_rest_headers(true), [
            'Prefer: return=representation',
        ]),
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
} else {
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $token,
            'Prefer: return=representation',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
}

$rawResponse = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($rawResponse === false || $statusCode < 200 || $statusCode >= 300) {
    $errorMessage = 'Erro ao criar post. HTTP=' . $statusCode . ' | ' . substr((string) $rawResponse, 0, 400);
    if ($curlError !== '') {
        $errorMessage = 'Erro de comunicacao com Supabase.';
    }

    header('Location: /features/feed/index.php?status=error&message=' . urlencode($errorMessage));
    exit;
}

header('Location: /features/feed/index.php?status=ok&message=' . urlencode('Post criado com sucesso.'));
exit;
