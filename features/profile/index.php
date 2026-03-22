<?php

require_once __DIR__ . '/../../auth_guard.php';
require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../config/profile_helper.php';

$status = isset($_GET['status']) ? (string) $_GET['status'] : '';
$message = isset($_GET['message']) ? (string) $_GET['message'] : '';
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$profile_user_id = isset($_GET['user']) && $_GET['user'] !== '' ? $_GET['user'] : $current_user_id;

if ($profile_user_id === null || $profile_user_id === '') {
    $profile_user_id = $current_user_id;
}

$invites = array();
$profileRow = null;
$clLabel = 'CL00';
$avatarUrl = '';
$profileTipo = '';
$profileCidade = '';
$is_admin = false;

$access_token = isset($_SESSION['access_token']) ? $_SESSION['access_token'] : '';

// Busca REST do perfil: select=display_id,username,avatar_url,tipo,cidade
// No próprio perfil a URL deve ser: .../profiles?id=eq.{ $current_user_id }&select=...
// Com ?user= (outro membro) usa-se o id desse perfil.
$profile_lookup_id = ($profile_user_id !== null && $profile_user_id !== '') ? (string) $profile_user_id : '';
$is_own_profile = $current_user_id !== null && (string) $profile_user_id === (string) $current_user_id;

if ($access_token !== '' && $profile_lookup_id !== '') {
    if ($is_own_profile) {
        $profile_url = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . $current_user_id . '&select=display_id,username,avatar_url,tipo,cidade';
    } else {
        $profile_url = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . $profile_user_id . '&select=display_id,username,avatar_url,tipo,cidade';
    }
    $ch = curl_init($profile_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $access_token,
    ));
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

// display_id vazio no próprio perfil: atribuir CL sequencial (service_role no helper)
if (
    is_array($profileRow)
    && $current_user_id !== null
    && (string) $profile_user_id === (string) $current_user_id
) {
    $dcheck = isset($profileRow['display_id']) ? trim((string) $profileRow['display_id']) : '';
    if ($dcheck === '') {
        $assignedId = assignDisplayIdIfEmptyForUser((string) $current_user_id);
        if ($assignedId !== null) {
            $profileRow['display_id'] = $assignedId;
        }
    }
}

// Próprio perfil: reforçar dados (avatar, CL, tipo, cidade) via service_role se RLS ocultar campos no JWT
if (
    $current_user_id !== null
    && (string) $profile_user_id === (string) $current_user_id
    && supabase_service_role_available()
) {
    $svcUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . $current_user_id . '&select=display_id,username,avatar_url,tipo,cidade';
    $ch = curl_init($svcUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(supabase_service_rest_headers(false), [
        'Accept: application/json',
    ]));
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    $svcBody = curl_exec($ch);
    $svcHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($svcBody !== false && $svcHttp >= 200 && $svcHttp < 300) {
        $svcRows = json_decode($svcBody, true);
        if (is_array($svcRows) && !empty($svcRows[0])) {
            $svc = $svcRows[0];
            if (!is_array($profileRow)) {
                $profileRow = [];
            }
            foreach (['display_id', 'username', 'avatar_url', 'tipo', 'cidade'] as $k) {
                if (array_key_exists($k, $svc) && $svc[$k] !== null && trim((string) $svc[$k]) !== '') {
                    $profileRow[$k] = $svc[$k];
                }
            }
        }
    }
}

if (is_array($profileRow)) {
    if (isset($profileRow['avatar_url'])) {
        $avatarUrl = trim((string) $profileRow['avatar_url']);
    }
    if (isset($profileRow['tipo'])) {
        $profileTipo = trim((string) $profileRow['tipo']);
    }
    if (isset($profileRow['cidade'])) {
        $profileCidade = trim((string) $profileRow['cidade']);
    }
    $disp = isset($profileRow['display_id']) ? trim((string) $profileRow['display_id']) : '';
    if ($disp !== '') {
        $num = null;
        if (preg_match('/^CL\s*0*(\d+)$/i', $disp, $m)) {
            $num = (int) $m[1];
        } else {
            $digits = preg_replace('/\D/', '', $disp);
            if ($digits !== '') {
                $num = (int) $digits;
            }
        }
        if ($num !== null && $num > 0) {
            $clLabel = 'CL' . str_pad((string) min(999, $num), 2, '0', STR_PAD_LEFT);
        } else {
            $clLabel = 'CL00';
        }
    } else {
        $clLabel = 'CL00';
    }
}

$myPosts = array();
if (
    $access_token !== ''
    && $current_user_id !== null
    && (string) $profile_user_id === (string) $current_user_id
) {
    $postsUrl = SUPABASE_URL . '/rest/v1/posts?user_id=eq.' . $current_user_id . '&select=id,image_url,caption&order=created_at.desc';
    $ch = curl_init($postsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $access_token,
    ));
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    $postsBody = curl_exec($ch);
    $postsHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($postsBody !== false && $postsHttp >= 200 && $postsHttp < 300) {
        $decodedPosts = json_decode($postsBody, true);
        if (is_array($decodedPosts)) {
            $myPosts = $decodedPosts;
        }
    }
}

$myPostsForGrid = array_values(array_filter($myPosts, function ($p) {
    return isset($p['image_url']) && trim((string) $p['image_url']) !== '';
}));

if ($access_token !== '' && $current_user_id !== null && $current_user_id !== '') {
    $role_url = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode((string) $current_user_id) . '&select=' . rawurlencode('role');
    $ch = curl_init($role_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $access_token,
    ));
    $role_response = curl_exec($ch);
    $role_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($role_response !== false && $role_http >= 200 && $role_http < 300) {
        $decoded_role = json_decode($role_response, true);
        if (is_array($decoded_role) && !empty($decoded_role)) {
            $role_row = $decoded_role[0];
            if (isset($role_row['role']) && (string) $role_row['role'] === 'admin') {
                $is_admin = true;
            }
        }
    }
}

if ($is_admin && $access_token !== '' && $current_user_id !== null && $current_user_id !== '') {
    $url = SUPABASE_URL . '/rest/v1/invites?created_by=eq.' . urlencode((string) $current_user_id);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $access_token,
    ));
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response !== false && $statusCode >= 200 && $statusCode < 300) {
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            $invites = $decoded;
        }
    }
}

$cidade = $profileCidade;

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
            font-family: 'Segoe UI', system-ui, -apple-system, Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        nav {
            background: #111111;
            border-bottom: 1px solid #222222;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 60px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-brand {
            font-size: 1.3rem;
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
            flex-wrap: wrap;
        }
        .nav-links a {
            color: #888;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.15s ease;
        }
        .nav-links a:hover { color: #C9A84C; }
        .page {
            padding: 24px 16px 48px;
        }
        .auth-wrap {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
        }
        @media (max-width: 480px) {
            nav { padding: 0 16px; }
            .page { padding: 20px 14px 40px; }
        }
        .auth-card {
            background: #111111;
            border: 1px solid #222222;
            border-radius: 4px;
            padding: 40px 32px 36px;
            margin-bottom: 20px;
        }
        @media (max-width: 480px) {
            .auth-card { padding: 28px 20px 24px; }
        }
        .auth-brand {
            margin: 0 0 8px;
            font-size: clamp(1.5rem, 4vw, 1.85rem);
            font-weight: 600;
            letter-spacing: 0.06em;
            text-align: center;
            color: #C9A84C;
        }
        .auth-sub {
            margin: 0 0 28px;
            font-size: 0.8125rem;
            text-align: center;
            color: #888;
            line-height: 1.45;
        }
        .alert {
            margin-bottom: 20px;
            padding: 12px 14px;
            font-size: 0.8125rem;
            line-height: 1.45;
            border-radius: 4px;
        }
        .alert.ok {
            color: #69db7c;
            background: rgba(47, 158, 68, 0.08);
            border: 1px solid rgba(47, 158, 68, 0.25);
        }
        .alert.error {
            color: #FF6B6B;
            background: rgba(255, 107, 107, 0.08);
            border: 1px solid rgba(255, 107, 107, 0.25);
        }
        .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #1A1A1A;
            border: 3px solid rgba(123, 46, 255, 0.45);
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.25rem;
            color: #7B2EFF;
        }
        .avatar-stack {
            text-align: center;
            margin-bottom: 8px;
        }
        .avatar-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #7B2EFF;
            margin: 0 auto 12px;
            display: block;
        }
        .avatar-upload-form {
            margin: 0 0 8px;
        }
        .avatar-file-input {
            position: absolute;
            width: 0.1px;
            height: 0.1px;
            opacity: 0;
            overflow: hidden;
            z-index: -1;
        }
        .btn-alterar-foto {
            display: inline-block;
            margin: 0 auto 4px;
            padding: 10px 20px;
            font-size: 0.9375rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: #fff;
            background: #7B2EFF;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: box-shadow 0.25s ease, background 0.15s ease;
        }
        .btn-alterar-foto:hover {
            box-shadow: 0 0 20px rgba(123, 46, 255, 0.5), 0 0 40px rgba(123, 46, 255, 0.18);
        }
        .btn-alterar-foto:active { transform: translateY(1px); }
        .profile-id {
            font-size: 0.8125rem;
            color: #fff;
            word-break: break-all;
            text-align: center;
            margin-bottom: 20px;
            padding: 12px 14px;
            background: #1A1A1A;
            border: 1px solid #333333;
            border-radius: 4px;
        }
        .btn {
            display: block;
            width: 100%;
            margin-top: 8px;
            padding: 14px 18px;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: #fff;
            background: #7B2EFF;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: box-shadow 0.25s ease, background 0.15s ease;
        }
        .btn:hover {
            box-shadow: 0 0 24px rgba(123, 46, 255, 0.55), 0 0 48px rgba(123, 46, 255, 0.2);
        }
        .btn:active { transform: translateY(1px); }
        .btn-follow {
            background: rgba(123, 46, 255, 0.25);
            color: #e9e0ff;
            border: 1px solid rgba(123, 46, 255, 0.5);
        }
        .btn-follow:hover {
            background: #7B2EFF;
            color: #fff;
            border-color: #7B2EFF;
            box-shadow: 0 0 24px rgba(123, 46, 255, 0.55), 0 0 48px rgba(123, 46, 255, 0.2);
        }
        .follow-wrap { margin-top: 4px; }
        .section-head {
            margin: 0 0 8px;
            font-size: clamp(1.15rem, 3vw, 1.35rem);
            font-weight: 600;
            letter-spacing: 0.06em;
            text-align: center;
            color: #C9A84C;
        }
        .invite-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 12px 14px;
            margin-bottom: 10px;
            background: #1A1A1A;
            border: 1px solid #333333;
            border-radius: 4px;
        }
        .invite-code {
            font-family: ui-monospace, Consolas, monospace;
            font-size: 0.9rem;
            color: #C9A84C;
            letter-spacing: 0.06em;
        }
        .invite-status {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 4px;
            background: #111111;
            border: 1px solid #333333;
            color: #888;
        }
        .invite-status.available {
            color: #69db7c;
            border-color: rgba(47, 158, 68, 0.35);
            background: rgba(47, 158, 68, 0.1);
        }
        .empty {
            text-align: center;
            color: #888;
            font-size: 0.8125rem;
            padding: 16px 14px;
            border: 1px solid #333333;
            border-radius: 4px;
            background: #1A1A1A;
        }
        .form-invite { margin-bottom: 0; }
        .auth-sub--tight { margin-bottom: 20px !important; }
        .invite-list { margin-top: 20px; }
        .form-perfil {
            margin-top: 8px;
            text-align: left;
        }
        .form-perfil label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 6px;
        }
        .form-perfil select,
        .form-perfil input[type="text"] {
            width: 100%;
            margin-bottom: 14px;
            padding: 12px 14px;
            font-size: 0.9375rem;
            color: #fff;
            background: #1A1A1A;
            border: 1px solid #333333;
            border-radius: 4px;
            outline: none;
            transition: border-color 0.15s ease;
        }
        .form-perfil select:focus,
        .form-perfil input[type="text"]:focus {
            border-color: #7B2EFF;
        }
        .form-perfil select {
            cursor: pointer;
            appearance: auto;
        }
        .btn-salvar-perfil {
            display: block;
            width: 100%;
            margin-top: 4px;
            padding: 14px 18px;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: #fff;
            background: #7B2EFF;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: box-shadow 0.25s ease, background 0.15s ease;
        }
        .btn-salvar-perfil:hover {
            box-shadow: 0 0 24px rgba(123, 46, 255, 0.55), 0 0 48px rgba(123, 46, 255, 0.2);
        }
        .btn-salvar-perfil:active { transform: translateY(1px); }
        .perfil-boasvindas {
            font-size: 0.875rem;
            color: #ccc;
            text-align: center;
            line-height: 1.5;
            margin: -8px 0 20px;
            padding: 12px 14px;
            background: rgba(123, 46, 255, 0.08);
            border: 1px solid rgba(123, 46, 255, 0.25);
            border-radius: 4px;
        }
        .perfil-boasvindas strong { color: #e9e0ff; }
        .posts-section {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid #222222;
            text-align: left;
        }
        .posts-section .section-head {
            margin-bottom: 16px;
            font-size: 1rem;
        }
        .posts-empty {
            font-size: 0.8125rem;
            color: #666;
            text-align: center;
            padding: 20px 12px;
            border: 1px dashed #333333;
            border-radius: 4px;
            background: #151515;
        }
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2px;
            width: 100%;
        }
        .posts-grid-cell {
            position: relative;
            aspect-ratio: 1 / 1;
            width: 100%;
            padding: 0;
            margin: 0;
            border: none;
            cursor: pointer;
            overflow: hidden;
            background: #1a1a1a;
            display: block;
        }
        .posts-grid-cell img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            vertical-align: middle;
        }
        .posts-grid-cell:focus {
            outline: 2px solid #7B2EFF;
            outline-offset: 2px;
        }
        .post-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 300;
            background: rgba(0, 0, 0, 0.94);
            align-items: center;
            justify-content: center;
            padding: 48px 16px 32px;
        }
        .post-modal.is-open { display: flex; }
        .post-modal #postModalImg {
            max-width: min(100vw - 32px, 900px);
            max-height: calc(100vh - 120px);
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
        }
        .post-modal-inner {
            position: relative;
            max-width: min(100vw - 32px, 900px);
            max-height: calc(100vh - 96px);
        }
        .post-modal-inner img {
            max-width: 100%;
            max-height: calc(100vh - 120px);
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .post-modal-close {
            position: fixed;
            top: 16px;
            right: 16px;
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
        <div class="auth-wrap">
            <?php if ($status === 'ok'): ?>
                <div class="alert ok"><?php echo htmlspecialchars($message !== '' ? $message : 'Operação realizada.', ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif ($status === 'error'): ?>
                <div class="alert error"><?php echo htmlspecialchars($message !== '' ? $message : 'Erro ao processar.', ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="auth-card">
                <h1 class="auth-brand">Perfil</h1>
                <?php if (
                    (string) $profile_user_id === (string) $current_user_id
                    && $current_user_id !== null
                    && ($profileTipo !== '' || $cidade !== '')
                ): ?>
                <p class="perfil-boasvindas">
                    Bem-vindo!
                    <?php if ($profileTipo !== ''): ?> Tipo: <strong><?php echo htmlspecialchars($profileTipo, ENT_QUOTES, 'UTF-8'); ?></strong><?php endif; ?>
                    <?php if ($profileTipo !== '' && $cidade !== ''): ?> ·<?php endif; ?>
                    <?php if ($cidade !== ''): ?> Cidade: <strong><?php echo htmlspecialchars($cidade, ENT_QUOTES, 'UTF-8'); ?></strong><?php endif; ?>.
                </p>
                <?php endif; ?>
                <p class="auth-sub">Identificador do membro neste espaço.</p>
                <div class="avatar-stack">
                <?php if ($avatarUrl !== ''): ?>
                <img class="avatar-img" src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                <?php else: ?>
                <div class="avatar" aria-hidden="true">&#128100;</div>
                <?php endif; ?>
                <?php if ((string) $profile_user_id === (string) $current_user_id && $current_user_id !== null): ?>
                <form class="avatar-upload-form" action="upload_avatar.php" method="post" enctype="multipart/form-data">
                    <input type="file" class="avatar-file-input" name="avatar" id="avatar-file-input" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" onchange="this.form.submit()">
                    <button type="button" class="btn-alterar-foto" onclick="document.getElementById('avatar-file-input').click();">Alterar foto</button>
                </form>
                <?php endif; ?>
                </div>
                <div class="profile-id"><?php echo htmlspecialchars($clLabel, ENT_QUOTES, 'UTF-8'); ?></div>

                <?php
                $tiposValidos = ['Homem', 'Mulher', 'Casal'];
                $tipoSelecionado = in_array($profileTipo, $tiposValidos, true) ? $profileTipo : '';
                ?>
                <?php if ((string) $profile_user_id === (string) $current_user_id && $current_user_id !== null): ?>
                <form class="form-perfil" action="update_profile.php" method="post" autocomplete="on">
                    <label for="perfil-tipo">Tipo</label>
                    <select id="perfil-tipo" name="tipo" required>
                        <option value="" disabled<?php echo $tipoSelecionado === '' ? ' selected' : ''; ?>>Selecione…</option>
                        <option value="Homem"<?php echo $tipoSelecionado === 'Homem' ? ' selected' : ''; ?>>Homem</option>
                        <option value="Mulher"<?php echo $tipoSelecionado === 'Mulher' ? ' selected' : ''; ?>>Mulher</option>
                        <option value="Casal"<?php echo $tipoSelecionado === 'Casal' ? ' selected' : ''; ?>>Casal</option>
                    </select>
                    <label for="perfil-cidade">Cidade</label>
                    <input id="perfil-cidade" type="text" name="cidade" value="<?= htmlspecialchars($cidade, ENT_QUOTES, 'UTF-8') ?>" placeholder="Sua cidade" maxlength="120">
                    <button class="btn-salvar-perfil" type="submit">Salvar perfil</button>
                </form>

                <div class="posts-section">
                    <h2 class="section-head">Minhas publicações</h2>
                    <?php if (empty($myPostsForGrid)): ?>
                        <p class="posts-empty">Nenhuma publicação ainda.</p>
                    <?php else: ?>
                        <div class="posts-grid">
                            <?php foreach ($myPostsForGrid as $gp): ?>
                                <?php
                                $gimg = trim((string) $gp['image_url']);
                                ?>
                            <button type="button" class="posts-grid-cell" data-src="<?php echo htmlspecialchars($gimg, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Ampliar publicação">
                                <img src="<?php echo htmlspecialchars($gimg, ENT_QUOTES, 'UTF-8'); ?>" alt="">
                            </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php endif; ?>

                <?php if ((string) $profile_user_id !== (string) $current_user_id): ?>
                <div class="follow-wrap">
                    <form action="follow.php" method="POST">
                        <input type="hidden" name="followed_id" value="<?php echo htmlspecialchars((string) $profile_user_id, ENT_QUOTES, 'UTF-8'); ?>">
                        <button class="btn btn-follow" type="submit">+ Seguir</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($is_admin): ?>
            <div class="auth-card">
                <h2 class="section-head">Convites</h2>
                <p class="auth-sub auth-sub--tight">Gere códigos para novos membros.</p>

                <form class="form-invite" action="generate_invite.php" method="POST">
                    <button class="btn" type="submit">Gerar novo convite</button>
                </form>

                <div class="invite-list">
                    <?php if (empty($invites)): ?>
                        <div class="empty">Nenhum convite gerado ainda.</div>
                    <?php else: ?>
                        <?php foreach ($invites as $invite): ?>
                        <div class="invite-row">
                            <span class="invite-code"><?php echo htmlspecialchars(isset($invite['code']) ? (string) $invite['code'] : '', ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="invite-status<?php echo (isset($invite['status']) && (string) $invite['status'] === 'available') ? ' available' : ''; ?>">
                                <?php echo htmlspecialchars(isset($invite['status']) ? (string) $invite['status'] : 'indefinido', ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="postModal" class="post-modal" role="dialog" aria-modal="true" aria-label="Publicação ampliada" hidden>
        <button type="button" class="post-modal-close" aria-label="Fechar">&times;</button>
        <img id="postModalImg" src="" alt="">
    </div>
    <script>
    (function () {
        var modal = document.getElementById('postModal');
        var modalImg = document.getElementById('postModalImg');
        if (!modal || !modalImg) return;
        function openModal(src) {
            modalImg.src = src;
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
        document.querySelectorAll('.posts-grid-cell').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var src = btn.getAttribute('data-src');
                if (src) openModal(src);
            });
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
