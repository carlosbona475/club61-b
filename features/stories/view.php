<?php


declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';

$viewerToken = $_SESSION['access_token'] ?? '';
if ($viewerToken === '') {
    header('Location: /features/auth/login.php');
    exit;
}

$userId = isset($_GET['user_id']) ? trim((string) $_GET['user_id']) : '';
if ($userId === '') {
    header('Location: /features/feed/index.php');
    exit;
}

$sk = defined('SUPABASE_SERVICE_KEY') && SUPABASE_SERVICE_KEY !== '' ? SUPABASE_SERVICE_KEY : null;
$useHeaders = $sk !== null
    ? [
        'apikey: ' . $sk,
        'Authorization: Bearer ' . $sk,
        'Accept: application/json',
    ]
    : [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $viewerToken,
        'Accept: application/json',
    ];

$nowIso = gmdate('Y-m-d\TH:i:s\Z');
$storyUrl = SUPABASE_URL . '/rest/v1/stories?user_id=eq.' . urlencode($userId)
    . '&expires_at=gt.' . urlencode($nowIso)
    . '&order=created_at.desc&limit=1'
    . '&select=id,image_url,expires_at,created_at';

$ch = curl_init($storyUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $useHeaders);
curl_setopt($ch, CURLOPT_HTTPGET, true);
$storyBody = curl_exec($ch);
$storyCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$storyRow = null;
if ($storyBody !== false && $storyCode >= 200 && $storyCode < 300) {
    $rows = json_decode($storyBody, true);
    if (is_array($rows) && !empty($rows[0])) {
        $storyRow = $rows[0];
    }
}

$imageUrl = '';
if (is_array($storyRow) && !empty($storyRow['image_url'])) {
    $imageUrl = trim((string) $storyRow['image_url']);
}

$profUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId) . '&select=avatar_url,display_id,username';
$ch = curl_init($profUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $useHeaders);
curl_setopt($ch, CURLOPT_HTTPGET, true);
$profBody = curl_exec($ch);
curl_close($ch);

$avatarUrl = '';
$displayLabel = '';
$profRows = json_decode($profBody !== false ? $profBody : '[]', true);
if (is_array($profRows) && !empty($profRows[0])) {
    $pr = $profRows[0];
    if (!empty($pr['avatar_url'])) {
        $avatarUrl = trim((string) $pr['avatar_url']);
    }
    $sdisp = isset($pr['display_id']) ? trim((string) $pr['display_id']) : '';
    if ($sdisp !== '') {
        $num = null;
        if (preg_match('/^CL\s*0*(\d+)$/i', $sdisp, $m)) {
            $num = (int) $m[1];
        } else {
            $digits = preg_replace('/\D/', '', $sdisp);
            if ($digits !== '') {
                $num = (int) $digits;
            }
        }
        if ($num !== null && $num > 0) {
            $displayLabel = 'CL' . str_pad((string) min(999, $num), 2, '0', STR_PAD_LEFT);
        }
    }
    if ($displayLabel === '' && !empty($pr['username'])) {
        $displayLabel = '@' . trim((string) $pr['username']);
    }
    if ($displayLabel === '') {
        $displayLabel = 'Membro';
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Story — Club61</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            overflow: hidden;
            background: #000;
            color: #fff;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .story-root {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            background: #000;
        }
        .progress-wrap {
            flex-shrink: 0;
            padding: 10px 12px 0;
            z-index: 20;
        }
        .progress-track {
            height: 3px;
            background: rgba(255,255,255,0.2);
            border-radius: 2px;
            overflow: hidden;
            direction: ltr;
        }
        .progress-fill {
            height: 100%;
            width: 100%;
            background: #fff;
            animation: story-progress-drain 5s linear forwards;
        }
        @keyframes story-progress-drain {
            from { width: 100%; }
            to { width: 0%; }
        }
        .story-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px 12px;
            z-index: 20;
        }
        .story-user {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .story-user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.25);
            flex-shrink: 0;
            background: #1a1a1a;
        }
        .story-user-avatar--ph {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: #7B2EFF;
        }
        .story-user-id {
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .story-close {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 50%;
            background: rgba(255,255,255,0.12);
            color: #fff;
            font-size: 1.35rem;
            line-height: 1;
            cursor: pointer;
            flex-shrink: 0;
            transition: background 0.15s;
        }
        .story-close:hover { background: rgba(255,255,255,0.22); }
        .story-img-wrap {
            flex: 1;
            min-height: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 8px 24px;
        }
        .story-img-wrap img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .story-empty {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #888;
            font-size: 0.95rem;
            padding: 24px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="story-root">
        <div class="progress-wrap">
            <div class="progress-track">
                <div class="progress-fill"></div>
            </div>
        </div>
        <div class="story-top">
            <div class="story-user">
                <?php if ($avatarUrl !== ''): ?>
                    <img class="story-user-avatar" src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                <?php else: ?>
                    <div class="story-user-avatar story-user-avatar--ph" aria-hidden="true">&#128100;</div>
                <?php endif; ?>
                <span class="story-user-id"><?= htmlspecialchars($displayLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button type="button" class="story-close" id="storyClose" aria-label="Fechar">×</button>
        </div>
        <?php if ($imageUrl !== ''): ?>
        <div class="story-img-wrap">
            <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Story">
        </div>
        <?php else: ?>
        <div class="story-empty">Story não encontrado ou expirado.</div>
        <?php endif; ?>
    </div>
    <script>
    (function () {
        var feed = '/features/feed/index.php';
        function goFeed() { window.location.href = feed; }
        document.getElementById('storyClose').addEventListener('click', goFeed);
        setTimeout(goFeed, 5000);
    })();
    </script>
</body>
</html>
