<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';
require_once CLUB61_ROOT . '/config/csrf.php';
require_once CLUB61_ROOT . '/config/followers.php';
require_once CLUB61_ROOT . '/config/message_requests.php';

function club61_view_rel_label(string $stored): string
{
    $k = strtolower(trim($stored));
    $map = [
        'solteiro' => 'Solteiro',
        'solteira' => 'Solteira',
        'casal' => 'Casal',
        'casado' => 'Casado',
        'casada' => 'Casada',
        'namorando' => 'Namorando',
        'prefiro_nao_dizer' => 'Prefiro não dizer',
        'single' => 'Solteiro(a)',
        'couple' => 'Casal',
    ];

    return $map[$k] ?? $stored;
}

$access_token = $_SESSION['access_token'] ?? '';
$current_user_id = $_SESSION['user_id'] ?? null;

$view_user_id = '';
if (isset($_GET['id']) && trim((string) $_GET['id']) !== '') {
    $view_user_id = trim((string) $_GET['id']);
} elseif (isset($_GET['user_id']) && trim((string) $_GET['user_id']) !== '') {
    $view_user_id = trim((string) $_GET['user_id']);
} else {
    $view_user_id = (string) ($current_user_id ?? '');
}

if ($view_user_id === '') {
    header('Location: /features/profile/index.php');
    exit;
}

if ($current_user_id !== null && (string) $view_user_id === (string) $current_user_id) {
    header('Location: /features/profile/index.php');
    exit;
}

$profileRow = null;
$avatarUrl = '';
$profileBio = '';
$profileCidade = '';
$profileRelationship = '';
$posts = [];
$likeCounts = [];
$userLikedPostIds = [];

if ($access_token !== '') {
    $profile_url = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($view_user_id) . '&select=' . rawurlencode(CLUB61_PROFILE_REST_SELECT);
    $ch = curl_init($profile_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $access_token,
    ]);
    $profile_response = curl_exec($ch);
    $profile_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($profile_response !== false && $profile_http >= 200 && $profile_http < 300) {
        $decoded_profile = json_decode($profile_response, true);
        if (is_array($decoded_profile) && !empty($decoded_profile)) {
            $profileRow = $decoded_profile[0];
        }
    }
}

if (
    supabase_service_role_available()
    && $view_user_id !== ''
) {
    $svcOtherUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($view_user_id) . '&select=' . rawurlencode(CLUB61_PROFILE_REST_SELECT);
    $chO = curl_init($svcOtherUrl);
    curl_setopt($chO, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chO, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($chO, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($chO, CURLOPT_HTTPHEADER, array_merge(supabase_service_rest_headers(false), [
        'Accept: application/json',
    ]));
    curl_setopt($chO, CURLOPT_HTTPGET, true);
    $svcOB = curl_exec($chO);
    $svcOC = curl_getinfo($chO, CURLINFO_HTTP_CODE);
    curl_close($chO);
    if ($svcOB !== false && $svcOC >= 200 && $svcOC < 300) {
        $svcORows = json_decode($svcOB, true);
        if (is_array($svcORows) && !empty($svcORows[0])) {
            $o = $svcORows[0];
            if (!is_array($profileRow)) {
                $profileRow = [];
            }
            foreach (explode(',', CLUB61_PROFILE_REST_SELECT) as $k) {
                $k = trim($k);
                if (array_key_exists($k, $o) && $o[$k] !== null && trim((string) $o[$k]) !== '') {
                    $profileRow[$k] = $o[$k];
                }
            }
        }
    }
}

if (is_array($profileRow)) {
    if (isset($profileRow['avatar_url'])) {
        $avatarUrl = trim((string) $profileRow['avatar_url']);
    }
    if (isset($profileRow['bio']) && $profileRow['bio'] !== null) {
        $profileBio = trim((string) $profileRow['bio']);
    }
    if (array_key_exists('cidade', $profileRow) && $profileRow['cidade'] !== null) {
        $profileCidade = trim((string) $profileRow['cidade']);
    }
    if (isset($profileRow['relationship_status']) && $profileRow['relationship_status'] !== null) {
        $profileRelationship = trim((string) $profileRow['relationship_status']);
    }
}

$clLabel = is_array($profileRow)
    ? club61_display_id_label(isset($profileRow['display_id']) ? (string) $profileRow['display_id'] : null)
    : club61_display_id_label(null);

if ($access_token !== '') {
    $postsUrl = SUPABASE_URL . '/rest/v1/posts?user_id=eq.' . urlencode($view_user_id) . '&select=' . rawurlencode('id,image_url,caption,created_at') . '&order=created_at.desc';
    $ch = curl_init($postsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    if (supabase_service_role_available()) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(supabase_service_rest_headers(false), [
            'Accept: application/json',
        ]));
    } else {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $access_token,
        ]);
    }
    $postsBody = curl_exec($ch);
    $postsHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($postsBody !== false && $postsHttp >= 200 && $postsHttp < 300) {
        $decodedPosts = json_decode($postsBody, true);
        if (is_array($decodedPosts)) {
            $posts = $decodedPosts;
        }
    }
}

$postsWithImage = array_values(array_filter($posts, function ($p) {
    return isset($p['image_url']) && trim((string) $p['image_url']) !== '';
}));

$postIds = [];
foreach ($postsWithImage as $p) {
    if (isset($p['id'])) {
        $postIds[] = (int) $p['id'];
    }
}

if ($access_token !== '' && $postIds !== []) {
    $inList = implode(',', $postIds);
    $likesUrl = SUPABASE_URL . '/rest/v1/likes?select=post_id,user_id&post_id=in.(' . $inList . ')';
    $ch = curl_init($likesUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    if (supabase_service_role_available()) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(supabase_service_rest_headers(false), [
            'Accept: application/json',
        ]));
    } else {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $access_token,
        ]);
    }
    $likesBody = curl_exec($ch);
    $likesHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($likesBody !== false && $likesHttp >= 200 && $likesHttp < 300) {
        $likesRows = json_decode($likesBody, true);
        if (is_array($likesRows)) {
            foreach ($likesRows as $lr) {
                $pid = isset($lr['post_id']) ? (int) $lr['post_id'] : 0;
                if ($pid <= 0) {
                    continue;
                }
                if (!isset($likeCounts[$pid])) {
                    $likeCounts[$pid] = 0;
                }
                $likeCounts[$pid]++;
                if ($current_user_id !== null && isset($lr['user_id']) && (string) $lr['user_id'] === (string) $current_user_id) {
                    $userLikedPostIds[$pid] = true;
                }
            }
        }
    }
}

$postsCount = count($postsWithImage);
$statFollowers = followers_service_ok() ? getFollowersCount($view_user_id) : 0;
$statFollowing = followers_service_ok() ? getFollowingCount($view_user_id) : 0;
$isFollowingProfile = false;
if ($current_user_id !== null && followers_service_ok()) {
    $isFollowingProfile = followers_is_following((string) $current_user_id, (string) $view_user_id);
}
$mrBtn = 'hidden';
if ($current_user_id !== null) {
    $mrBtn = mr_profile_button_state((string) $current_user_id, (string) $view_user_id);
}

$headline = $clLabel;
$csrf = csrf_token();
$returnUrl = '/features/profile/view.php?id=' . rawurlencode($view_user_id);

$flash_status = isset($_GET['status']) ? (string) $_GET['status'] : '';
$flash_message = isset($_GET['message']) ? (string) $_GET['message'] : '';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil — Club61</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html { height: 100%; }
        body {
            margin: 0;
            min-height: 100%;
            background: #0A0A0A;
            color: #fff;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        nav {
            background: #111;
            border-bottom: 1px solid #222;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 56px;
            position: sticky;
            top: 0;
            z-index: 200;
        }
        .nav-brand {
            font-size: 1.2rem;
            font-weight: 800;
            color: #C9A84C;
            letter-spacing: 2px;
            text-decoration: none;
        }
        .nav-links {
            display: flex;
            gap: 24px;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .nav-links a {
            color: #888;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .nav-links a:hover { color: #C9A84C; }
        .page {
            max-width: 640px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }
        .profile-header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #222;
        }
        .avatar-wrap { flex-shrink: 0; }
        .avatar-ring {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 3px solid #333;
            overflow: hidden;
            background: #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .avatar-ring img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .avatar-fallback {
            font-size: 2.75rem;
            color: #7B2EFF;
        }
        .profile-meta { flex: 1; min-width: 0; }
        .ig-title-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 6px; }
        .profile-cl {
            font-size: clamp(1.2rem, 4vw, 1.5rem);
            font-weight: 700;
            letter-spacing: 0.02em;
            margin: 0;
            color: #fff;
        }
        .ig-handle-sub { margin: 0 0 10px; font-size: 0.88rem; color: #777; }
        .ig-stats {
            display: flex;
            gap: 16px;
            margin: 12px 0 14px;
            flex-wrap: wrap;
        }
        a.ig-stat-link { text-decoration: none; color: inherit; }
        a.ig-stat-link:hover .ig-stat-num { color: #C9A84C; }
        .ig-stat { text-align: center; min-width: 52px; }
        .ig-stat-num { font-weight: 700; font-size: 1rem; color: #fff; }
        .ig-stat-lbl { font-size: 0.62rem; color: #888; text-transform: uppercase; letter-spacing: 0.06em; margin-top: 2px; }
        .profile-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 4px; }
        .btn-follow {
            padding: 8px 18px;
            border: none;
            border-radius: 999px;
            background: #7B2EFF;
            color: #fff;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-follow.is-following {
            background: #1a1a1a;
            color: #b0b0b0;
            border: 1px solid #444;
        }
        .btn-follow:hover { filter: brightness(1.08); }
        .btn-dm-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid #333;
            background: #161616;
            color: #ccc;
            text-decoration: none;
            transition: border-color 0.15s ease, color 0.15s ease;
        }
        .btn-dm-icon:hover { border-color: #C9A84C; color: #C9A84C; }
        .btn-ig-msg {
            display: inline-block;
            padding: 7px 14px;
            font-size: 0.78rem;
            font-weight: 600;
            border-radius: 999px;
            border: 1px solid #333;
            background: #161616;
            color: #e0e0e0;
            text-decoration: none;
            font-family: inherit;
        }
        .btn-ig-msg--muted { opacity: 0.75; cursor: default; border-style: dashed; }
        .profile-cidade { font-size: 0.88rem; color: #C9A84C; margin: 8px 0 0; line-height: 1.4; }
        .ig-bio {
            font-size: 0.92rem;
            color: #ccc;
            line-height: 1.45;
            white-space: pre-wrap;
            word-break: break-word;
            margin: 0;
        }
        .ig-meta-line { font-size: 0.88rem; color: #888; margin-top: 8px; }
        .section-title {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #555;
            margin-bottom: 12px;
        }
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2px;
            background: #222;
            border: 1px solid #222;
            border-radius: 4px;
            overflow: hidden;
        }
        .grid-cell {
            position: relative;
            aspect-ratio: 1;
            padding: 0;
            margin: 0;
            border: none;
            cursor: pointer;
            overflow: hidden;
            background: #111;
            display: block;
        }
        .grid-cell img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            vertical-align: middle;
        }
        .grid-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.2s ease;
            pointer-events: none;
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
        }
        .grid-cell:hover .grid-overlay { opacity: 1; }
        .grid-overlay .heart { font-size: 1.25rem; }
        .posts-empty {
            text-align: center;
            color: #555;
            padding: 40px 16px;
            font-size: 0.95rem;
        }
        .post-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 300;
            background: rgba(0, 0, 0, 0.92);
            align-items: center;
            justify-content: center;
            padding: 56px 16px 32px;
            overflow-y: auto;
        }
        .post-modal.is-open { display: flex; }
        .post-modal-inner {
            position: relative;
            max-width: min(100vw - 32px, 480px);
            width: 100%;
        }
        .post-modal-close {
            position: fixed;
            top: 12px;
            right: 12px;
            width: 44px;
            height: 44px;
            border: none;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            font-size: 1.5rem;
            line-height: 1;
            cursor: pointer;
            z-index: 310;
        }
        .post-modal-close:hover { background: rgba(255, 255, 255, 0.22); }
        .post-modal-img {
            width: 100%;
            max-height: min(70vh, 520px);
            object-fit: contain;
            background: #0d0d0d;
            display: block;
            border-radius: 8px;
        }
        .post-modal-body {
            margin-top: 16px;
            padding: 0 4px;
        }
        .post-modal-caption {
            color: #ccc;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        .post-modal-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .like-count-badge {
            color: #888;
            font-size: 0.9rem;
        }
        .btn-like-modal, .btn-unlike-modal {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-like-modal {
            background: rgba(123, 46, 255, 0.2);
            color: #c4b5fd;
            border: 1px solid rgba(123, 46, 255, 0.35);
        }
        .btn-like-modal:hover { background: rgba(123, 46, 255, 0.35); }
        .btn-unlike-modal {
            background: rgba(255, 107, 107, 0.12);
            color: #ff6b6b;
            border: 1px solid rgba(255, 107, 107, 0.25);
        }
        .alert-flash {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .alert-flash.ok {
            background: rgba(47, 158, 68, 0.1);
            border: 1px solid rgba(47, 158, 68, 0.3);
            color: #69db7c;
        }
        .alert-flash.error {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.3);
            color: #ff6b6b;
        }
        #profile-network-stub { scroll-margin-top: 72px; }
        #profile-posts-grid { scroll-margin-top: 72px; }
    </style>
</head>
<body>
    <nav>
        <a class="nav-brand" href="/features/feed/index.php">Club61</a>
        <ul class="nav-links">
            <li><a href="/features/feed/index.php">Feed</a></li>
            <li><a href="/features/profile/index.php">Perfil</a></li>
            <li><a href="/features/auth/logout.php">Logout</a></li>
        </ul>
    </nav>

    <div class="page">
        <?php if ($flash_status === 'ok' && $flash_message !== ''): ?>
            <div class="alert-flash ok"><?= htmlspecialchars($flash_message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif ($flash_status === 'error' && $flash_message !== ''): ?>
            <div class="alert-flash error"><?= htmlspecialchars($flash_message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div class="profile-header">
            <div class="avatar-wrap">
                <div class="avatar-ring">
                    <?php if ($avatarUrl !== ''): ?>
                        <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                    <?php else: ?>
                        <span class="avatar-fallback" aria-hidden="true">&#128100;</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="profile-meta">
                <div class="ig-title-row">
                    <h1 class="profile-cl"><?= htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') ?></h1>
                </div>
                <div class="ig-stats" aria-label="Estatísticas">
                    <a class="ig-stat-link" href="#profile-posts-grid">
                        <div class="ig-stat">
                            <div class="ig-stat-num"><?= (int) $postsCount ?></div>
                            <div class="ig-stat-lbl">Posts</div>
                        </div>
                    </a>
                    <a class="ig-stat-link" href="#profile-network-stub">
                        <div class="ig-stat">
                            <div class="ig-stat-num" id="view-followers-count"><?= (int) $statFollowers ?></div>
                            <div class="ig-stat-lbl">Seguidores</div>
                        </div>
                    </a>
                    <a class="ig-stat-link" href="#profile-network-stub">
                        <div class="ig-stat">
                            <div class="ig-stat-num"><?= (int) $statFollowing ?></div>
                            <div class="ig-stat-lbl">Seguindo</div>
                        </div>
                    </a>
                </div>

                <?php if ($profileBio !== ''): ?>
                <p class="ig-bio"><?= htmlspecialchars($profileBio, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($profileCidade !== ''): ?>
                <p class="profile-cidade">📍 <?= htmlspecialchars($profileCidade, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($current_user_id !== null && (string) $view_user_id !== (string) $current_user_id): ?>
                <div class="profile-actions">
                    <button type="button" id="view-follow-btn" class="btn-follow<?= $isFollowingProfile ? ' is-following' : '' ?>"
                        data-following-id="<?= htmlspecialchars((string) $view_user_id, ENT_QUOTES, 'UTF-8') ?>"
                        data-following="<?= $isFollowingProfile ? '1' : '0' ?>"
                        aria-pressed="<?= $isFollowingProfile ? 'true' : 'false' ?>">
                        <?= $isFollowingProfile ? 'Seguindo' : 'Seguir' ?>
                    </button>
                    <?php if ($mrBtn === 'accepted'): ?>
                    <a class="btn-dm-icon" href="/features/chat/dm.php?to=<?= rawurlencode((string) $view_user_id) ?>" title="Mensagem" aria-label="Mensagem privada">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M4 5h16a2 2 0 012 2v10a2 2 0 01-2 2H4l-4 3V7a2 2 0 012-2z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    <?php elseif ($mrBtn !== 'hidden'): ?>
                        <?php if ($mrBtn === 'request'): ?>
                        <form action="/features/profile/message_request.php" method="post" style="display:inline">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="to_user" value="<?= htmlspecialchars((string) $view_user_id, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn-ig-msg">Pedir mensagem</button>
                        </form>
                        <?php elseif ($mrBtn === 'pending_sent'): ?>
                        <span class="btn-ig-msg btn-ig-msg--muted">Pedido enviado</span>
                        <?php elseif ($mrBtn === 'pending_inbox'): ?>
                        <a class="btn-ig-msg" href="/features/chat/message_requests_inbox.php">Responder pedido</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($profileRelationship !== ''): ?>
                <p class="ig-meta-line">
                    <?= htmlspecialchars(club61_view_rel_label($profileRelationship), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php endif; ?>
            </div>
        </div>

        <div id="profile-network-stub" tabindex="-1"></div>
        <div class="section-title" id="profile-posts-grid">Publicações</div>
        <?php if (empty($postsWithImage)): ?>
            <p class="posts-empty">Nenhuma publicação ainda.</p>
        <?php else: ?>
            <div class="posts-grid">
                <?php foreach ($postsWithImage as $gp): ?>
                    <?php

                    $gpid = (int) ($gp['id'] ?? 0);
                    $gimg = trim((string) ($gp['image_url'] ?? ''));
                    $gcaption = isset($gp['caption']) ? (string) $gp['caption'] : '';
                    $lc = isset($likeCounts[$gpid]) ? (int) $likeCounts[$gpid] : 0;
                    $liked = isset($userLikedPostIds[$gpid]);
                    ?>
                    <button type="button"
                        class="grid-cell"
                        data-post-id="<?= $gpid ?>"
                        data-src="<?= htmlspecialchars($gimg, ENT_QUOTES, 'UTF-8') ?>"
                        data-caption="<?= htmlspecialchars($gcaption, ENT_QUOTES, 'UTF-8') ?>"
                        data-likes="<?= $lc ?>"
                        data-liked="<?= $liked ? '1' : '0' ?>"
                        aria-label="Abrir publicação">
                        <img src="<?= htmlspecialchars($gimg, ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <span class="grid-overlay" aria-hidden="true">
                            <span class="heart">♥</span>
                            <span><?= $lc ?></span>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="postModal" class="post-modal" role="dialog" aria-modal="true" aria-label="Publicação" hidden>
        <button type="button" class="post-modal-close" aria-label="Fechar">&times;</button>
        <div class="post-modal-inner">
            <img id="postModalImg" class="post-modal-img" src="" alt="">
            <div class="post-modal-body">
                <p id="postModalCaption" class="post-modal-caption"></p>
                <div class="post-modal-actions">
                    <span id="postModalLikeCount" class="like-count-badge"></span>
                    <form id="formLikeModal" action="/features/feed/like.php" method="POST" style="display:inline">
                        <input type="hidden" name="post_id" id="modalPostIdLike" value="">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn-like-modal" id="btnModalLike">♥ Curtir</button>
                    </form>
                    <form id="formUnlikeModal" action="/features/feed/unlike_post.php" method="POST" style="display:none">
                        <input type="hidden" name="post_id" id="modalPostIdUnlike" value="">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn-unlike-modal" id="btnModalUnlike">✕ Descurtir</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var PROFILE_CSRF = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        var followBtn = document.getElementById('view-follow-btn');
        var followersEl = document.getElementById('view-followers-count');
        if (followBtn) {
            followBtn.addEventListener('click', function () {
                var fid = followBtn.getAttribute('data-following-id');
                if (!fid) return;
                followBtn.disabled = true;
                var fd = new FormData();
                fd.append('following_id', fid);
                fd.append('csrf', PROFILE_CSRF);
                fetch('/features/profile/follow_toggle.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d.csrf) PROFILE_CSRF = d.csrf;
                        if (!d.ok) return;
                        var follow = !!d.following;
                        followBtn.setAttribute('data-following', follow ? '1' : '0');
                        followBtn.setAttribute('aria-pressed', follow ? 'true' : 'false');
                        followBtn.textContent = follow ? 'Seguindo' : 'Seguir';
                        followBtn.classList.toggle('is-following', follow);
                        if (followersEl && typeof d.followers_count === 'number') {
                            followersEl.textContent = String(d.followers_count);
                        }
                    })
                    .catch(function () {})
                    .finally(function () { followBtn.disabled = false; });
            });
        }

        var modal = document.getElementById('postModal');
        var modalImg = document.getElementById('postModalImg');
        var modalCaption = document.getElementById('postModalCaption');
        var modalLikeCount = document.getElementById('postModalLikeCount');
        var formLike = document.getElementById('formLikeModal');
        var formUnlike = document.getElementById('formUnlikeModal');
        if (!modal || !modalImg) return;

        function openModal(btn) {
            var src = btn.getAttribute('data-src');
            var caption = btn.getAttribute('data-caption') || '';
            var likes = btn.getAttribute('data-likes') || '0';
            var liked = btn.getAttribute('data-liked') === '1';
            var pid = btn.getAttribute('data-post-id') || '';

            modalImg.src = src;
            modalCaption.textContent = caption;
            modalLikeCount.textContent = likes + ' curtida' + (parseInt(likes, 10) === 1 ? '' : 's');

            document.getElementById('modalPostIdLike').value = pid;
            document.getElementById('modalPostIdUnlike').value = pid;

            if (liked) {
                formLike.style.display = 'none';
                formUnlike.style.display = 'inline';
            } else {
                formLike.style.display = 'inline';
                formUnlike.style.display = 'none';
            }

            modal.removeAttribute('hidden');
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('hidden', '');
            modalImg.removeAttribute('src');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('.grid-cell').forEach(function (btn) {
            btn.addEventListener('click', function () { openModal(btn); });
        });

        var closeBtn = modal.querySelector('.post-modal-close');
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
        });
    })();
    </script>
</body>
</html>
