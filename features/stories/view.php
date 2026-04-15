<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';

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
    . '&order=created_at.asc'
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

$storyRows = [];
if ($storyBody !== false && $storyCode >= 200 && $storyCode < 300) {
    $rows = json_decode($storyBody, true);
    if (is_array($rows)) {
        $storyRows = $rows;
    }
}

$slides = [];
foreach ($storyRows as $row) {
    if (!empty($row['image_url'])) {
        $slides[] = [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'image_url' => trim((string) $row['image_url']),
            'expires_at' => isset($row['expires_at']) ? (string) $row['expires_at'] : '',
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
        ];
    }
}
$nSlides = count($slides);
$slidesJson = json_encode($slides, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$profUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId) . '&select=avatar_url,display_id';
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
    $displayLabel = club61_display_id_label($sdisp);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stories — Club61</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            overflow: hidden;
            background: #0A0A0A;
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
        .progress-row {
            flex-shrink: 0;
            display: flex;
            gap: 4px;
            padding: 10px 10px 0;
            z-index: 20;
        }
        .progress-seg {
            flex: 1;
            height: 3px;
            background: rgba(255,255,255,0.22);
            border-radius: 2px;
            overflow: hidden;
        }
        .progress-seg-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #7B2EFF, #C9A84C);
            border-radius: 2px;
        }
        .progress-seg.is-done .progress-seg-fill { width: 100% !important; transition: none; }
        .progress-seg.is-active .progress-seg-fill {
            animation: seg-fill var(--seg-dur, 5s) linear forwards;
        }
        @keyframes seg-fill {
            from { width: 0%; }
            to { width: 100%; }
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
            border: 2px solid #7B2EFF;
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
            color: #C9A84C;
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
    <div class="story-root" id="storyRoot">
        <div class="progress-row" id="progressRow" aria-hidden="true"></div>
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
        <?php if ($nSlides > 0): ?>
        <div class="story-img-wrap">
            <img id="storyImg" src="" alt="Story">
        </div>
        <?php else: ?>
        <div class="story-empty">Nenhum story ativo ou expirado.</div>
        <?php endif; ?>
    </div>
    <script>
    (function () {
        var feed = '/features/feed/index.php';
        function goFeed() { window.location.href = feed; }
        document.getElementById('storyClose').addEventListener('click', goFeed);

        var slides = <?= $slidesJson ?>;
        if (!slides.length) return;

        var imgEl = document.getElementById('storyImg');
        var row = document.getElementById('progressRow');
        var idx = 0;
        var SEG_MS = 5000;

        function buildBars() {
            row.innerHTML = '';
            slides.forEach(function (_, i) {
                var seg = document.createElement('div');
                seg.className = 'progress-seg';
                seg.dataset.i = String(i);
                var fill = document.createElement('div');
                fill.className = 'progress-seg-fill';
                seg.appendChild(fill);
                row.appendChild(seg);
            });
        }

        function setActive(i) {
            var segs = row.querySelectorAll('.progress-seg');
            segs.forEach(function (el, j) {
                el.classList.remove('is-active', 'is-done');
                var f = el.querySelector('.progress-seg-fill');
                if (f) f.style.animation = 'none';
                if (j < i) el.classList.add('is-done');
            });
            if (i >= segs.length) {
                goFeed();
                return;
            }
            var cur = segs[i];
            cur.classList.add('is-active');
            var fill = cur.querySelector('.progress-seg-fill');
            void cur.offsetWidth;
            fill.style.animation = '';
            fill.style.animation = 'seg-fill ' + (SEG_MS / 1000) + 's linear forwards';

            if (imgEl && slides[i]) imgEl.src = slides[i].image_url;

            clearTimeout(setActive._t);
            setActive._t = setTimeout(function () {
                idx = i + 1;
                setActive(idx);
            }, SEG_MS);
        }

        buildBars();
        setActive(0);
    })();
    </script>
</body>
</html>
