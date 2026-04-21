<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';
require_once CLUB61_ROOT . '/config/csrf.php';
require_once CLUB61_ROOT . '/config/upload_validate.php';
require_once CLUB61_ROOT . '/config/post_input.php';

$token = $_SESSION['access_token'] ?? null;
if (!$token || !is_string($token) || $token === '') {
    header('Location: /features/auth/login.php');
    exit;
}
if (substr_count($token, '.') !== 2) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Sessão inválida. Faça login novamente.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /features/feed/index.php');
    exit;
}

if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Sessão expirada. Atualize a página.'));
    exit;
}

$caption = club61_normalize_post_caption($_POST['caption'] ?? null);

$userId = (string) ($_SESSION['user_id'] ?? '');
if ($userId === '') {
    header('Location: /features/auth/login.php');
    exit;
}

if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Configure SUPABASE_SERVICE_KEY no servidor.'));
    exit;
}

// Detecta se é vídeo ou imagem
$imageFile = $_FILES['image'] ?? null;
$videoFile = $_FILES['video'] ?? null;

$isVideo = false;
$imageUrl = null;
$videoUrl = null;

// --- VÍDEO ---
if (is_array($videoFile) && isset($videoFile['tmp_name']) && $videoFile['tmp_name'] !== '' && $videoFile['error'] === UPLOAD_ERR_OK) {
    $isVideo = true;
    $maxVideoBytes = 100 * 1024 * 1024; // 100MB
    $allowedVideoMimes = ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'];

    if ($videoFile['size'] > $maxVideoBytes) {
        header('Location: /features/feed/index.php?status=error&message=' . urlencode('Vídeo muito grande. Máximo 100MB.'));
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $videoFile['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedVideoMimes[$detectedMime])) {
        header('Location: /features/feed/index.php?status=error&message=' . urlencode('Formato de vídeo inválido. Use MP4, MOV ou WEBM.'));
        exit;
    }

    $ext = $allowedVideoMimes[$detectedMime];
    $filename = uniqid('video_', true) . '.' . $ext;
    $binary = file_get_contents($videoFile['tmp_name']);

    if ($binary === false) {
        header('Location: /features/feed/index.php?status=error&message=' . urlencode('Falha ao ler o vídeo.'));
        exit;
    }

    // Upload para bucket videos
    $storageUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/videos/' . $filename;
    $storageCh = curl_init($storageUrl);
    curl_setopt_array($storageCh, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $binary,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: ' . $detectedMime,
            'x-upsert: true',
        ],
    ]);
    $storageResponse = curl_exec($storageCh);
    $storageStatusCode = (int) curl_getinfo($storageCh, CURLINFO_HTTP_CODE);
    $storageError = curl_error($storageCh);
    curl_close($storageCh);

    if ($storageResponse === false || $storageStatusCode < 200 || $storageStatusCode >= 300) {
        $detail = is_string($storageResponse) ? substr($storageResponse, 0, 120) : '';
        $errorMessage = $storageError !== '' ? 'Falha de comunicação com o Storage.' : 'Erro ao enviar vídeo (HTTP ' . $storageStatusCode . '): ' . $detail;
        header('Location: /features/feed/index.php?status=error&message=' . urlencode($errorMessage));
        exit;
    }

    $videoUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/videos/' . $filename;

} else {
    // --- IMAGEM ---
    $maxBytes = 10 * 1024 * 1024; // 10MB
    $allowedMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $check = club61_validate_image_upload(is_array($imageFile) ? $imageFile : null, $maxBytes, $allowedMap);
    if (!$check['ok']) {
        header('Location: /features/feed/index.php?status=error&message=' . urlencode($check['error']));
        exit;
    }

    $mimeType = $check['mime'];
    $filename = uniqid('post_', true) . '.' . $check['ext'];
    $binary = file_get_contents($imageFile['tmp_name']);

    if ($binary === false) {
        header('Location: /features/feed/index.php?status=error&message=' . urlencode('Falha ao ler a imagem.'));
        exit;
    }

    $storageUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/posts/' . str_replace(['/', '\\'], '', $filename);
    $storageCh = curl_init($storageUrl);
    curl_setopt_array($storageCh, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $binary,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: ' . $mimeType,
            'x-upsert: true',
        ],
    ]);
    $storageResponse = curl_exec($storageCh);
    $storageStatusCode = (int) curl_getinfo($storageCh, CURLINFO_HTTP_CODE);
    $storageError = curl_error($storageCh);
    curl_close($storageCh);

    if ($storageResponse === false || $storageStatusCode < 200 || $storageStatusCode >= 300) {
        $detail = is_string($storageResponse) ? substr($storageResponse, 0, 120) : '';
        $errorMessage = $storageError !== '' ? 'Falha de comunicação com o Storage.' : 'Storage (HTTP ' . $storageStatusCode . '): ' . $detail;
        header('Location: /features/feed/index.php?status=error&message=' . urlencode($errorMessage));
        exit;
    }

    $imageUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/posts/' . $filename;
}

// Salva post no banco
$payload = [
    'user_id' => $userId,
    'caption' => $caption === '' ? null : $caption,
];
if ($imageUrl !== null) $payload['image_url'] = $imageUrl;
if ($videoUrl !== null) $payload['video_url'] = $videoUrl;

$ch = curl_init(SUPABASE_URL . '/rest/v1/posts');
if (supabase_service_role_available()) {
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge(supabase_service_rest_headers(true), ['Prefer: return=representation']),
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
} else {
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $token,
            'Prefer: return=representation',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
}

$rawResponse = curl_exec($ch);
$statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($rawResponse === false || $statusCode < 200 || $statusCode >= 300) {
    $errorMessage = 'Erro ao criar post. HTTP=' . $statusCode . ' | ' . substr((string) $rawResponse, 0, 400);
    if ($curlError !== '') {
        $errorMessage = 'Erro de comunicação com Supabase.';
    }
    header('Location: /features/feed/index.php?status=error&message=' . urlencode($errorMessage));
    exit;
}

header('Location: /features/feed/index.php?status=ok&message=' . urlencode('Post criado com sucesso.'));
exit;