<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/session.php';
club61_session_bootstrap();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';
require_once CLUB61_ROOT . '/config/csrf.php';
require_once CLUB61_ROOT . '/config/upload_validate.php';
require_once CLUB61_ROOT . '/config/post_input.php';

$token = $_SESSION['access_token'] ?? null;
if (!$token) {
    die('Error: access_token not found in session');
}
if (!is_string($token) || $token === '') {
    die('Error: access_token not found in session');
}
if (substr_count($token, '.') !== 2) {
    die('Error: invalid JWT format');
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

$file = $_FILES['image'] ?? null;
$maxBytes = 5 * 1024 * 1024;
$allowedMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$check = club61_validate_image_upload(is_array($file) ? $file : null, $maxBytes, $allowedMap);
if (!$check['ok']) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode($check['error']));
    exit;
}

$mimeType = $check['mime'];
$filename = uniqid('post_', true) . '.' . $check['ext'];
$userId = $_SESSION['user_id'];
$binary = file_get_contents($file['tmp_name']);

if ($binary === false) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Falha ao ler a imagem.'));
    exit;
}

// TEMPORARY DEBUG: remove after validating JWT / Supabase Storage (runs before any cURL).
echo "<pre>";
echo "SESSION DATA:\n";
print_r($_SESSION);

$token = $_SESSION['access_token'] ?? null;

echo "\nTOKEN:\n";
var_dump($token);

if (!$token) {
    echo "\nERROR: access_token not found\n";
    exit;
}

if (substr_count($token, '.') !== 2) {
    echo "\nERROR: Invalid JWT format\n";
    exit;
}

echo "\nJWT FORMAT OK\n";
echo "</pre>";
exit;

$storageUrl = SUPABASE_URL . '/storage/v1/object/posts/' . $filename;
$storageCh = curl_init($storageUrl);
curl_setopt_array($storageCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $binary,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token,
        'apikey: ' . SUPABASE_ANON_KEY,
        'Content-Type: ' . $mimeType,
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
