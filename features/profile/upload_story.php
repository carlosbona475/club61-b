<?php
require_once __DIR__ . '/../../auth_guard.php';
require_once __DIR__ . '/../../config/supabase.php';

$userId = $_SESSION['user_id'] ?? null;
$token = $_SESSION['access_token'] ?? '';

if ($userId === null || $userId === '' || $token === '') {
    header('Location: /features/auth/login.php');
    exit;
}

$status = isset($_GET['status']) ? (string) $_GET['status'] : '';
$message = isset($_GET['message']) ? (string) $_GET['message'] : '';

/**
 * @return string|null MIME ou null
 */
function upload_story_mime(string $tmpPath): ?string
{
    if (is_readable($tmpPath) && function_exists('mime_content_type')) {
        $m = @mime_content_type($tmpPath);
        if ($m && $m !== 'application/octet-stream') {
            return $m;
        }
    }
    if (is_readable($tmpPath) && class_exists('finfo')) {
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $m = $fi->file($tmpPath);
        if ($m) {
            return $m;
        }
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['story_image'] ?? null;

    if (!$file || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        header('Location: upload_story.php?status=error&message=' . urlencode('Erro ao enviar arquivo.'));
        exit;
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = upload_story_mime($file['tmp_name']);

    if ($mime === null || !isset($allowed[$mime])) {
        header('Location: upload_story.php?status=error&message=' . urlencode('Use JPG, PNG ou WEBP.'));
        exit;
    }

    if ($file['size'] > 8 * 1024 * 1024) {
        header('Location: upload_story.php?status=error&message=' . urlencode('Imagem muito grande. Máximo 8MB.'));
        exit;
    }

    $ext = $allowed[$mime];
    $uniq = bin2hex(random_bytes(8));
    $safeUser = preg_replace('/[^a-zA-Z0-9\-]/', '', (string) $userId);
    if ($safeUser === '') {
        $safeUser = 'user';
    }
    $filename = $safeUser . '_' . $uniq . '.' . $ext;

    $binary = file_get_contents($file['tmp_name']);
    if ($binary === false) {
        header('Location: upload_story.php?status=error&message=' . urlencode('Não foi possível ler o arquivo.'));
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
            'Authorization: Bearer ' . $token,
            'Content-Type: ' . $mime,
            'x-upsert: true',
        ],
    ]);
    $resBody = curl_exec($ch);
    $storageCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($storageCode < 200 || $storageCode >= 300) {
        $err = $resBody !== false && $resBody !== '' ? substr($resBody, 0, 200) : ('HTTP ' . $storageCode);
        header('Location: upload_story.php?status=error&message=' . urlencode('Storage: ' . $err));
        exit;
    }

    header('Location: upload_story.php?status=ok&message=' . urlencode('Story enviado com sucesso!'));
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

            <form method="post" action="upload_story.php" enctype="multipart/form-data">
                <input type="file" id="storyFile" name="story_image" accept="image/jpeg,image/png,image/webp" required>
                <button type="button" class="file-btn" id="chooseBtn" onclick="document.getElementById('storyFile').click()">📷 Toque para escolher imagem</button>
                <img id="preview" src="" alt="Prévia do story">
                <button class="btn-send" type="submit">Enviar story</button>
            </form>
            <p class="hint">JPG, PNG ou WEBP · até 8MB · crie o bucket <code style="color:#666">stories</code> no Supabase se ainda não existir.</p>
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
