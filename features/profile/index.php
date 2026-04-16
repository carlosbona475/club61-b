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
        'namorando' => 'Namorando',
        'prefiro_nao_dizer' => 'Prefiro não dizer',
        'single' => 'Solteiro(a)',
        'couple' => 'Casal',
    ];

    return $map[$k] ?? $stored;
}
require_once CLUB61_ROOT . '/config/csrf.php';
require_once CLUB61_ROOT . '/config/profile_stats.php';
require_once CLUB61_ROOT . '/config/message_requests.php';
require_once CLUB61_ROOT . '/config/followers.php';
require_once CLUB61_ROOT . '/config/follows_status.php';
require_once CLUB61_ROOT . '/config/feed_interactions.php';
require_once CLUB61_ROOT . '/config/direct_messages_helper.php';

$status = isset($_GET['status']) ? (string) $_GET['status'] : '';
$message = isset($_GET['message']) ? (string) $_GET['message'] : '';
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$dmUnread = ($current_user_id !== null && (string) $current_user_id !== '') ? club61_dm_unread_count((string) $current_user_id) : 0;
$profile_user_id = isset($_GET['user']) && $_GET['user'] !== '' ? $_GET['user'] : $current_user_id;

if ($profile_user_id === null || $profile_user_id === '') {
    $profile_user_id = $current_user_id;
}

$invites = array();
$profileRow = null;
$avatarUrl = '';
$profileBio = '';
$profileAge = null;
$profileRelationship = '';
$cidade = '';
$statPosts = 0;
$statLikesRecv = 0;
$statFollowersCount = 0;
$statFollowingCount = 0;
$isFollowingProfile = false;
$followUiState = 'none';
$mrBtn = 'hidden';
$is_admin = false;

$access_token = isset($_SESSION['access_token']) ? $_SESSION['access_token'] : '';

// Busca REST do perfil: display_id público (CL01…), sem username na UI
// No próprio perfil a URL deve ser: .../profiles?id=eq.{ $current_user_id }&select=...
// Com ?user= (outro membro) usa-se o id desse perfil.
$profile_lookup_id = ($profile_user_id !== null && $profile_user_id !== '') ? (string) $profile_user_id : '';
/** @var bool Definido após carregar perfil (UUID); antes disso usa-se $fetch_self_profile só no 1.º GET. */
$is_own_profile = false;
$fetch_self_profile = $current_user_id !== null && (string) $profile_user_id === (string) $current_user_id;

if ($access_token !== '' && $profile_lookup_id !== '') {
    if ($fetch_self_profile) {
        $profile_url = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . $current_user_id . '&select=' . rawurlencode(CLUB61_PROFILE_REST_SELECT);
    } else {
        $profile_url = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . $profile_user_id . '&select=' . rawurlencode(CLUB61_PROFILE_REST_SELECT);
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

// ?user=CL01… não é UUID: resolver via display_id (posts/follows usam user_id uuid)
if (
    (!is_array($profileRow) || empty($profileRow))
    && $profile_lookup_id !== ''
    && supabase_service_role_available()
) {
    $dispQ = strtoupper(trim($profile_lookup_id));
    if (preg_match('/^CL\d+$/', $dispQ)) {
        $dispUrl = SUPABASE_URL . '/rest/v1/profiles?display_id=eq.' . rawurlencode($dispQ) . '&select=' . rawurlencode(CLUB61_PROFILE_REST_SELECT);
        $chD = curl_init($dispUrl);
        curl_setopt_array($chD, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => array_merge(supabase_service_rest_headers(false), ['Accept: application/json']),
            CURLOPT_HTTPGET => true,
        ]);
        $dispBody = curl_exec($chD);
        $dispHttp = curl_getinfo($chD, CURLINFO_HTTP_CODE);
        curl_close($chD);
        if ($dispBody !== false && $dispHttp >= 200 && $dispHttp < 300) {
            $dispRows = json_decode($dispBody, true);
            if (is_array($dispRows) && !empty($dispRows[0])) {
                $profileRow = $dispRows[0];
            }
        }
    }
}

if (is_array($profileRow) && isset($profileRow['id']) && trim((string) $profileRow['id']) !== '') {
    $profile_lookup_id = (string) $profileRow['id'];
}
$is_own_profile = $current_user_id !== null && $profile_lookup_id !== '' && (string) $profile_lookup_id === (string) $current_user_id;

// display_id vazio no próprio perfil: atribuir CL sequencial (service_role no helper)
if (
    is_array($profileRow)
    && $current_user_id !== null
    && $is_own_profile
) {
    $dcheck = isset($profileRow['display_id']) ? trim((string) $profileRow['display_id']) : '';
    if ($dcheck === '') {
        $assignedId = assignDisplayIdIfEmptyForUser((string) $current_user_id);
        if ($assignedId !== null) {
            $profileRow['display_id'] = $assignedId;
        }
    }
}

// Próprio perfil: reforçar dados via service_role se RLS ocultar campos no JWT
if (
    $current_user_id !== null
    && $is_own_profile
    && supabase_service_role_available()
) {
    $svcUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . $current_user_id . '&select=' . rawurlencode(CLUB61_PROFILE_REST_SELECT);
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
            foreach (explode(',', CLUB61_PROFILE_REST_SELECT) as $k) {
                $k = trim($k);
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
    if (isset($profileRow['bio']) && $profileRow['bio'] !== null) {
        $profileBio = trim((string) $profileRow['bio']);
    }
    if (isset($profileRow['age']) && $profileRow['age'] !== null && $profileRow['age'] !== '') {
        $profileAge = (int) $profileRow['age'];
    }
    if (isset($profileRow['relationship_type']) && $profileRow['relationship_type'] !== null) {
        $profileRelationship = trim((string) $profileRow['relationship_type']);
    }
    if (array_key_exists('cidade', $profileRow) && $profileRow['cidade'] !== null) {
        $cidade = trim((string) $profileRow['cidade']);
    }
}

if (
    !$is_own_profile
    && $profile_lookup_id !== ''
    && supabase_service_role_available()
) {
    $svcOtherUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($profile_lookup_id) . '&select=' . rawurlencode(CLUB61_PROFILE_REST_SELECT);
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
            if (isset($o['bio'])) {
                $profileBio = trim((string) $o['bio']);
            }
            if (isset($o['age']) && $o['age'] !== null && $o['age'] !== '') {
                $profileAge = (int) $o['age'];
            }
            if (isset($o['relationship_type'])) {
                $profileRelationship = trim((string) $o['relationship_type']);
            }
            if (array_key_exists('cidade', $o)) {
                $cidade = $o['cidade'] === null ? '' : trim((string) $o['cidade']);
            }
            if (isset($o['avatar_url']) && trim((string) $o['avatar_url']) !== '') {
                $avatarUrl = trim((string) $o['avatar_url']);
            }
        }
    }
}

$clLabel = is_array($profileRow)
    ? club61_display_id_label(isset($profileRow['display_id']) ? (string) $profileRow['display_id'] : null)
    : club61_display_id_label(null);

$pendingFollowCount = 0;
$pendingFollowRows = [];
if ($profile_lookup_id !== '' && club61_follows_service_ok()) {
    $statFollowersCount = club61_follows_count_followers_accepted($profile_lookup_id);
    $statFollowingCount = club61_follows_count_following_accepted($profile_lookup_id);
    if (!$is_own_profile && $current_user_id !== null) {
        $followUiState = club61_follows_relation_state((string) $current_user_id, $profile_lookup_id);
        $isFollowingProfile = ($followUiState === 'aceito');
    }
    if ($is_own_profile && $current_user_id !== null) {
        $pendingFollowCount = club61_follows_pending_incoming_count((string) $current_user_id);
        $pendingFollowRows = club61_follows_pending_incoming_list((string) $current_user_id, 30);
    }
} elseif ($profile_lookup_id !== '' && followers_service_ok()) {
    $statFollowersCount = getFollowersCount($profile_lookup_id);
    $statFollowingCount = getFollowingCount($profile_lookup_id);
    if (!$is_own_profile && $current_user_id !== null) {
        $isFollowingProfile = followers_is_following((string) $current_user_id, (string) $profile_lookup_id);
        $followUiState = $isFollowingProfile ? 'aceito' : 'none';
    }
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

if ($profile_lookup_id !== '') {
    if (profile_stats_service_ok()) {
        $statPosts = profile_count_posts($profile_lookup_id);
        $statLikesRecv = profile_count_likes_received($profile_lookup_id);
    } else {
        $statPosts = count($myPosts);
        $statLikesRecv = 0;
    }
    $postsLoadedCount = count($myPosts);
    if ($postsLoadedCount > $statPosts) {
        $statPosts = $postsLoadedCount;
    }
}

$myPostsForGrid = array_values(array_filter($myPosts, function ($p) {
    return isset($p['image_url']) && trim((string) $p['image_url']) !== '';
}));

$likesByPostId = [];
if ($myPostsForGrid !== [] && feed_sk_available()) {
    $pids = [];
    foreach ($myPostsForGrid as $p) {
        if (isset($p['id'])) {
            $pids[] = (int) $p['id'];
        }
    }
    if ($pids !== []) {
        $likesByPostId = feed_get_likes_count_map($pids);
    }
}

$pendingFollowProfiles = [];
if ($pendingFollowRows !== [] && supabase_service_role_available()) {
    $pidsF = [];
    foreach ($pendingFollowRows as $pr) {
        if (!empty($pr['follower_id'])) {
            $pidsF[] = (string) $pr['follower_id'];
        }
    }
    if ($pidsF !== []) {
        $inList = implode(',', array_unique($pidsF));
        $pfUrl = SUPABASE_URL . '/rest/v1/profiles?select=id,display_id,avatar_url&id=in.(' . $inList . ')';
        $chPf = curl_init($pfUrl);
        curl_setopt_array($chPf, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => array_merge(supabase_service_rest_headers(false), ['Accept: application/json']),
        ]);
        $rawPf = curl_exec($chPf);
        curl_close($chPf);
        $decPf = json_decode((string) $rawPf, true);
        if (is_array($decPf)) {
            foreach ($decPf as $row) {
                if (isset($row['id'])) {
                    $pendingFollowProfiles[(string) $row['id']] = $row;
                }
            }
        }
    }
}

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

$csrf = csrf_token();
$relLower = strtolower(trim($profileRelationship));
$igShowAge = $profileAge !== null;
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
            display: flex;
            flex-direction: column;
            align-items: stretch;
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
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #333;
            display: block;
            background: #1A1A1A;
        }
        .ig-avatar-fallback {
            width: 120px;
            height: 120px;
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
        .ig-title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 6px; justify-content: space-between; width: 100%; }
        .ig-title-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; flex-shrink: 0; flex-wrap: wrap; justify-content: flex-end; max-width: 100%; }
        .ig-title-actions .btn-follow-ig { min-width: 92px; padding: 8px 12px; font-size: 0.78rem; }
        .ig-title-actions .btn-msg-ig { flex: 0 0 auto; min-width: auto; padding: 8px 12px; font-size: 0.78rem; }
        .ig-pending-cta {
            margin-left: auto; font-size: 0.78rem; font-weight: 700; color: #C9A84C; text-decoration: none;
            padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(201, 168, 76, 0.35); background: rgba(201, 168, 76, 0.08); white-space: nowrap;
        }
        .ig-pending-cta:hover { background: rgba(201, 168, 76, 0.15); color: #e8d5a3; }
        .btn-admin-link {
            display: inline-block;
            padding: 6px 14px;
            background: transparent;
            border: 1px solid #333;
            border-radius: 6px;
            color: #C9A84C;
            font-size: 0.8rem;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-admin-link:hover { background: rgba(201, 168, 76, 0.1); }
        .ig-username {
            margin: 0;
            font-size: clamp(1.35rem, 4vw, 1.65rem);
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.02em;
        }
        .ig-gear {
            color: #888; font-size: 1.35rem; text-decoration: none; line-height: 1;
            padding: 4px;
        }
        .ig-gear:hover { color: #C9A84C; }
        .ig-handle-sub { margin: 0 0 10px; font-size: 0.88rem; color: #777; }
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
        a.ig-stat-link { text-decoration: none; color: inherit; cursor: pointer; }
        a.ig-stat-link:hover .ig-stat-num { color: #C9A84C; }
        .ig-stat { text-align: center; min-width: 56px; }
        .ig-stat-num { font-weight: 700; font-size: 1rem; color: #fff; }
        .ig-stat-lbl { font-size: 0.62rem; color: #888; text-transform: uppercase; letter-spacing: 0.06em; margin-top: 2px; }
        .btn-ig-story {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 16px; font-size: 0.78rem; font-weight: 600; border-radius: 999px;
            border: 1px solid #333; background: #161616; color: #e0e0e0; text-decoration: none; font-family: inherit;
        }
        .btn-ig-story:hover { border-color: #C9A84C; color: #C9A84C; }
        .ig-avatar-link { display: block; border-radius: 50%; text-decoration: none; }
        .ig-avatar-link:focus-visible { outline: 2px solid #7B2EFF; outline-offset: 3px; }
        .btn-dm-icon {
            display: inline-flex; align-items: center; justify-content: center;
            width: 40px; height: 36px; border-radius: 8px; border: 1px solid #333;
            background: #161616; color: #ccc; text-decoration: none;
            transition: border-color 0.15s ease, color 0.15s ease;
        }
        .btn-dm-icon:hover { border-color: #C9A84C; color: #C9A84C; }
        .ig-meta-line { font-size: 0.88rem; color: #bbb; margin-bottom: 8px; }
        .ig-bio { font-size: 0.9rem; color: #ccc; line-height: 1.45; white-space: pre-wrap; word-break: break-word; }
        .profile-cidade { font-size: 0.88rem; color: #C9A84C; margin: 8px 0 0; line-height: 1.4; }
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
        .ig-topbar{
            position:sticky;top:0;z-index:120;display:flex;align-items:center;gap:8px;
            padding:10px 12px;background:#0A0A0A;border-bottom:1px solid #2a2a2a;min-height:48px;
        }
        .ig-topbar-back{color:#AAAAAA;text-decoration:none;font-size:1.25rem;padding:4px;flex-shrink:0;line-height:1}
        .ig-topbar-back:hover{color:#C9A84C}
        .ig-topbar-title{
            flex:1;min-width:0;text-align:center;font-weight:700;font-size:0.98rem;color:#fff;
            letter-spacing:0.02em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:0 4px;
        }
        .ig-topbar-end{display:flex;align-items:center;gap:10px;flex-shrink:0;margin-left:auto}
        .ig-topbar-dm{
            position:relative;display:inline-flex;align-items:center;justify-content:center;
            width:40px;height:36px;border-radius:10px;border:1px solid #333;background:#161616;color:#e8e8e8;
            text-decoration:none;transition:border-color .15s ease,color .15s ease;
        }
        .ig-topbar-dm:hover{border-color:#C9A84C;color:#C9A84C}
        .ig-topbar-dm-ico{font-size:1.15rem;line-height:1}
        .ig-dm-badge{
            position:absolute;top:-5px;right:-5px;min-width:18px;height:18px;padding:0 5px;border-radius:999px;
            background:#7B2EFF;color:#fff;font-size:0.62rem;font-weight:800;line-height:18px;text-align:center;
            border:2px solid #0A0A0A;
        }
        .ig-gear-wrap{position:relative;display:inline-flex}
        .ig-gear-badge{
            position:absolute;top:-4px;right:-6px;min-width:18px;height:18px;padding:0 4px;border-radius:999px;
            background:#7B2EFF;color:#fff;font-size:0.65rem;font-weight:800;line-height:18px;text-align:center;
            border:none;cursor:pointer;font-family:inherit;z-index:2;
        }
        .ig-gear-badge:focus-visible{outline:2px solid #C9A84C;outline-offset:2px}
        .ig-follow-pending-card{
            margin:14px 0 18px;padding:14px 14px 10px;background:#121212;border:1px solid #2a2a2a;border-radius:14px;
        }
        .ig-follow-pending-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}
        .ig-follow-pending-head strong{color:#C9A84C;font-size:0.88rem;font-weight:700}
        .ig-follow-pending-count{
            font-size:0.72rem;font-weight:800;color:#fff;background:#7B2EFF;min-width:22px;text-align:center;
            padding:2px 8px;border-radius:999px;
        }
        .btn-follow-ig{
            flex:1;min-width:0;text-align:center;padding:9px 14px;font-size:0.82rem;font-weight:700;border-radius:8px;
            cursor:pointer;font-family:inherit;border:none;
        }
        .btn-follow-ig--segue,.btn-seguir{
            background:#7B2EFF;color:#fff;border:none;padding:8px 20px;border-radius:8px;
            cursor:pointer;font-weight:700;font-size:0.82rem;font-family:inherit;
        }
        .btn-follow-ig--seguindo,.btn-seguindo{
            background:transparent;color:#7B2EFF;border:2px solid #7B2EFF;padding:8px 20px;border-radius:8px;
            cursor:pointer;font-weight:600;font-size:0.82rem;font-family:inherit;
        }
        .btn-follow-ig--pendente,.btn-solicitado{
            background:#1a1a1a;color:#aaa;border:1px solid #2a2a2a;padding:8px 20px;border-radius:8px;
            cursor:default;font-size:0.82rem;font-family:inherit;
        }
        .btn-msg-ig{
            flex:1;min-width:0;display:inline-flex;align-items:center;justify-content:center;gap:6px;
            padding:9px 14px;font-size:0.82rem;font-weight:600;border-radius:8px;text-decoration:none;
            background:#1a1a1a;border:1px solid #2a2a2a;color:#eee;
        }
        .btn-msg-ig:hover{border-color:#C9A84C;color:#C9A84C}
        .btn-mensagem{background:#1a1a1a;color:#fff;border:1px solid #2a2a2a;padding:8px 20px;border-radius:8px;
            cursor:pointer;font-size:0.82rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
        .btn-mensagem:hover{border-color:#C9A84C;color:#C9A84C}
        .ig-actions-row{display:flex;flex-wrap:wrap;gap:8px;align-items:stretch;width:100%;max-width:420px}
        .ig-dono{font-size:0.88rem;color:#C9A84C;margin:6px 0 4px;font-weight:600}
        .follow-requests-modal{
            display:none;position:fixed;inset:0;z-index:400;background:rgba(0,0,0,0.75);
            align-items:center;justify-content:center;padding:20px;
        }
        .follow-requests-modal.is-open{display:flex}
        .follow-requests-sheet{
            background:#111;border:1px solid #2a2a2a;border-radius:14px;max-width:420px;width:100%;max-height:80vh;overflow:auto;padding:16px;
        }
        .follow-req-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 0;border-bottom:1px solid #222}
        .follow-req-row:last-child{border-bottom:none}
        .post-modal-cap{padding:12px 16px;color:#ddd;font-size:0.9rem;max-width:min(100vw - 32px, 900px)}
        .post-modal-likes{font-size:0.82rem;color:#888;padding:0 16px 12px}
    </style>
</head>
<body>
    <header class="ig-topbar">
        <a class="ig-topbar-back" href="/features/feed/index.php" aria-label="Voltar">←</a>
        <div class="ig-topbar-title"><?= htmlspecialchars($clLabel, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="ig-topbar-end">
            <a class="ig-topbar-dm" href="/mensagens" aria-label="Mensagens diretas">
                <span class="ig-topbar-dm-ico" aria-hidden="true">✉️</span>
                <?php if ($dmUnread > 0): ?>
                <span class="ig-dm-badge"><?= ((int) $dmUnread) > 99 ? '99+' : (string) (int) $dmUnread ?></span>
                <?php endif; ?>
            </a>
            <?php if ($is_own_profile): ?>
            <div class="ig-gear-wrap">
                <a class="ig-gear" href="/features/profile/settings.php" title="Configurações" aria-label="Configurações" id="igGearLink">⚙️</a>
                <?php if ($pendingFollowCount > 0): ?>
                <button type="button" class="ig-gear-badge" id="followReqBadgeBtn" aria-label="Ir aos pedidos para seguir"><?= (int) $pendingFollowCount ?></button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="page" style="padding-top:8px">
        <div class="auth-wrap" style="max-width:520px">
            <?php if ($status === 'ok'): ?>
                <div class="alert ok"><?php echo htmlspecialchars($message !== '' ? $message : 'Operação realizada.', ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif ($status === 'error'): ?>
                <div class="alert error"><?php echo htmlspecialchars($message !== '' ? $message : 'Erro ao processar.', ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="auth-card">
                <h1 class="auth-brand" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0">Perfil</h1>
                <div class="ig-header">
                    <div class="ig-avatar-col">
                        <?php if ($is_own_profile): ?>
                        <a class="ig-avatar-link" href="/features/profile/upload_avatar.php?return_to=<?= rawurlencode('/features/profile/index.php') ?>" title="Alterar foto">
                        <?php endif; ?>
                        <?php if ($avatarUrl !== ''): ?>
                        <img class="ig-avatar-img" src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <?php else: ?>
                        <div class="ig-avatar-fallback" aria-hidden="true">&#128100;</div>
                        <?php endif; ?>
                        <?php if ($is_own_profile): ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="ig-main-col">
                        <div class="ig-title-row">
                            <h2 class="ig-username"><?= htmlspecialchars($clLabel, ENT_QUOTES, 'UTF-8') ?></h2>
                            <?php if (!$is_own_profile && $current_user_id !== null && $profile_lookup_id !== ''): ?>
                            <div class="ig-title-actions">
                            <?php if (club61_follows_service_ok()): ?>
                                <?php
                                $flabel = 'Seguir';
                                $fclass = 'btn-follow-ig btn-follow-ig--segue btn-seguir';
                                if ($followUiState === 'aceito') {
                                    $flabel = 'Seguindo';
                                    $fclass = 'btn-follow-ig btn-follow-ig--seguindo btn-seguindo';
                                } elseif ($followUiState === 'pendente') {
                                    $flabel = 'Solicitado';
                                    $fclass = 'btn-follow-ig btn-follow-ig--pendente btn-solicitado';
                                }
                                ?>
                            <button type="button" id="profile-follow-btn" class="<?= htmlspecialchars($fclass, ENT_QUOTES, 'UTF-8') ?>"
                                data-following-id="<?= htmlspecialchars((string) $profile_lookup_id, ENT_QUOTES, 'UTF-8') ?>"
                                data-state="<?= htmlspecialchars($followUiState, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($flabel, ENT_QUOTES, 'UTF-8') ?>
                            </button>
                            <?php else: ?>
                            <button type="button" id="profile-follow-btn" class="btn btn-follow dark-btn js-follow-toggle<?= $isFollowingProfile ? ' is-following' : '' ?>"
                                data-following-id="<?= htmlspecialchars((string) $profile_lookup_id, ENT_QUOTES, 'UTF-8') ?>"
                                data-following="<?= $isFollowingProfile ? '1' : '0' ?>"
                                aria-pressed="<?= $isFollowingProfile ? 'true' : 'false' ?>">
                                <?= $isFollowingProfile ? 'Seguindo' : 'Seguir' ?>
                            </button>
                            <?php endif; ?>
                            <?php if ($mrBtn === 'accepted'): ?>
                            <a class="btn-msg-ig btn-mensagem" href="/mensagens?com=<?= rawurlencode((string) $profile_lookup_id) ?>" title="Mensagem">✉️</a>
                            <?php elseif ($mrBtn !== 'hidden'): ?>
                                <?php if ($mrBtn === 'request'): ?>
                                <form action="/features/profile/message_request.php" method="post" style="margin:0;display:inline">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="to_user" value="<?= htmlspecialchars((string) $profile_lookup_id, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="return_to" value="<?= htmlspecialchars('/features/profile/index.php?user=' . rawurlencode((string) $profile_lookup_id), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn-msg-ig" title="Pedir mensagem">✉️ Pedir</button>
                                </form>
                                <?php elseif ($mrBtn === 'pending_sent'): ?>
                                <span class="btn-msg-ig" style="opacity:0.7;cursor:default;font-size:0.72rem">Pedido enviado</span>
                                <?php elseif ($mrBtn === 'pending_inbox'): ?>
                                <a class="btn-msg-ig" href="/features/chat/message_requests_inbox.php">✉️ Responder</a>
                                <?php endif; ?>
                            <?php endif; ?>
                            </div>
                            <?php elseif ($is_own_profile && $pendingFollowCount > 0 && $pendingFollowRows !== []): ?>
                            <a class="ig-pending-cta" href="#ig-follow-pending"><?= (int) $pendingFollowCount ?> pedido(s) — Aceitar</a>
                            <?php endif; ?>
                        </div>
                        <?php if (is_array($profileRow) && strtolower((string) ($profileRow['role'] ?? '')) === 'admin'): ?>
                        <p class="ig-dono">Dono do Site</p>
                        <?php endif; ?>
                        <div class="ig-actions">
                            <?php if ($is_own_profile): ?>
                            <a class="btn-ig-edit" href="/features/profile/settings.php">Editar perfil</a>
                            <a class="btn-ig-story" href="/features/profile/upload_story.php"><i class="bi bi-camera" aria-hidden="true"></i> Enviar story</a>
                            <a class="btn-ig-story" href="/features/chat/salas.php">💬 Salas de chat</a>
                            <?php if (is_array($profileRow) && strtolower((string) ($profileRow['role'] ?? '')) === 'admin'): ?>
                            <a class="btn-admin-link" href="/features/admin/index.php" style="margin-top:8px;display:inline-block">Painel admin</a>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($is_own_profile && $pendingFollowCount > 0 && $pendingFollowRows !== []): ?>
                        <div id="ig-follow-pending-wrap" class="ig-follow-pending-card">
                            <div class="ig-follow-pending-head">
                                <strong id="ig-follow-pending">Pedidos para seguir</strong>
                                <span class="ig-follow-pending-count" id="ig-follow-pending-count"><?= (int) $pendingFollowCount ?></span>
                            </div>
                            <?php foreach ($pendingFollowRows as $pr):
                                $fid = (string) ($pr['follower_id'] ?? '');
                                if ($fid === '') {
                                    continue;
                                }
                                $fp = $pendingFollowProfiles[$fid] ?? [];
                                $flab = $fp !== [] ? club61_display_id_label(isset($fp['display_id']) ? (string) $fp['display_id'] : null) : 'Membro';
                                ?>
                            <div class="follow-req-row" data-follower-id="<?= htmlspecialchars($fid, ENT_QUOTES, 'UTF-8') ?>">
                                <span style="font-weight:600;color:#fff"><?= htmlspecialchars($flab, ENT_QUOTES, 'UTF-8') ?></span>
                                <span>
                                    <button type="button" class="btn-ig-edit follow-accept" style="display:inline-block;width:auto;padding:6px 12px;margin-right:6px">Aceitar</button>
                                    <button type="button" class="btn-cancel follow-reject" style="display:inline-block;width:auto;padding:6px 12px">Recusar</button>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="ig-stats" aria-label="Estatísticas">
                            <a class="ig-stat-link" href="#profile-posts-grid">
                            <div class="ig-stat">
                                <div class="ig-stat-num"><?= (int) $statPosts ?></div>
                                <div class="ig-stat-lbl">Posts</div>
                            </div>
                            </a>
                            <a class="ig-stat-link" href="#profile-network-stub">
                            <div class="ig-stat">
                                <div class="ig-stat-num" id="profile-followers-count" data-followers-count="<?= (int) $statFollowersCount ?>"><?= (int) $statFollowersCount ?></div>
                                <div class="ig-stat-lbl">Seguidores</div>
                            </div>
                            </a>
                            <a class="ig-stat-link" href="#profile-network-stub">
                            <div class="ig-stat">
                                <div class="ig-stat-num"><?= (int) $statFollowingCount ?></div>
                                <div class="ig-stat-lbl">Seguindo</div>
                            </div>
                            </a>
                        </div>
                        <?php if ($profileBio !== ''): ?>
                        <p class="ig-bio"><?= htmlspecialchars($profileBio, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <?php if (trim($cidade) !== ''): ?>
                        <p class="profile-cidade">📍 <?= htmlspecialchars(trim($cidade), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <?php if ($igShowAge || $igShowRel): ?>
                        <p class="ig-meta-line">
                            <?php if ($igShowAge): ?><?= (int) $profileAge ?> anos<?php endif; ?>
                            <?php if ($igShowRel): ?>
                                <?php if ($igShowAge): ?> • <?php endif; ?>
                                <?= htmlspecialchars(club61_relationship_label($profileRelationship), ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <div id="profile-network-stub" tabindex="-1" style="scroll-margin-top:72px"></div>
                <div class="posts-section" id="profile-posts-grid">
                    <h2 class="section-head">Publicações</h2>
                    <?php if (empty($myPostsForGrid)): ?>
                        <p class="posts-empty" style="display:flex;flex-direction:column;align-items:center;gap:10px;padding:28px 12px">
                            <span style="font-size:2rem" aria-hidden="true">📷</span>
                            <span>Sem publicações ainda</span>
                        </p>
                    <?php else: ?>
                        <div class="posts-grid">
                            <?php foreach ($myPostsForGrid as $gp): ?>
                                <?php
                                $gimg = trim((string) $gp['image_url']);
                                $gcap = isset($gp['caption']) ? (string) $gp['caption'] : '';
                                $gid = isset($gp['id']) ? (int) $gp['id'] : 0;
                                $glk = $gid > 0 ? (int) ($likesByPostId[(string) $gid] ?? 0) : 0;
                                ?>
                            <button type="button" class="posts-grid-cell" data-src="<?= htmlspecialchars($gimg, ENT_QUOTES, 'UTF-8') ?>"
                                data-caption="<?= htmlspecialchars($gcap, ENT_QUOTES, 'UTF-8') ?>"
                                data-likes="<?= (int) $glk ?>"
                                aria-label="Ampliar publicação">
                                <img src="<?= htmlspecialchars($gimg, ENT_QUOTES, 'UTF-8') ?>" alt="">
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
        <div class="post-modal-inner">
            <img id="postModalImg" src="" alt="">
            <div id="postModalCap" class="post-modal-cap" style="display:none"></div>
            <div id="postModalLikes" class="post-modal-likes" style="display:none"></div>
        </div>
    </div>

    <script>
    (function () {
        var PROFILE_CSRF = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        var USE_FOLLOWS = <?= club61_follows_service_ok() ? 'true' : 'false' ?>;
        var FOLLOW_API = '/features/follow/follow_api.php';
        var FOLLOW_ENVIAR_URLS = ['/follow/enviar', '/features/follow/follow_api.php?r=enviar'];
        var FOLLOW_REMOVER_URLS = ['/follow/remover', '/features/follow/follow_api.php?r=remover'];
        var followBtn = document.getElementById('profile-follow-btn');
        var followersEl = document.getElementById('profile-followers-count');
        function applyFollowButtonState(state) {
            if (!followBtn) return;
            followBtn.setAttribute('data-state', state);
            if (state === 'aceito') {
                followBtn.textContent = 'Seguindo';
                followBtn.className = 'btn-follow-ig btn-follow-ig--seguindo btn-seguindo';
            } else if (state === 'pendente') {
                followBtn.textContent = 'Solicitado';
                followBtn.className = 'btn-follow-ig btn-follow-ig--pendente btn-solicitado';
            } else {
                followBtn.textContent = 'Seguir';
                followBtn.className = 'btn-follow-ig btn-follow-ig--segue btn-seguir';
            }
        }
        function postFollowJson(urls, payload) {
            function tryAt(i) {
                if (i >= urls.length) {
                    return Promise.reject(new Error('Não foi possível conectar ao servidor.'));
                }
                return fetch(urls[i], {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                })
                    .then(function (r) {
                        return r.text().then(function (txt) {
                            var j = null;
                            try { j = txt ? JSON.parse(txt) : null; } catch (e) { j = null; }
                            return { r: r, j: j };
                        });
                    })
                    .then(function (o) {
                        var ok = o.j && (o.j.success === true || o.j.ok === true);
                        if (o.r.ok && ok) return o.j;
                        if (o.r.status === 404 && i + 1 < urls.length) return tryAt(i + 1);
                        var msg = (o.j && (o.j.message || o.j.error)) ? String(o.j.message || o.j.error) : ('HTTP ' + o.r.status);
                        var err = new Error(msg);
                        err._noFollowRetry = true;
                        throw err;
                    })
                    .catch(function (err) {
                        if (err && err._noFollowRetry) throw err;
                        if (i + 1 < urls.length) return tryAt(i + 1);
                        throw err || new Error('Erro de rede.');
                    });
            }
            return tryAt(0);
        }
        if (followBtn) {
            followBtn.addEventListener('click', function () {
                var fid = followBtn.getAttribute('data-following-id');
                if (!fid) return;
                var st = followBtn.getAttribute('data-state') || '';
                if (USE_FOLLOWS) {
                    if (st === 'pendente') return;
                    if (st === 'aceito') {
                        if (!confirm('Deixar de seguir?')) return;
                        followBtn.disabled = true;
                        postFollowJson(FOLLOW_REMOVER_URLS, { following_id: fid, csrf: PROFILE_CSRF })
                            .then(function () { location.reload(); })
                            .catch(function (e) { alert('Erro: ' + (e && e.message ? e.message : 'rede')); })
                            .finally(function () { followBtn.disabled = false; });
                        return;
                    }
                    followBtn.disabled = true;
                    postFollowJson(FOLLOW_ENVIAR_URLS, { following_id: fid, csrf: PROFILE_CSRF })
                        .then(function (d) {
                            var ns = d.state || 'pendente';
                            applyFollowButtonState(ns);
                            if (followersEl && typeof d.followers_count === 'number') {
                                followersEl.textContent = String(d.followers_count);
                            }
                        })
                        .catch(function (e) { alert('Erro: ' + (e && e.message ? e.message : 'rede')); })
                        .finally(function () { followBtn.disabled = false; });
                    return;
                }
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
        var followReqBadgeBtn = document.getElementById('followReqBadgeBtn');
        var pendingWrap = document.getElementById('ig-follow-pending-wrap');
        var pendingCountEl = document.getElementById('ig-follow-pending-count');
        if (followReqBadgeBtn) {
            followReqBadgeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var el = document.getElementById('ig-follow-pending');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }
        function updateFollowReqBadge(n) {
            var b = document.getElementById('followReqBadgeBtn');
            if (b) {
                if (n <= 0) {
                    b.style.display = 'none';
                } else {
                    b.style.display = '';
                    b.textContent = String(n);
                }
            }
            if (pendingCountEl) {
                pendingCountEl.textContent = n > 0 ? String(n) : '0';
            }
            if (pendingWrap && n <= 0) {
                pendingWrap.style.display = 'none';
            }
        }
        document.querySelectorAll('.follow-accept').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var row = btn.closest('.follow-req-row');
                var id = row ? row.getAttribute('data-follower-id') : '';
                if (!id) return;
                fetch(FOLLOW_API + '?r=aceitar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ follower_id: id, csrf: PROFILE_CSRF })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d || !d.success) return;
                        if (row) row.remove();
                        if (followersEl && typeof d.followers_count === 'number') {
                            followersEl.textContent = String(d.followers_count);
                        }
                        var n = d.pending_count != null ? parseInt(d.pending_count, 10) : 0;
                        updateFollowReqBadge(n);
                    });
            });
        });
        document.querySelectorAll('.follow-reject').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var row = btn.closest('.follow-req-row');
                var id = row ? row.getAttribute('data-follower-id') : '';
                if (!id) return;
                fetch(FOLLOW_API + '?r=recusar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ follower_id: id, csrf: PROFILE_CSRF })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d || !d.success) return;
                        if (row) row.remove();
                        var n = d.pending_count != null ? parseInt(d.pending_count, 10) : 0;
                        updateFollowReqBadge(n);
                    });
            });
        });
        var modal = document.getElementById('postModal');
        var modalImg = document.getElementById('postModalImg');
        var modalCap = document.getElementById('postModalCap');
        var modalLikes = document.getElementById('postModalLikes');
        if (!modal || !modalImg) return;
        function openModal(src, cap, likes) {
            modalImg.src = src;
            if (modalCap) {
                if (cap) {
                    modalCap.style.display = 'block';
                    modalCap.textContent = cap;
                } else {
                    modalCap.style.display = 'none';
                    modalCap.textContent = '';
                }
            }
            if (modalLikes) {
                if (likes > 0) {
                    modalLikes.style.display = 'block';
                    modalLikes.textContent = '♥ ' + likes + ' curtidas';
                } else {
                    modalLikes.style.display = 'none';
                    modalLikes.textContent = '';
                }
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
        document.querySelectorAll('.posts-grid-cell').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var src = btn.getAttribute('data-src');
                var cap = btn.getAttribute('data-caption') || '';
                var likes = parseInt(btn.getAttribute('data-likes') || '0', 10) || 0;
                if (src) openModal(src, cap, likes);
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
