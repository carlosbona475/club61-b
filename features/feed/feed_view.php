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
            'id' => isset($row['id']) ? (string) $row['id'] : '',
            'image_url' => trim((string) $row['image_url']),
            'expires_at' => isset($row['expires_at']) ? (string) $row['expires_at'] : '',
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
        ];
    }
}
$nSlides = count($slides);
$slidesJson = json_encode($slides, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$currentViewerId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
$isStoryOwner = $currentViewerId !== '' && $userId !== '' && $currentViewerId === $userId;

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
            padding: 0 8px 8px;
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
        .story-footer {
            flex-shrink: 0;
            padding: 8px 12px 16px;
            z-index: 20;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        /* Botão curtir story */
        .story-like-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
            color: #e8e8e8;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
            -webkit-tap-highlight-color: transparent;
        }
        .story-like-btn:hover { background: rgba(255,255,255,0.15); }
        .story-like-btn.ativo {
            background: rgba(255,60,100,0.2);
            border-color: rgba(255,60,100,0.5);
            color: #ff6b9d;
        }
        .story-like-btn .like-emoji { font-size: 1.2rem; transition: transform 0.2s; }
        .story-like-btn.ativo .like-emoji { transform: scale(1.2); }
        .story-like-count { font-size: 0.82rem; color: #aaa; }
        /* Botão visualizações (só owner) */
        .story-views-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border: none;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
            color: #e8e8e8;
            font-size: 0.85rem;
            cursor: pointer;
            transition: background 0.15s;
        }
        .story-views-btn:hover { background: rgba(255,255,255,0.2); }
        .story-views-modal {
            position: fixed;
            inset: 0;
            z-index: 100;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            background: rgba(0,0,0,0.65);
            padding: 16px;
        }
        .story-views-modal[hidden] { display: none !important; }
        .story-views-panel {
            width: 100%;
            max-width: 420px;
            max-height: 70vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: #141414;
            border-radius: 16px 16px 0 0;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .story-views-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            font-weight: 600;
            color: #fff;
        }
        .story-views-close {
            border: none;
            background: transparent;
            color: #aaa;
            font-size: 1.4rem;
            line-height: 1;
            cursor: pointer;
            padding: 4px 8px;
        }
        .story-views-list {
            overflow-y: auto;
            padding: 8px 0 16px;
        }
        .story-view-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
        }
        .story-view-av {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background: #222;
            flex-shrink: 0;
        }
        .story-view-av--ph {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        .story-view-name {
            font-size: 0.9rem;
            color: #eee;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
        <div class="story-footer">
            <?php if (!$isStoryOwner): ?>
            <!-- Botão curtir — visível para quem não é o dono -->
            <button type="button" class="story-like-btn" id="storyLikeBtn" aria-label="Curtir story">
                <span class="like-emoji">🤍</span>
                <span class="like-count" id="storyLikeCount">0</span>
            </button>
            <?php endif; ?>
            <?php if ($isStoryOwner): ?>
            <!-- Visualizações — só para o dono -->
            <button type="button" class="story-views-btn" id="storyViewsBtn" aria-haspopup="dialog">👁 0 visualizações</button>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="story-empty">Nenhum story ativo ou expirado.</div>
        <?php endif; ?>
    </div>

    <?php if ($nSlides > 0 && $isStoryOwner): ?>
    <div class="story-views-modal" id="storyViewsModal" hidden role="dialog" aria-modal="true" aria-labelledby="storyViewsTitle">
        <div class="story-views-panel">
            <div class="story-views-head">
                <span id="storyViewsTitle">Quem viu</span>
                <button type="button" class="story-views-close" id="storyViewsClose" aria-label="Fechar">×</button>
            </div>
            <div class="story-views-list" id="storyViewsList"></div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    (function () {
        var feed = '/features/feed/index.php';
        function goFeed() { window.location.href = feed; }
        document.getElementById('storyClose').addEventListener('click', goFeed);

        var slides = <?= $slidesJson ?>;
        if (!slides.length) return;

        var IS_STORY_OWNER = <?= $isStoryOwner ? 'true' : 'false' ?>;
        var CURRENT_VIEWER = <?= json_encode($currentViewerId) ?>;
        var imgEl = document.getElementById('storyImg');
        var row = document.getElementById('progressRow');
        var idx = 0;
        var SEG_MS = 5000;
        var viewsBtn = document.getElementById('storyViewsBtn');
        var viewsModal = document.getElementById('storyViewsModal');
        var viewsList = document.getElementById('storyViewsList');
        var viewsClose = document.getElementById('storyViewsClose');
        var likeBtn = document.getElementById('storyLikeBtn');
        var likeCountEl = document.getElementById('storyLikeCount');

        // --- CURTIDAS ---
        var likedStories = {}; // story_id => bool

        function loadLikes(storyId) {
            if (!storyId || IS_STORY_OWNER) return;
            fetch('/features/stories/get_likes.php?story_id=' + encodeURIComponent(storyId), {
                credentials: 'same-origin'
            })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d) return;
                var count = d.count || 0;
                var liked = d.liked || false;
                likedStories[storyId] = liked;
                if (likeCountEl) likeCountEl.textContent = count;
                if (likeBtn) {
                    likeBtn.classList.toggle('ativo', liked);
                    likeBtn.querySelector('.like-emoji').textContent = liked ? '❤️' : '🤍';
                }
            })
            .catch(function(){});
        }

        function toggleLike(storyId) {
            if (!storyId) return;
            fetch('/features/stories/toggle_like.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ story_id: storyId })
            })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d) return;
                likedStories[storyId] = d.liked;
                if (likeCountEl) likeCountEl.textContent = d.count || 0;
                if (likeBtn) {
                    likeBtn.classList.toggle('ativo', d.liked);
                    likeBtn.querySelector('.like-emoji').textContent = d.liked ? '❤️' : '🤍';
                }
            })
            .catch(function(){});
        }

        if (likeBtn) {
            likeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                var cur = slides[idx];
                if (cur && cur.id) toggleLike(cur.id);
            });
        }

        // --- VISUALIZAÇÕES ---
        function registerStoryView(storyId) {
            if (!storyId) return;
            fetch('/features/stories/register_view.php?story_id=' + encodeURIComponent(String(storyId)), {
                credentials: 'same-origin'
            });
        }

        function refreshViewsCount(storyId) {
            if (!IS_STORY_OWNER || !storyId || !viewsBtn) return;
            fetch('/features/stories/get_views.php?story_id=' + encodeURIComponent(String(storyId)), {
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var list = (d && d.views) ? d.views : [];
                viewsBtn.textContent = '\uD83D\uDC41 ' + list.length + ' visualiza\u00e7\u00e3o' + (list.length !== 1 ? 'es' : '');
            })
            .catch(function () {});
        }

        function openViewsModal(storyId) {
            if (!viewsModal || !viewsList || !storyId) return;
            viewsList.innerHTML = '';
            fetch('/features/stories/get_views.php?story_id=' + encodeURIComponent(String(storyId)), {
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var list = (d && d.views) ? d.views : [];
                list.forEach(function (v) {
                    var rowEl = document.createElement('div');
                    rowEl.className = 'story-view-row';
                    var av = document.createElement(v.avatar_url ? 'img' : 'div');
                    if (v.avatar_url) {
                        av.className = 'story-view-av';
                        av.src = v.avatar_url;
                        av.alt = '';
                    } else {
                        av.className = 'story-view-av story-view-av--ph';
                        av.setAttribute('aria-hidden', 'true');
                        av.textContent = '\uD83D\uDC64';
                    }
                    var nm = document.createElement('div');
                    nm.className = 'story-view-name';
                    nm.textContent = v.display_id || 'Membro';
                    rowEl.appendChild(av);
                    rowEl.appendChild(nm);
                    viewsList.appendChild(rowEl);
                });
                if (!list.length) {
                    var empty = document.createElement('div');
                    empty.style.cssText = 'padding:24px 16px;color:#888;text-align:center;font-size:0.9rem';
                    empty.textContent = 'Ningu\u00e9m viu este story ainda.';
                    viewsList.appendChild(empty);
                }
            })
            .catch(function () {
                viewsList.innerHTML = '<div style="padding:24px;color:#c44;text-align:center">N\u00e3o foi poss\u00edvel carregar.</div>';
            });
            viewsModal.removeAttribute('hidden');
        }

        function closeViewsModal() {
            if (viewsModal) viewsModal.setAttribute('hidden', '');
        }

        if (viewsBtn && viewsModal) {
            viewsBtn.addEventListener('click', function () {
                var cur = slides[idx];
                if (cur && cur.id) openViewsModal(cur.id);
            });
        }
        if (viewsClose) viewsClose.addEventListener('click', closeViewsModal);
        if (viewsModal) {
            viewsModal.addEventListener('click', function (e) {
                if (e.target === viewsModal) closeViewsModal();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && viewsModal && !viewsModal.hasAttribute('hidden')) closeViewsModal();
        });

        // --- SLIDES ---
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

            var sid = slides[i] && slides[i].id ? String(slides[i].id) : '';
            registerStoryView(sid);
            refreshViewsCount(sid);
            loadLikes(sid);

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