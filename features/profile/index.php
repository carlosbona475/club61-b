<?php



declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';

/**
 * Rótulo em PT para exibição (valor salvo em minúsculas).
 */
function club61_relationship_label(string $stored): string
{
    $k = strtolower(trim($stored));
    $map = [
        'solteiro' => 'Solteiro',
        'solteira' => 'Solteira',
        'casal' => 'Casal',
        'casado' => 'Casado',
        'casada' => 'Casada',
        'single' => 'Solteiro(a)',
        'couple' => 'Casal',
    ];

    return $map[$k] ?? $stored;
}
require_once CLUB61_ROOT . '/config/csrf.php';
require_once CLUB61_ROOT . '/config/profile_stats.php';
require_once CLUB61_ROOT . '/config/message_requests.php';
require_once CLUB61_ROOT . '/config/followers.php';

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
$profileBio = '';
$profileAge = null;
$profileRelationship = '';
$profilePartnerAge = null;
$statPosts = 0;
$statLikesRecv = 0;
$statFollowersCount = 0;
$isFollowingProfile = false;
$mrBtn = 'hidden';
$profileUsername = '';
$is_admin = false;

$access_token = isset($_SESSION['access_token']) ? $_SESSION['access_token'] : '';

// Busca REST do perfil: select=display_id,username,avatar_url,tipo,cidade
// No próprio perfil a URL deve ser: .../profiles?id=eq.{ $current_user_id }&select=...
// Com ?user= (outro membro) usa-se o id desse perfil.
$profile_lookup_id = ($profile_user_id !== null && $profile_user_id !== '') ? (string) $profile_user_id : '';
$is_own_profile = $current_user_id !== null && (string) $profile_user_id === (string) $current_user_id;

if ($access_token !== '' && $profile_lookup_id !== '') {
    if ($is_own_profile) {
        $profile_url = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . $current_user_id . '&select=' . rawurlencode('display_id,username,avatar_url,tipo,cidade,bio,age,relationship_type,partner_age');
    } else {
        $profile_url = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . $profile_user_id . '&select=' . rawurlencode('display_id,username,avatar_url,tipo,cidade,bio,age,relationship_type,partner_age');
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
    $svcUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . $current_user_id . '&select=' . rawurlencode('display_id,username,avatar_url,tipo,cidade,bio,age,relationship_type,partner_age');
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
            foreach (['display_id', 'username', 'avatar_url', 'tipo', 'cidade', 'bio', 'age', 'relationship_type', 'partner_age'] as $k) {
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
    if (isset($profileRow['username'])) {
        $profileUsername = trim((string) $profileRow['username']);
    }
    if (isset($profileRow['tipo'])) {
        $profileTipo = trim((string) $profileRow['tipo']);
    }
    if (isset($profileRow['cidade'])) {
        $profileCidade = trim((string) $profileRow['cidade']);
    }
    if (isset($profileRow['bio']) && $profileRow['bio'] !== null) {
        $profileBio = trim((string) $profileRow['bio']);
    }
    if (isset($profileRow['age']) && $profileRow['age'] !== null && $profileRow['age'] !== '') {
        $profileAge = (int) $profileRow['age'];
    }
    if (isset($profileRow['relationship_type']) && $profileRow['relationship_type'] !== null) {
        $profileRelationship = trim((string) $profileRow['relationship_type']);
    }
    if (isset($profileRow['partner_age']) && $profileRow['partner_age'] !== null && $profileRow['partner_age'] !== '') {
        $profilePartnerAge = (int) $profileRow['partner_age'];
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

if (
    !$is_own_profile
    && $profile_lookup_id !== ''
    && supabase_service_role_available()
) {
    $svcOtherUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($profile_lookup_id) . '&select=' . rawurlencode('display_id,username,avatar_url,tipo,cidade,bio,age,relationship_type,partner_age');
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
            foreach (['display_id', 'username', 'avatar_url', 'tipo', 'cidade', 'bio', 'age', 'relationship_type', 'partner_age'] as $k) {
                if (array_key_exists($k, $o) && $o[$k] !== null && trim((string) $o[$k]) !== '') {
                    $profileRow[$k] = $o[$k];
                }
            }
            if (isset($o['bio'])) {
                $profileBio = trim((string) $o['bio']);
            }
            if (isset($o['username'])) {
                $profileUsername = trim((string) $o['username']);
            }
            if (isset($o['age']) && $o['age'] !== null && $o['age'] !== '') {
                $profileAge = (int) $o['age'];
            }
            if (isset($o['relationship_type'])) {
                $profileRelationship = trim((string) $o['relationship_type']);
            }
            if (isset($o['partner_age']) && $o['partner_age'] !== null && $o['partner_age'] !== '') {
                $profilePartnerAge = (int) $o['partner_age'];
            }
            if (isset($o['avatar_url']) && trim((string) $o['avatar_url']) !== '') {
                $avatarUrl = trim((string) $o['avatar_url']);
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
            }
        }
    }
}

if ($profile_lookup_id !== '' && profile_stats_service_ok()) {
    $statPosts = profile_count_posts($profile_lookup_id);
    $statLikesRecv = profile_count_likes_received($profile_lookup_id);
}

if ($profile_lookup_id !== '' && followers_service_ok()) {
    $statFollowersCount = getFollowersCount($profile_lookup_id);
}

if (
    !$is_own_profile
    && $current_user_id !== null
    && $profile_lookup_id !== ''
    && followers_service_ok()
) {
    $isFollowingProfile = followers_is_following((string) $current_user_id, (string) $profile_lookup_id);
}

if ($current_user_id !== null && $profile_lookup_id !== '') {
    $mrBtn = mr_profile_button_state((string) $current_user_id, (string) $profile_lookup_id);
}

$myPosts = array();
if (
    $access_token !== ''
    && $profile_lookup_id !== ''
) {
    $postsUrl = SUPABASE_URL . '/rest/v1/posts?user_id=eq.' . urlencode((string) $profile_lookup_id) . '&select=' . rawurlencode('id,image_url,caption') . '&order=created_at.desc';
    $ch = curl_init($postsUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    if (supabase_service_role_available()) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(supabase_service_rest_headers(false), array(
            'Accept: application/json',
        )));
    } else {
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $access_token,
        ));
    }
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

$csrf = csrf_token();
$bigHandle = $clLabel !== '' && $clLabel !== 'CL00' ? $clLabel : ($profileUsername !== '' ? '@' . $profileUsername : 'Membro');
$relLower = strtolower(trim($profileRelationship));
$allowedRelForm = ['solteiro', 'solteira', 'casal', 'casado', 'casada'];
$relSel = in_array($relLower, $allowedRelForm, true) ? $relLower : '';
if ($relSel === '' && $relLower === 'single') {
    $relSel = 'solteiro';
}
if ($relSel === '' && $relLower === 'couple') {
    $relSel = 'casal';
}
$showPartnerAge = ($relSel === 'casal' || $relLower === 'couple');

$igShowAge = $profileAge !== null;
$igShowCity = trim($cidade) !== '';
$igShowRel = trim($profileRelationship) !== '';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil — Club61</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous">
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
        .dark-btn { border-radius: 6px; }
        .ig-actions .btn-follow.dark-btn {
            display: inline-block;
            width: auto;
            margin-top: 0;
            padding: 8px 16px;
            font-size: 0.85rem;
        }
        .btn-follow.dark-btn.is-following {
            background: #1a1a1a;
            color: #b0b0b0;
            border-color: #444;
        }
        .btn-follow.dark-btn.is-following:hover {
            background: #252525;
            color: #e0e0e0;
            border-color: #555;
            box-shadow: 0 0 12px rgba(255, 255, 255, 0.06);
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
        /* Instagram-style header (tema escuro existente) */
        .ig-header {
            display: flex;
            gap: 22px;
            align-items: flex-start;
            text-align: left;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }
        .ig-avatar-col { flex-shrink: 0; }
        .ig-avatar-img {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #333;
            display: block;
            background: #1A1A1A;
        }
        .ig-avatar-fallback {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            border: 2px solid #333;
            background: #1A1A1A;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            color: #7B2EFF;
        }
        .ig-main-col { flex: 1; min-width: 0; }
        .ig-username {
            margin: 0 0 10px;
            font-size: clamp(1.35rem, 4vw, 1.65rem);
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.02em;
        }
        .ig-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; align-items: center; }
        .btn-ig-edit, .btn-ig-msg {
            display: inline-block;
            padding: 7px 16px;
            font-size: 0.78rem;
            font-weight: 600;
            border-radius: 999px;
            border: 1px solid #333;
            background: #161616;
            color: #e0e0e0;
            text-decoration: none;
            font-family: inherit;
            cursor: pointer;
        }
        .btn-ig-edit:hover, .btn-ig-msg:hover { border-color: #555; color: #fff; }
        .btn-ig-msg--muted { opacity: 0.75; cursor: default; border-style: dashed; }
        .ig-stats {
            display: flex;
            gap: 18px;
            margin-bottom: 12px;
            justify-content: flex-start;
        }
        .ig-stat { text-align: center; min-width: 56px; }
        .ig-stat-num { font-weight: 700; font-size: 1rem; color: #fff; }
        .ig-stat-lbl { font-size: 0.62rem; color: #888; text-transform: uppercase; letter-spacing: 0.06em; margin-top: 2px; }
        .ig-meta-line { font-size: 0.88rem; color: #bbb; margin-bottom: 8px; }
        .ig-bio { font-size: 0.9rem; color: #ccc; line-height: 1.45; white-space: pre-wrap; word-break: break-word; }
        .perfil-location-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px 14px;
            margin-bottom: 14px;
        }
        .location-save-status { font-size: 0.8rem; color: #888; }
        .form-perfil .btn-salvar-perfil { width: auto; margin-left: auto; display: block; padding: 10px 20px; font-size: 0.85rem; }
        .form-perfil textarea {
            width: 100%;
            margin-bottom: 14px;
            padding: 12px 14px;
            font-size: 0.9375rem;
            color: #fff;
            background: #1A1A1A;
            border: 1px solid #333333;
            border-radius: 4px;
            outline: none;
            min-height: 72px;
            resize: vertical;
            font-family: inherit;
        }
        .form-perfil textarea:focus { border-color: #7B2EFF; }
        .form-perfil input[type="number"] {
            width: 100%;
            margin-bottom: 14px;
            padding: 12px 14px;
            font-size: 0.9375rem;
            color: #fff;
            background: #1A1A1A;
            border: 1px solid #333333;
            border-radius: 4px;
        }
        #partner-age-wrap { display: none; }
        #partner-age-wrap.is-on { display: block; }
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
                <div class="ig-header">
                    <div class="ig-avatar-col">
                        <?php if ($avatarUrl !== ''): ?>
                        <img class="ig-avatar-img" src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <?php else: ?>
                        <div class="ig-avatar-fallback" aria-hidden="true">&#128100;</div>
                        <?php endif; ?>
                    </div>
                    <div class="ig-main-col">
                        <h2 class="ig-username"><?= htmlspecialchars($bigHandle, ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="ig-actions">
                            <?php if ($is_own_profile): ?>
                            <a class="btn-ig-edit" href="#form-perfil">Editar perfil</a>
                            <?php elseif (!$is_own_profile && $current_user_id !== null && $profile_lookup_id !== ''): ?>
                            <button type="button" id="profile-follow-btn" class="btn btn-follow dark-btn js-follow-toggle<?= $isFollowingProfile ? ' is-following' : '' ?>"
                                data-following-id="<?= htmlspecialchars((string) $profile_lookup_id, ENT_QUOTES, 'UTF-8') ?>"
                                data-following="<?= $isFollowingProfile ? '1' : '0' ?>"
                                aria-pressed="<?= $isFollowingProfile ? 'true' : 'false' ?>">
                                <?= $isFollowingProfile ? 'Seguindo' : 'Seguir' ?>
                            </button>
                            <?php endif; ?>
                            <?php if (!$is_own_profile && $mrBtn !== 'hidden'): ?>
                                <?php if ($mrBtn === 'request'): ?>
                                <form action="/features/profile/message_request.php" method="post" style="display:inline">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="to_user" value="<?= htmlspecialchars((string) $profile_lookup_id, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="return_to" value="<?= htmlspecialchars('/features/profile/index.php?user=' . rawurlencode((string) $profile_lookup_id), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn-ig-msg">Pedir mensagem</button>
                                </form>
                                <?php elseif ($mrBtn === 'pending_sent'): ?>
                                <span class="btn-ig-msg btn-ig-msg--muted" title="Aguardando resposta">Pedido enviado</span>
                                <?php elseif ($mrBtn === 'pending_inbox'): ?>
                                <a class="btn-ig-msg" href="/features/chat/message_requests_inbox.php">Responder pedido</a>
                                <?php elseif ($mrBtn === 'accepted'): ?>
                                <a class="btn-ig-msg" href="/features/chat/dm.php?with=<?= rawurlencode((string) $profile_lookup_id) ?>">Mensagem</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="ig-stats" aria-label="Estatísticas">
                            <div class="ig-stat">
                                <div class="ig-stat-num"><?= (int) $statPosts ?></div>
                                <div class="ig-stat-lbl">Posts</div>
                            </div>
                            <div class="ig-stat">
                                <div class="ig-stat-num"><?= (int) $statLikesRecv ?></div>
                                <div class="ig-stat-lbl">Curtidas</div>
                            </div>
                            <div class="ig-stat">
                                <div class="ig-stat-num" id="profile-followers-count" data-followers-count="<?= (int) $statFollowersCount ?>"><?= (int) $statFollowersCount ?></div>
                                <div class="ig-stat-lbl">Seguidores</div>
                            </div>
                        </div>
                        <?php if ($igShowAge || $igShowCity || $igShowRel): ?>
                        <p class="ig-meta-line">
                            <?php if ($igShowAge): ?><?= (int) $profileAge ?> anos<?php endif; ?>
                            <?php if ($igShowCity): ?>
                                <?php if ($igShowAge): ?> • <?php endif; ?>
                                <span class="ig-meta-geo"><i class="bi bi-geo-alt" aria-hidden="true"></i><?= htmlspecialchars(trim($cidade), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <?php if ($igShowRel): ?>
                                <?php if ($igShowAge || $igShowCity): ?> • <?php endif; ?>
                                <?= htmlspecialchars(club61_relationship_label($profileRelationship), ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                        <?php if ($profileBio !== ''): ?>
                        <p class="ig-bio"><?= htmlspecialchars($profileBio, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
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
                <?php if ((string) $profile_user_id === (string) $current_user_id && $current_user_id !== null): ?>
                <div class="avatar-stack" style="margin-bottom:12px">
                <form class="avatar-upload-form" action="upload_avatar.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="file" class="avatar-file-input" name="avatar" id="avatar-file-input" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" onchange="this.form.submit()">
                    <button type="button" class="btn-alterar-foto" onclick="document.getElementById('avatar-file-input').click();">Alterar foto</button>
                </form>
                </div>
                <?php endif; ?>
                <div class="profile-id"><?php echo htmlspecialchars($clLabel, ENT_QUOTES, 'UTF-8'); ?></div>

                <?php

                $tiposValidos = ['Homem', 'Mulher', 'Casal'];
                $tipoSelecionado = in_array($profileTipo, $tiposValidos, true) ? $profileTipo : '';
                ?>
                <?php if ((string) $profile_user_id === (string) $current_user_id && $current_user_id !== null): ?>
                <form id="form-perfil" class="form-perfil" action="update_profile.php" method="post" autocomplete="on">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <label for="perfil-bio">Bio</label>
                    <textarea id="perfil-bio" name="bio" rows="3" maxlength="2000" placeholder="Uma linha sobre você"><?= htmlspecialchars($profileBio, ENT_QUOTES, 'UTF-8') ?></textarea>
                    <label for="perfil-age">Idade</label>
                    <input id="perfil-age" type="number" name="age" min="18" max="120" placeholder="Ex.: 32" value="<?= $profileAge !== null ? (int) $profileAge : '' ?>">
                    <label for="perfil-rel">Relacionamento</label>
                    <select id="perfil-rel" name="relationship_type" required>
                        <option value="" disabled<?= $relSel === '' ? ' selected' : '' ?>>Selecione…</option>
                        <option value="solteiro"<?= $relSel === 'solteiro' ? ' selected' : '' ?>>Solteiro</option>
                        <option value="solteira"<?= $relSel === 'solteira' ? ' selected' : '' ?>>Solteira</option>
                        <option value="casal"<?= $relSel === 'casal' ? ' selected' : '' ?>>Casal</option>
                        <option value="casado"<?= $relSel === 'casado' ? ' selected' : '' ?>>Casado</option>
                        <option value="casada"<?= $relSel === 'casada' ? ' selected' : '' ?>>Casada</option>
                    </select>
                    <div id="partner-age-wrap" class="<?= $showPartnerAge ? 'is-on' : '' ?>">
                        <label for="perfil-partner-age">Idade do(a) parceiro(a)</label>
                        <input id="perfil-partner-age" type="number" name="partner_age" min="18" max="120" placeholder="Ex.: 30" value="<?= $profilePartnerAge !== null ? (int) $profilePartnerAge : '' ?>">
                    </div>
                    <label for="perfil-tipo">Tipo</label>
                    <select id="perfil-tipo" name="tipo" required>
                        <option value="" disabled<?php echo $tipoSelecionado === '' ? ' selected' : ''; ?>>Selecione…</option>
                        <option value="Homem"<?php echo $tipoSelecionado === 'Homem' ? ' selected' : ''; ?>>Homem</option>
                        <option value="Mulher"<?php echo $tipoSelecionado === 'Mulher' ? ' selected' : ''; ?>>Mulher</option>
                        <option value="Casal"<?php echo $tipoSelecionado === 'Casal' ? ' selected' : ''; ?>>Casal</option>
                    </select>
                    <label for="perfil-cidade">Cidade</label>
                    <input id="perfil-cidade" type="text" name="cidade" value="<?= htmlspecialchars($cidade, ENT_QUOTES, 'UTF-8') ?>" placeholder="Sua cidade" maxlength="120">
                    <div class="perfil-location-row">
                        <button type="button" class="btn-ig-edit" id="btn-usar-localizacao">Usar minha localização</button>
                        <span id="location-save-status" class="location-save-status" aria-live="polite"></span>
                    </div>
                    <button class="btn-salvar-perfil" type="submit">Salvar</button>
                </form>
                <?php endif; ?>

                <div class="posts-section">
                    <h2 class="section-head">Publicações</h2>
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

            </div>

            <?php if ($is_admin): ?>
            <div class="auth-card">
                <h2 class="section-head">Convites</h2>
                <p class="auth-sub auth-sub--tight">Gere códigos para novos membros.</p>

                <form class="form-invite" action="generate_invite.php" method="POST">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
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
        var PROFILE_CSRF = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        var followBtn = document.getElementById('profile-follow-btn');
        var followersEl = document.getElementById('profile-followers-count');
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
                            followersEl.setAttribute('data-followers-count', String(d.followers_count));
                        }
                    })
                    .catch(function () {})
                    .finally(function () { followBtn.disabled = false; });
            });
        }
        var rel = document.getElementById('perfil-rel');
        var pw = document.getElementById('partner-age-wrap');
        function syncPartner() {
            if (!rel || !pw) return;
            pw.classList.toggle('is-on', rel.value === 'casal');
        }
        if (rel) {
            rel.addEventListener('change', syncPartner);
            syncPartner();
        }
        var btnLoc = document.getElementById('btn-usar-localizacao');
        var locStatus = document.getElementById('location-save-status');
        if (btnLoc) {
            if (!navigator.geolocation) {
                btnLoc.disabled = true;
                if (locStatus) locStatus.textContent = 'Geolocalização não suportada neste navegador.';
            } else {
                btnLoc.addEventListener('click', function () {
                    if (locStatus) locStatus.textContent = 'A obter localização…';
                    btnLoc.disabled = true;
                    navigator.geolocation.getCurrentPosition(
                        function (pos) {
                            var fd = new FormData();
                            fd.append('latitude', String(pos.coords.latitude));
                            fd.append('longitude', String(pos.coords.longitude));
                            fd.append('csrf', PROFILE_CSRF);
                            fetch('/features/profile/save_location.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                                .then(function (r) { return r.json(); })
                                .then(function (d) {
                                    if (d.csrf) PROFILE_CSRF = d.csrf;
                                    if (d.ok) {
                                        if (locStatus) locStatus.textContent = 'Localização guardada.';
                                        try { localStorage.setItem('club61_location_updated_at', String(Date.now())); } catch (e) {}
                                    } else {
                                        if (locStatus) locStatus.textContent = (d.error === 'rate_limit') ? 'Aguarde um momento.' : 'Não foi possível guardar.';
                                    }
                                })
                                .catch(function () {
                                    if (locStatus) locStatus.textContent = 'Erro de rede.';
                                })
                                .finally(function () { btnLoc.disabled = false; });
                        },
                        function () {
                            if (locStatus) locStatus.textContent = 'Permissão negada ou localização indisponível.';
                            btnLoc.disabled = false;
                        },
                        { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
                    );
                });
            }
        }
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
