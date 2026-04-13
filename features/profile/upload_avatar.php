<?php
declare(strict_types=1);

/**
 * GET: formulário simples para trocar avatar (abre a partir do perfil).
 * POST: envia ficheiro para Storage e PATCH em profiles.
 */

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/csrf.php';
require_once CLUB61_ROOT . '/config/upload_validate.php';

function club61_upload_avatar_safe_return(?string $raw): ?string
{
    if ($raw === null || $raw === '') {
        return null;
    }
    $t = trim($raw);
    if (!str_starts_with($t, '/') || str_starts_with($t, '//')) {
        return null;
    }
    if (strpbrk($t, "\r\n") !== false) {
        return null;
    }

    return $t;
}

$userId = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : '';
$token = isset($_SESSION['access_token']) ? trim((string) $_SESSION['access_token']) : '';
$csrf = csrf_token();

if ($userId === '' || $token === '') {
    header('Location: /features/auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $ret = isset($_GET['return_to']) ? club61_upload_avatar_safe_return((string) $_GET['return_to']) : null;
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar foto — Club61</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #0A0A0A; color: #eee; font-family: system-ui, sans-serif; padding: 24px 16px; }
        .card { max-width: 400px; margin: 0 auto; background: #111; border: 1px solid #222; border-radius: 12px; padding: 24px; }
        h1 { font-size: 1.1rem; color: #C9A84C; margin: 0 0 16px; }
        .btn { display: inline-block; padding: 12px 20px; background: #7B2EFF; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .back { display: inline-block; margin-top: 16px; color: #888; text-decoration: none; font-size: 0.9rem; }
        .back:hover { color: #C9A84C; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Nova foto de perfil</h1>
        <form action="upload_avatar.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($ret !== null): ?>
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($ret, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" required>
            <p style="margin-top:16px"><button type="submit" class="btn">Enviar</button></p>
        </form>
        <a class="back" href="<?= htmlspecialchars($ret ?? '/features/profile/index.php', ENT_QUOTES, 'UTF-8') ?>">← Voltar</a>
    </div>
</body>
</html>
    <?php
    exit;
}

if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Sessão expirada. Atualize a página.'));
    exit;
}

if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Configure SUPABASE_SERVICE_KEY em config/supabase.php para upload no Storage.'));
    exit;
}

$returnTo = club61_upload_avatar_safe_return(isset($_POST['return_to']) ? (string) $_POST['return_to'] : null);
$okTarget = $returnTo ?? '/features/profile/index.php';

$file = $_FILES['avatar'] ?? null;
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$check = club61_validate_image_upload(is_array($file) ? $file : null, 5 * 1024 * 1024, $allowed);
if (!$check['ok']) {
    $q = str_contains($okTarget, '?') ? '&' : '?';
    header('Location: ' . $okTarget . $q . 'status=error&message=' . urlencode($check['error']));
    exit;
}

$mime = $check['mime'];
$filename = bin2hex(random_bytes(16)) . '.' . $check['ext'];
$binary = file_get_contents($file['tmp_name']);
if ($binary === false) {
    $q = str_contains($okTarget, '?') ? '&' : '?';
    header('Location: ' . $okTarget . $q . 'status=error&message=' . urlencode('Não foi possível ler o arquivo.'));
    exit;
}

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
    $q = str_contains($okTarget, '?') ? '&' : '?';
    header('Location: ' . $okTarget . $q . 'status=error&message=' . urlencode('Erro ao salvar imagem no armazenamento.'));
    exit;
}

$avatarUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/avatars/' . $filename;

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
    $q = str_contains($okTarget, '?') ? '&' : '?';
    header('Location: ' . $okTarget . $q . 'status=error&message=' . urlencode('Imagem enviada, mas não foi possível atualizar o perfil.'));
    exit;
}

$q = str_contains($okTarget, '?') ? '&' : '?';
header('Location: ' . $okTarget . $q . 'status=ok&message=' . urlencode('Foto atualizada com sucesso!'));
exit;
