<?php
declare(strict_types=1);



require_once dirname(__DIR__, 2) . '/config/bootstrap_path.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/csrf.php';
require_once CLUB61_ROOT . '/config/upload_validate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /features/profile/index.php');
    exit;
}

if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Sessão expirada. Atualize a página.'));
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$token = $_SESSION['access_token'] ?? '';

if ($userId === null || $userId === '' || $token === '') {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Sessão inválida. Faça login novamente.'));
    exit;
}

if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Configure SUPABASE_SERVICE_KEY em config/supabase.php para upload no Storage.'));
    exit;
}

$file = $_FILES['avatar'] ?? null;
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$check = club61_validate_image_upload(is_array($file) ? $file : null, 5 * 1024 * 1024, $allowed);
if (!$check['ok']) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode($check['error']));
    exit;
}

$mime = $check['mime'];
$filename = bin2hex(random_bytes(16)) . '.' . $check['ext'];
$binary = file_get_contents($file['tmp_name']);
if ($binary === false) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Não foi possível ler o arquivo.'));
    exit;
}

// Upload para Supabase Storage bucket avatars (header x-upsert: true sobrescreve arquivo existente)
$uploadUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/avatars/' . str_replace(['/', '\\'], '', $filename);

$ch = curl_init($uploadUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $binary,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: ' . $mime,
        'x-upsert: true',
    ],
]);
curl_exec($ch);
$storageCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($storageCode < 200 || $storageCode >= 300) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Erro ao salvar imagem no armazenamento.'));
    exit;
}

$avatarUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/avatars/' . $filename;

// Atualiza avatar_url na tabela profiles
$ch = curl_init(SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode((string) $userId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_POSTFIELDS => json_encode(['avatar_url' => $avatarUrl]),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Prefer: return=minimal',
    ],
]);
curl_exec($ch);
$patchCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($patchCode < 200 || $patchCode >= 300) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Imagem enviada, mas não foi possível atualizar o perfil.'));
    exit;
}

header('Location: /features/profile/index.php?status=ok&message=' . urlencode('Foto atualizada com sucesso!'));
exit;
