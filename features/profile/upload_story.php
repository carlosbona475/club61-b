<?php
declare(strict_types=1);



require_once dirname(__DIR__, 2) . '/config/bootstrap_path.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/csrf.php';
require_once CLUB61_ROOT . '/config/upload_validate.php';

$userId = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : '';
$token  = isset($_SESSION['access_token']) ? trim((string) $_SESSION['access_token']) : '';

if ($userId === '' || $token === '') {
    header('Location: /features/auth/login.php');
    exit;
}

if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    header('Location: /features/profile/upload_story.php?status=error&message=' . urlencode('Configure SUPABASE_SERVICE_KEY em config/supabase.php.'));
    exit;
}

$status = isset($_GET['status']) ? (string) $_GET['status'] : '';
$message = isset($_GET['message']) ? (string) $_GET['message'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        header('Location: /features/profile/upload_story.php?status=error&message=' . urlencode('Sessão expirada. Atualize a página.'));
        exit;
    }
    $file = $_FILES['story_image'] ?? null;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $check = club61_validate_image_upload(is_array($file) ? $file : null, 5 * 1024 * 1024, $allowed);
    if (!$check['ok']) {
        header('Location: /features/profile/upload_story.php?status=error&message=' . urlencode($check['error']));
        exit;
    }

    $mime = $check['mime'];
    $filename = bin2hex(random_bytes(16)) . '.' . $check['ext'];

    $binary = file_get_contents($file['tmp_name']);
    if ($binary === false) {
        header('Location: /features/profile/upload_story.php?status=error&message=' . urlencode('Não foi possível ler o arquivo.'));
        exit;
    }

    $uploadUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/stories/' . str_replace(['/', '\\'], '', $filename);

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
    $resBody = curl_exec($ch);
    $storageCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($storageCode < 200 || $storageCode >= 300) {
        $err = $resBody !== false && $resBody !== '' ? substr($resBody, 0, 200) : ('HTTP ' . $storageCode);
        header('Location: /features/profile/upload_story.php?status=error&message=' . urlencode('Storage: ' . $err));
        exit;
    }

    $publicUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/public/stories/' . $filename;

    $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify('+24 hours')
        ->format('Y-m-d\TH:i:s\Z');

    $insertPayload = json_encode([
        'user_id'    => (string) $userId,
        'image_url'  => $publicUrl,
        'expires_at' => $expiresAt,
    ], JSON_UNESCAPED_SLASHES);

    $chIns = curl_init(SUPABASE_URL . '/rest/v1/stories');
    curl_setopt_array($chIns, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $insertPayload,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ],
    ]);
    $insBody = curl_exec($chIns);
    $insCode = curl_getinfo($chIns, CURLINFO_HTTP_CODE);
    curl_close($chIns);

    if ($insCode < 200 || $insCode >= 300) {
        $errIns = $insBody !== false && $insBody !== '' ? $insBody : ('HTTP ' . $insCode);
        header('Location: /features/profile/upload_story.php?status=error&message=' . urlencode('ERRO INSERT stories | HTTP ' . $insCode . ' | ' . $errIns));
        exit;
    }

    header('Location: /features/profile/upload_story.php?status=ok&message=' . urlencode('Story enviado! Fica disponível por 24h.'));
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar story — Club61</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: #0A0A0A;
            color: #fff;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        nav {
            background: #111;
            border-bottom: 1px solid #222;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 56px;
        }
        nav a { color: #888; text-decoration: none; font-size: 0.9rem; }
        nav a:hover { color: #C9A84C; }
        .wrap {
            max-width: 420px;
            margin: 0 auto;
            padding: 32px 20px 48px;
        }
        h1 {
            font-size: 1.35rem;
            font-weight: 600;
            color: #C9A84C;
            text-align: center;
            margin: 0 0 8px;
            letter-spacing: 0.06em;
        }
        .sub {
            text-align: center;
            color: #888;
            font-size: 0.85rem;
            margin-bottom: 28px;
            line-height: 1.45;
        }
        .card {
            background: #111;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 28px 24px;
        }
        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 20px;
        }
        .alert.ok {
            color: #69db7c;
            background: rgba(47, 158, 68, 0.1);
            border: 1px solid rgba(47, 158, 68, 0.3);
        }
        .alert.error {
            color: #ff6b6b;
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.25);
        }
        #storyFile {
            display: none;
        }
        #chooseBtn.file-btn {
            display: block;
            width: 100%;
            background: #1a1a1a;
            border: 2px dashed #333;
            border-radius: 10px;
            padding: 36px 16px;
            text-align: center;
            color: #888;
            cursor: pointer;
            margin-bottom: 18px;
            transition: border-color 0.2s, color 0.2s;
            font: inherit;
            font-size: 1rem;
        }
        #chooseBtn.file-btn:hover {
            border-color: #7B2EFF;
            color: #ccc;
        }
        #preview {
            display: none;
            width: 100%;
            max-height: 320px;
            object-fit: contain;
            border-radius: 10px;
            margin-bottom: 18px;
            background: #0d0d0d;
            border: 1px solid #222;
        }
        .btn-send {
            width: 100%;
            padding: 14px 18px;
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            background: #7B2EFF;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: box-shadow 0.25s;
        }
        .btn-send:hover {
            box-shadow: 0 0 20px rgba(123, 46, 255, 0.45);
        }
        .hint { font-size: 0.75rem; color: #555; margin-top: 14px; text-align: center; }
    </style>
</head>
<body>
    <nav>
        <a href="/features/feed/index.php">← Feed</a>
        <a href="/features/profile/index.php">Perfil</a>
    </nav>
    <div class="wrap">
        <h1>Novo story</h1>
        <p class="sub">Escolha uma imagem da galeria. O arquivo vai para o bucket <strong>stories</strong> no Supabase.</p>

        <div class="card">
            <?php if ($status === 'ok'): ?>
                <div class="alert ok"><?php echo htmlspecialchars($message !== '' ? $message : 'Enviado.', ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif ($status === 'error'): ?>
                <div class="alert error"><?php echo htmlspecialchars($message !== '' ? $message : 'Erro ao enviar.', ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" action="/features/profile/upload_story.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="file" id="storyFile" name="story_image" accept="image/jpeg,image/png,image/webp" required>
                <button type="button" class="file-btn" id="chooseBtn" onclick="document.getElementById('storyFile').click()">📷 Toque para escolher imagem</button>
                <img id="preview" src="" alt="Prévia do story">
                <button class="btn-send" type="submit">Enviar story</button>
            </form>
            <p class="hint">JPG, PNG ou WEBP · até 5MB · visível por <strong>24 horas</strong> · bucket <code style="color:#666">stories</code> no Supabase.</p>
        </div>
    </div>
    <script>
    document.getElementById('storyFile').addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('preview').src = e.target.result;
                document.getElementById('preview').style.display = 'block';
                document.getElementById('chooseBtn').style.display = 'none';
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
    </script>
</body>
</html>
