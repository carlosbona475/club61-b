<?php

/**
 * Painel Admin — Club61
 *
 * Segurança: sessão + isCurrentUserAdmin(), CSRF em todo POST, PRG, saída escapada.
 * Requer coluna opcional profiles.status para ban (ex.: 'active' | 'banned').
 *   SQL: ALTER TABLE public.profiles ADD COLUMN IF NOT EXISTS status text DEFAULT 'active';
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/session.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';

// Service role obrigatória para ler perfil e operações admin
if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Serviço indisponível.'));
    exit;
}

// Perfil tem de existir; caso contrário encerra sessão (evita token sem linha em profiles)
admin_guard_profile_or_logout();

if (!isCurrentUserAdmin()) {
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Acesso negado.'));
    exit;
}

$current_user_id = trim((string) ($_SESSION['user_id'] ?? ''));

$base = rtrim(SUPABASE_URL, '/');
$sk = SUPABASE_SERVICE_KEY;

const ADMIN_PAGE_LIMIT = 20;

// ---------------------------------------------------------------------------
// CSRF (sessão)
// ---------------------------------------------------------------------------

function admin_csrf_token(): string
{
    club61_session_start_safe();
    if (empty($_SESSION['admin_csrf_token']) || !is_string($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

function admin_csrf_validate(): bool
{
    club61_session_start_safe();
    $sent = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['admin_csrf_token'] ?? '';

    return is_string($sent) && is_string($stored) && $stored !== '' && hash_equals($stored, $sent);
}

// ---------------------------------------------------------------------------
// HTTP helpers (Supabase REST + service key)
// ---------------------------------------------------------------------------

/**
 * @return array{code:int, body:string, headers:string}
 */
function admin_curl_exec_full(string $url, array $options): array
{
    $ch = curl_init($url);
    $defaults = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ];
    curl_setopt_array($ch, $options + $defaults);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headers = '';
    if (isset($options[CURLOPT_HEADER]) && $options[CURLOPT_HEADER]) {
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        if (is_int($headerSize) && $headerSize > 0 && is_string($body)) {
            $headers = substr($body, 0, $headerSize);
            $body = substr($body, $headerSize);
        }
    }
    curl_close($ch);

    return [
        'code' => $code,
        'body' => is_string($body) ? $body : '',
        'headers' => $headers,
    ];
}

/**
 * Contagem exata via Prefer: count=exact + Range: 0-0
 */
function admin_count_exact(string $pathAndQuery): int
{
    global $base, $sk;

    $path = $pathAndQuery;
    if (!preg_match('/[?&]select=/', $pathAndQuery)) {
        $path .= (strpos($pathAndQuery, '?') !== false ? '&' : '?') . 'select=id';
    }
    $url = $base . $path;

    $res = admin_curl_exec_full($url, [
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $sk,
            'Authorization: Bearer ' . $sk,
            'Accept: application/json',
            'Prefer: count=exact',
            'Range: 0-0',
        ],
    ]);

    if ($res['code'] < 200 || $res['code'] >= 300) {
        return 0;
    }
    $headerBlock = $res['headers'];
    if (preg_match('/Content-Range:\s*[\d]+-[\d]+\/(\d+)/i', $headerBlock, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/Content-Range:\s*\*\/(\d+)/i', $headerBlock, $m)) {
        return (int) $m[1];
    }

    return 0;
}

/**
 * @return array<int, array<string, mixed>>
 */
function admin_json_get_list(string $pathAndQuery): array
{
    global $base, $sk;

    $url = $base . $pathAndQuery;
    $res = admin_curl_exec_full($url, [
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $sk,
            'Authorization: Bearer ' . $sk,
            'Accept: application/json',
        ],
        CURLOPT_HTTPGET => true,
    ]);
    if ($res['code'] < 200 || $res['code'] >= 300) {
        return [];
    }
    $rows = json_decode($res['body'], true);

    return is_array($rows) ? $rows : [];
}

function admin_json_post(string $path, string $jsonBody): int
{
    global $base, $sk;

    $url = $base . $path;
    $res = admin_curl_exec_full($url, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonBody,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $sk,
            'Authorization: Bearer ' . $sk,
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ],
    ]);

    return $res['code'];
}

function admin_json_patch(string $pathAndQuery, string $jsonBody): int
{
    global $base, $sk;

    $url = $base . $pathAndQuery;
    $res = admin_curl_exec_full($url, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $jsonBody,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $sk,
            'Authorization: Bearer ' . $sk,
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ],
    ]);

    return $res['code'];
}

function admin_http_delete(string $pathAndQuery): int
{
    global $base, $sk;

    $url = $base . $pathAndQuery;
    $res = admin_curl_exec_full($url, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $sk,
            'Authorization: Bearer ' . $sk,
            'Prefer: return=minimal',
        ],
    ]);

    return $res['code'];
}

/** Início do dia UTC (ISO) para filtros gte */
function admin_utc_midnight_iso(): string
{
    return gmdate('Y-m-d\T00:00:00\Z');
}

function admin_redirect_prg(string $tab, string $message = '', string $level = 'ok', array $extraQuery = []): void
{
    $q = array_merge(
        [
            'tab' => $tab,
        ],
        $extraQuery
    );
    if ($message !== '') {
        $q['status'] = $level;
        $q['message'] = $message;
    }
    header('Location: /features/admin/index.php?' . http_build_query($q));
    exit;
}

/**
 * @param array<string, mixed> $p
 */
function clLabel(array $p): string
{
    $disp = isset($p['display_id']) ? trim((string) $p['display_id']) : '';
    $uname = isset($p['username']) ? trim((string) $p['username']) : '';
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
            return 'CL' . str_pad((string) min(999, $num), 2, '0', STR_PAD_LEFT);
        }
    }
    if ($uname !== '') {
        return '@' . $uname;
    }

    return 'Membro';
}

// ---------------------------------------------------------------------------
// POST: revalidar admin + CSRF antes de qualquer ação
// ---------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isCurrentUserAdmin()) {
        header('Location: /features/feed/index.php?status=error&message=' . urlencode('Acesso negado.'));
        exit;
    }
    if (!admin_csrf_validate()) {
        admin_redirect_prg('dashboard', 'Sessão inválida. Atualize a página e tente de novo.', 'error');
    }

    $action = (string) ($_POST['action'] ?? '');
    $tab = (string) ($_POST['return_tab'] ?? 'dashboard');
    $allowedTabs = ['dashboard', 'members', 'posts', 'invites'];
    if (!in_array($tab, $allowedTabs, true)) {
        $tab = 'dashboard';
    }

    $mPage = max(1, (int) ($_POST['m_page'] ?? 1));
    $pPage = max(1, (int) ($_POST['p_page'] ?? 1));
    $pageQs = [
        'm_page' => $mPage,
        'p_page' => $pPage,
    ];

    if ($action === 'gen_invite') {
        $code = strtoupper(bin2hex(random_bytes(5)));
        $payload = json_encode([
            'code' => $code,
            'created_by' => $current_user_id,
            'status' => 'available',
        ], JSON_UNESCAPED_SLASHES);
        admin_json_post('/rest/v1/invites', $payload);
        admin_redirect_prg('invites', 'Convite gerado: ' . $code, 'ok', $pageQs);
    }

    if ($action === 'revoke_invite') {
        $code = trim((string) ($_POST['code'] ?? ''));
        if ($code !== '') {
            admin_json_patch(
                '/rest/v1/invites?code=eq.' . urlencode($code),
                json_encode(['status' => 'revoked'], JSON_UNESCAPED_SLASHES)
            );
        }
        admin_redirect_prg('invites', 'Convite revogado.', 'ok', $pageQs);
    }

    if ($action === 'set_role') {
        $uid = trim((string) ($_POST['uid'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        if ($uid !== '' && $uid !== $current_user_id && ($role === 'admin' || $role === 'member')) {
            admin_json_patch(
                '/rest/v1/profiles?id=eq.' . urlencode($uid),
                json_encode(['role' => $role], JSON_UNESCAPED_SLASHES)
            );
        }
        admin_redirect_prg('members', 'Role atualizado.', 'ok', $pageQs);
    }

    if ($action === 'ban_member') {
        $uid = trim((string) ($_POST['uid'] ?? ''));
        if ($uid !== '' && $uid !== $current_user_id) {
            admin_json_patch(
                '/rest/v1/profiles?id=eq.' . urlencode($uid),
                json_encode(['status' => 'banned'], JSON_UNESCAPED_SLASHES)
            );
        }
        admin_redirect_prg('members', 'Membro banido (status=banned).', 'ok', $pageQs);
    }

    if ($action === 'remove_member') {
        $uid = trim((string) ($_POST['uid'] ?? ''));
        if ($uid !== '' && $uid !== $current_user_id) {
            admin_http_delete('/rest/v1/profiles?id=eq.' . urlencode($uid));
        }
        admin_redirect_prg('members', 'Membro removido.', 'ok', $pageQs);
    }

    if ($action === 'delete_post') {
        $pid = trim((string) ($_POST['pid'] ?? ''));
        if ($pid !== '') {
            admin_http_delete('/rest/v1/posts?id=eq.' . urlencode($pid));
        }
        admin_redirect_prg('posts', 'Post removido.', 'ok', $pageQs);
    }

    if ($action === 'delete_all_user_posts') {
        $uid = trim((string) ($_POST['user_id'] ?? ''));
        if ($uid !== '') {
            admin_http_delete('/rest/v1/posts?user_id=eq.' . urlencode($uid));
        }
        admin_redirect_prg('posts', 'Todos os posts deste usuário foram removidos.', 'ok', $pageQs);
    }

    admin_redirect_prg($tab, '', 'ok', $pageQs);
}

// ---------------------------------------------------------------------------
// GET: dados paginados + estatísticas
// ---------------------------------------------------------------------------

$csrf = admin_csrf_token();

$tab = (string) ($_GET['tab'] ?? 'dashboard');
$allowedTabsGet = ['dashboard', 'members', 'posts', 'invites'];
if (!in_array($tab, $allowedTabsGet, true)) {
    $tab = 'dashboard';
}

$mPage = max(1, (int) ($_GET['m_page'] ?? 1));
$pPage = max(1, (int) ($_GET['p_page'] ?? 1));
$offsetMembers = ($mPage - 1) * ADMIN_PAGE_LIMIT;
$offsetPosts = ($pPage - 1) * ADMIN_PAGE_LIMIT;

$startUtc = admin_utc_midnight_iso();

$total_members = admin_count_exact('/rest/v1/profiles?select=id');
$total_posts = admin_count_exact('/rest/v1/posts?select=id');
$posts_today = admin_count_exact('/rest/v1/posts?select=id&created_at=gte.' . rawurlencode($startUtc));
$new_members_today = admin_count_exact('/rest/v1/profiles?select=id&created_at=gte.' . rawurlencode($startUtc));

$totalInvitesAvailable = admin_count_exact('/rest/v1/invites?select=id&status=eq.available');
$totalAdmins = admin_count_exact('/rest/v1/profiles?select=id&role=eq.admin');

$membersPath = '/rest/v1/profiles?select=id,display_id,username,avatar_url,role,tipo,cidade,status,created_at'
    . '&order=created_at.asc&limit=' . ADMIN_PAGE_LIMIT . '&offset=' . $offsetMembers;
$members = admin_json_get_list($membersPath);

$postsPath = '/rest/v1/posts?select=id,user_id,image_url,caption,created_at'
    . '&order=created_at.desc&limit=' . ADMIN_PAGE_LIMIT . '&offset=' . $offsetPosts;
$posts = admin_json_get_list($postsPath);

$invitesPath = '/rest/v1/invites?select=id,code,status,created_at&order=created_at.desc&limit=50';
$invites = admin_json_get_list($invitesPath);

/** @var array<string, array<string, mixed>> $memberMap */
$memberMap = [];
foreach ($members as $row) {
    if (isset($row['id'])) {
        $memberMap[(string) $row['id']] = $row;
    }
}

$postUserIds = [];
foreach ($posts as $pr) {
    if (!empty($pr['user_id'])) {
        $postUserIds[(string) $pr['user_id']] = true;
    }
}
$missingAuthorIds = array_keys(array_diff_key($postUserIds, $memberMap));
if ($missingAuthorIds !== []) {
    $inList = implode(',', $missingAuthorIds);
    $extraProfiles = admin_json_get_list('/rest/v1/profiles?select=id,display_id,username,avatar_url,role,tipo,cidade,status,created_at&id=in.(' . $inList . ')');
    foreach ($extraProfiles as $row) {
        if (isset($row['id'])) {
            $memberMap[(string) $row['id']] = $row;
        }
    }
}

$totalMembersPages = max(1, (int) ceil($total_members / ADMIN_PAGE_LIMIT));
$totalPostsPages = max(1, (int) ceil($total_posts / ADMIN_PAGE_LIMIT));

$flashStatus = (string) ($_GET['status'] ?? '');
$flashMessage = (string) ($_GET['message'] ?? '');

/**
 * @param array<string, mixed>|null $row
 */
function admin_format_dt(?array $row): string
{
    $iso = '';
    if ($row !== null && isset($row['created_at'])) {
        $iso = (string) $row['created_at'];
    }
    if ($iso === '') {
        return '—';
    }
    $ts = strtotime($iso);

    return $ts !== false ? date('d/m/Y H:i', $ts) : htmlspecialchars($iso, ENT_QUOTES, 'UTF-8');
}

/**
 * @param array<string, mixed>|null $row
 */
function admin_truncate_caption(?array $row, int $max = 80): string
{
    $cap = '';
    if ($row !== null && isset($row['caption'])) {
        $cap = trim((string) $row['caption']);
    }
    if ($cap === '') {
        return '—';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($cap, 'UTF-8') > $max) {
            return mb_substr($cap, 0, $max, 'UTF-8') . '…';
        }

        return $cap;
    }
    if (strlen($cap) > $max) {
        return substr($cap, 0, $max) . '…';
    }

    return $cap;
}

function admin_page_url(string $tab, int $mPageNum, int $pPageNum): string
{
    return '/features/admin/index.php?' . http_build_query([
        'tab' => $tab,
        'm_page' => $mPageNum,
        'p_page' => $pPageNum,
    ]);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin — Club61</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { height: 100%; }
        body {
            min-height: 100%;
            background: #0A0A0A;
            color: #fff;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding-bottom: 32px;
        }
        a { color: inherit; text-decoration: none; }
        .admin-header {
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: #0A0A0A;
            border-bottom: 1px solid #1a1a1a;
        }
        .admin-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #111;
            border: 1px solid #222;
            color: #C9A84C;
            font-size: 1.1rem;
            transition: background 0.15s ease;
        }
        .admin-back:hover { background: #1a1a1a; }
        .admin-title-wrap { flex: 1; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .admin-title { font-size: 1.15rem; font-weight: 700; letter-spacing: 0.04em; }
        .badge-admin {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            background: #7B2EFF;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
        }
        .admin-toast {
            display: none;
            margin: 12px 16px 0;
            padding: 11px 14px;
            border-radius: 8px;
            font-size: 0.88rem;
            border: 1px solid #333;
            background: #111;
            color: #C9A84C;
        }
        .admin-toast.is-visible { display: block; }
        .admin-toast.error { color: #ff6b6b; border-color: rgba(255,107,107,0.35); }
        /* Aliases pedidos (mesmo visual escuro) */
        .dark-card { background: #111; border: 1px solid #1a1a1a; border-radius: 10px; }
        .dark-btn { border-radius: 6px; border: 1px solid #333; background: #1a1a1a; color: #ddd; cursor: pointer; }
        .dark-input {
            width: 100%;
            max-width: 320px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #222;
            background: #111;
            color: #fff;
            font-size: 0.9rem;
            outline: none;
        }
        .dark-input:focus { border-color: #7B2EFF; }
        .admin-tabs.tabs-bar { }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            padding: 16px;
            max-width: 960px;
            margin: 0 auto;
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .stat-card {
            background: #111;
            border: 1px solid #1a1a1a;
            border-radius: 10px;
            padding: 14px 12px;
            text-align: center;
        }
        .stat-card:hover { background: #1a1a1a; }
        .stat-label { font-size: 0.72rem; color: #888; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px; }
        .stat-num { font-size: 1.65rem; font-weight: 800; color: #C9A84C; line-height: 1.1; }
        .tabs-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 0 16px 12px;
            max-width: 960px;
            margin: 0 auto;
        }
        .tab-btn {
            border: none;
            cursor: pointer;
            padding: 10px 14px;
            border-radius: 8px;
            background: transparent;
            color: #555;
            font-size: 0.88rem;
            font-weight: 600;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .tab-btn:hover { color: #aaa; }
        .tab-btn.is-active { background: #1e1e1e; color: #fff; }
        .tab-panel { display: none; max-width: 960px; margin: 0 auto; padding: 0 16px 24px; }
        .tab-panel.is-active { display: block; }
        .panel-inner { background: #111; border: 1px solid #1a1a1a; border-radius: 10px; padding: 16px; }
        .btn-gen {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 16px;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            background: #7B2EFF;
            color: #fff;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: box-shadow 0.2s ease, background 0.15s ease;
        }
        .btn-gen:hover { box-shadow: 0 0 20px rgba(123, 46, 255, 0.45); }
        .invite-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 12px;
        }
        .invite-card { background: #0A0A0A; border: 1px solid #222; border-radius: 10px; padding: 14px; }
        .invite-code {
            font-family: ui-monospace, monospace;
            font-size: 1rem;
            font-weight: 700;
            color: #C9A84C;
            cursor: pointer;
            word-break: break-all;
        }
        .invite-hint { font-size: 0.72rem; color: #666; margin-top: 4px; }
        .st-ok { color: #51cf66; }
        .st-used { color: #888; }
        .st-revoked { color: #ff6b6b; }
        .btn-revoke {
            margin-top: 10px;
            padding: 8px 12px;
            border: 1px solid #444;
            border-radius: 6px;
            background: #1a1a1a;
            color: #fff;
            font-size: 0.8rem;
            cursor: pointer;
        }
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .data-table th, .data-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #1a1a1a;
            text-align: left;
            vertical-align: middle;
        }
        .data-table th { color: #888; font-weight: 600; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .avatar-sm {
            width: 32px; height: 32px; border-radius: 50%; object-fit: cover;
            background: #0A0A0A; border: 1px solid #333; vertical-align: middle;
        }
        .avatar-fallback-sm {
            width: 32px; height: 32px; border-radius: 50%; background: #0A0A0A; border: 1px solid #333;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.85rem; color: #7B2EFF;
        }
        .name-cell { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .name-main { font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .name-sub { font-size: 0.75rem; color: #777; }
        .badge-role { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 0.72rem; font-weight: 700; }
        .badge-role.admin { background: rgba(201, 168, 76, 0.15); color: #C9A84C; border: 1px solid rgba(201, 168, 76, 0.35); }
        .badge-role.member { background: #1a1a1a; color: #888; border: 1px solid #333; }
        .badge-ban { display: inline-block; margin-left: 6px; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; background: rgba(255,107,107,0.15); color: #ff6b6b; border: 1px solid rgba(255,107,107,0.3); }
        .btn-action {
            display: inline-block;
            margin-right: 6px;
            margin-bottom: 4px;
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #333;
            background: #1a1a1a;
            color: #ddd;
            font-size: 0.78rem;
            cursor: pointer;
        }
        .btn-action:hover { background: #222; }
        .thumb-48 {
            width: 48px; height: 48px; object-fit: cover; border-radius: 6px;
            background: #0A0A0A; border: 1px solid #333;
        }
        .cap-cell { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #ccc; }
        .dash-hint { font-size: 0.85rem; color: #777; line-height: 1.5; margin-top: 8px; }
    </style>
</head>
<body>
    <header class="admin-header">
        <a class="admin-back" href="/features/feed/index.php" aria-label="Voltar ao feed">←</a>
        <div class="admin-title-wrap">
            <h1 class="admin-title">Painel Admin</h1>
            <span class="badge-admin">ADMIN</span>
        </div>
    </header>

    <?php if ($flashStatus === 'ok' && $flashMessage !== ''): ?>
        <div id="adminFlash" class="admin-toast is-visible" role="status"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($flashStatus === 'error' && $flashMessage !== ''): ?>
        <div id="adminFlash" class="admin-toast is-visible error" role="alert"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="tabs-bar admin-tabs" role="tablist">
        <button type="button" class="tab-btn<?= $tab === 'dashboard' ? ' is-active' : '' ?>" role="tab" data-tab="dashboard" onclick="switchTab('dashboard')">📊 Dashboard</button>
        <button type="button" class="tab-btn<?= $tab === 'members' ? ' is-active' : '' ?>" role="tab" data-tab="members" onclick="switchTab('members')">👥 Membros</button>
        <button type="button" class="tab-btn<?= $tab === 'posts' ? ' is-active' : '' ?>" role="tab" data-tab="posts" onclick="switchTab('posts')">🖼️ Posts</button>
        <button type="button" class="tab-btn<?= $tab === 'invites' ? ' is-active' : '' ?>" role="tab" data-tab="invites" onclick="switchTab('invites')">🎟️ Convites</button>
    </div>

    <!-- Dashboard -->
    <section id="panel-dashboard" class="tab-panel<?= $tab === 'dashboard' ? ' is-active' : '' ?>" role="tabpanel" data-panel="dashboard" <?= $tab === 'dashboard' ? '' : 'hidden' ?>>
        <div class="stats-grid">
            <div class="stat-card dark-card">
                <div class="stat-label">Total membros</div>
                <div class="stat-num"><?= htmlspecialchars((string) $total_members, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="stat-card dark-card">
                <div class="stat-label">Posts hoje</div>
                <div class="stat-num"><?= htmlspecialchars((string) $posts_today, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="stat-card dark-card">
                <div class="stat-label">Novos membros hoje</div>
                <div class="stat-num"><?= htmlspecialchars((string) $new_members_today, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="stat-card dark-card">
                <div class="stat-label">Total posts</div>
                <div class="stat-num"><?= htmlspecialchars((string) $total_posts, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <div class="panel-inner dark-card" style="margin:0 auto;max-width:960px">
            <p class="dash-hint">
                Resumo rápido. Convites ativos: <strong style="color:#C9A84C"><?= htmlspecialchars((string) $totalInvitesAvailable, ENT_QUOTES, 'UTF-8') ?></strong>
                · Admins: <strong style="color:#C9A84C"><?= htmlspecialchars((string) $totalAdmins, ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
            <p class="dash-hint" style="margin-top:12px;color:#555;font-size:0.8rem">
                Banimento usa <code style="color:#888">profiles.status = 'banned'</code>. Se o PATCH falhar, adicione a coluna no Supabase.
            </p>
        </div>
    </section>

    <!-- Membros -->
    <section id="panel-members" class="tab-panel<?= $tab === 'members' ? ' is-active' : '' ?>" role="tabpanel" data-panel="members" <?= $tab === 'members' ? '' : 'hidden' ?>>
        <div class="panel-inner dark-card" style="overflow-x:auto">
            <div style="margin-bottom:12px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
                <label for="memberSearch" class="stat-label" style="margin:0">Buscar</label>
                <input type="search" id="memberSearch" class="dark-input" placeholder="Nome, @user, tipo, cidade..." autocomplete="off">
            </div>
            <table class="data-table" id="membersTable">
                <thead>
                    <tr>
                        <th>Membro</th>
                        <th>Tipo / Cidade</th>
                        <th>Role / Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($members as $mem): ?>
                    <?php

                    $mid = isset($mem['id']) ? (string) $mem['id'] : '';
                    $lbl = clLabel($mem);
                    $un = isset($mem['username']) ? trim((string) $mem['username']) : '';
                    $av = isset($mem['avatar_url']) ? trim((string) $mem['avatar_url']) : '';
                    $tipo = isset($mem['tipo']) ? trim((string) $mem['tipo']) : '';
                    $cid = isset($mem['cidade']) ? trim((string) $mem['cidade']) : '';
                    $role = isset($mem['role']) ? (string) $mem['role'] : 'member';
                    $pstatus = isset($mem['status']) ? trim((string) $mem['status']) : '';
                    $isSelf = ($mid !== '' && $mid === $current_user_id);
                    $searchBlob = strtolower($lbl . ' ' . ($un !== '' ? '@' . $un : '') . ' ' . $tipo . ' ' . $cid . ' ' . $pstatus . ' ' . $role);
                    ?>
                    <tr data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
                        <td>
                            <div class="name-cell">
                                <?php if ($av !== ''): ?>
                                    <img class="avatar-sm" src="<?= htmlspecialchars($av, ENT_QUOTES, 'UTF-8') ?>" alt="">
                                <?php else: ?>
                                    <span class="avatar-fallback-sm" aria-hidden="true">&#128100;</span>
                                <?php endif; ?>
                                <div style="min-width:0">
                                    <div class="name-main"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if ($un !== ''): ?>
                                        <div class="name-sub">@<?= htmlspecialchars($un, ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?= htmlspecialchars($tipo !== '' ? $tipo : '—', ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($cid !== ''): ?>
                                <span style="color:#555"> · </span><?= htmlspecialchars($cid, ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($role === 'admin'): ?>
                                <span class="badge-role admin">★ Admin</span>
                            <?php else: ?>
                                <span class="badge-role member">Membro</span>
                            <?php endif; ?>
                            <?php if ($pstatus === 'banned'): ?>
                                <span class="badge-ban">BANIDO</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$isSelf): ?>
                                <?php if ($role !== 'admin'): ?>
                                    <form method="post" action="" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="return_tab" value="members">
                                        <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                                        <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                                        <input type="hidden" name="action" value="set_role">
                                        <input type="hidden" name="uid" value="<?= htmlspecialchars($mid, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="role" value="admin">
                                        <button type="submit" class="btn-action dark-btn">↑ Promover</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="return_tab" value="members">
                                        <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                                        <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                                        <input type="hidden" name="action" value="set_role">
                                        <input type="hidden" name="uid" value="<?= htmlspecialchars($mid, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="role" value="member">
                                        <button type="submit" class="btn-action dark-btn">↓ Rebaixar</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="" style="display:inline" onsubmit="return confirm('Banir este membro?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="return_tab" value="members">
                                    <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                                    <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                                    <input type="hidden" name="action" value="ban_member">
                                    <input type="hidden" name="uid" value="<?= htmlspecialchars($mid, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn-action dark-btn" style="border-color:#553;color:#ff6b6b">Banir</button>
                                </form>
                                <form method="post" action="" style="display:inline" onsubmit="return confirm('Remover este membro do profiles?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="return_tab" value="members">
                                    <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                                    <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                                    <input type="hidden" name="action" value="remove_member">
                                    <input type="hidden" name="uid" value="<?= htmlspecialchars($mid, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn-action dark-btn">✕ Remover</button>
                                </form>
                            <?php else: ?>
                                <span style="color:#555;font-size:0.8rem">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php

        if ($totalMembersPages > 1) {
            $prevM = max(1, $mPage - 1);
            $nextM = min($totalMembersPages, $mPage + 1);
            ?>
            <div class="pagination dark-card" style="max-width:960px;margin:12px auto 0;padding:10px 12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;font-size:0.85rem;color:#888">
                <span>Membros · Página <?= htmlspecialchars((string) $mPage, ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars((string) $totalMembersPages, ENT_QUOTES, 'UTF-8') ?></span>
                <span>
                    <?php if ($mPage > 1): ?>
                        <a class="dark-btn btn-action" style="text-decoration:none;display:inline-block" href="<?= htmlspecialchars(admin_page_url('members', $prevM, $pPage), ENT_QUOTES, 'UTF-8') ?>">← Anterior</a>
                    <?php endif; ?>
                    <?php if ($mPage < $totalMembersPages): ?>
                        <a class="dark-btn btn-action" style="text-decoration:none;display:inline-block;margin-left:8px" href="<?= htmlspecialchars(admin_page_url('members', $nextM, $pPage), ENT_QUOTES, 'UTF-8') ?>">Próxima →</a>
                    <?php endif; ?>
                </span>
            </div>
            <?php

        }
        ?>
    </section>

    <!-- Posts -->
    <section id="panel-posts" class="tab-panel<?= $tab === 'posts' ? ' is-active' : '' ?>" role="tabpanel" data-panel="posts" <?= $tab === 'posts' ? '' : 'hidden' ?>>
        <div class="panel-inner dark-card" style="overflow-x:auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Autor</th>
                        <th>Legenda</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($posts as $post): ?>
                    <?php

                    $pid = isset($post['id']) ? (string) $post['id'] : '';
                    $uid = isset($post['user_id']) ? (string) $post['user_id'] : '';
                    $author = $uid !== '' && isset($memberMap[$uid]) ? $memberMap[$uid] : [];
                    $authLbl = $author !== [] ? clLabel($author) : 'Membro';
                    $img = isset($post['image_url']) ? trim((string) $post['image_url']) : '';
                    $capShow = htmlspecialchars(admin_truncate_caption($post), ENT_QUOTES, 'UTF-8');
                    $dt = admin_format_dt($post);
                    ?>
                    <tr>
                        <td>
                            <?php if ($img !== ''): ?>
                                <img class="thumb-48" src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="">
                            <?php else: ?>
                                <span class="thumb-48" style="display:inline-flex;align-items:center;justify-content:center;font-size:1.2rem;background:#0A0A0A">🖼</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($authLbl, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><div class="cap-cell" title="<?= $capShow ?>"><?= $capShow ?></div></td>
                        <td style="white-space:nowrap;color:#888;font-size:0.8rem"><?= $dt ?></td>
                        <td>
                            <?php if ($pid !== '' && $uid !== ''): ?>
                                <form method="post" action="" style="display:inline" onsubmit="return confirm('Remover este post?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="return_tab" value="posts">
                                    <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                                    <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                                    <input type="hidden" name="action" value="delete_post">
                                    <input type="hidden" name="pid" value="<?= htmlspecialchars($pid, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn-action dark-btn" aria-label="Remover post">✕ Post</button>
                                </form>
                                <form method="post" action="" style="display:inline" onsubmit="return confirm('Apagar TODOS os posts deste usuário?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="return_tab" value="posts">
                                    <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                                    <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                                    <input type="hidden" name="action" value="delete_all_user_posts">
                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn-action dark-btn" style="border-color:#553;color:#ff6b6b">🗑 Todos</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php

        if ($totalPostsPages > 1) {
            $prevP = max(1, $pPage - 1);
            $nextP = min($totalPostsPages, $pPage + 1);
            ?>
            <div class="pagination dark-card" style="max-width:960px;margin:12px auto 0;padding:10px 12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;font-size:0.85rem;color:#888">
                <span>Posts · Página <?= htmlspecialchars((string) $pPage, ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars((string) $totalPostsPages, ENT_QUOTES, 'UTF-8') ?></span>
                <span>
                    <?php if ($pPage > 1): ?>
                        <a class="dark-btn btn-action" style="text-decoration:none;display:inline-block" href="<?= htmlspecialchars(admin_page_url('posts', $mPage, $prevP), ENT_QUOTES, 'UTF-8') ?>">← Anterior</a>
                    <?php endif; ?>
                    <?php if ($pPage < $totalPostsPages): ?>
                        <a class="dark-btn btn-action" style="text-decoration:none;display:inline-block;margin-left:8px" href="<?= htmlspecialchars(admin_page_url('posts', $mPage, $nextP), ENT_QUOTES, 'UTF-8') ?>">Próxima →</a>
                    <?php endif; ?>
                </span>
            </div>
            <?php

        }
        ?>
    </section>

    <!-- Convites -->
    <section id="panel-invites" class="tab-panel<?= $tab === 'invites' ? ' is-active' : '' ?>" role="tabpanel" data-panel="invites" <?= $tab === 'invites' ? '' : 'hidden' ?>>
        <div class="panel-inner dark-card">
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="return_tab" value="invites">
                <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                <input type="hidden" name="action" value="gen_invite">
                <button type="submit" class="btn-gen dark-btn" style="border:none;background:#7B2EFF">＋ Gerar novo convite</button>
            </form>
            <div class="invite-grid">
                <?php foreach ($invites as $inv): ?>
                    <?php

                    $ic = isset($inv['code']) ? (string) $inv['code'] : '';
                    $st = isset($inv['status']) ? (string) $inv['status'] : '';
                    ?>
                    <div class="invite-card dark-card">
                        <div>
                            <span class="invite-code" data-copy="<?= htmlspecialchars($ic, ENT_QUOTES, 'UTF-8') ?>" onclick="copyCode(this.dataset.copy, this)"><?= htmlspecialchars($ic, ENT_QUOTES, 'UTF-8') ?></span>
                            <div class="invite-hint">clique para copiar <span class="copy-feedback" style="display:none;color:#51cf66;font-weight:600"></span></div>
                        </div>
                        <div style="margin-top:10px;font-size:0.82rem;">
                            <?php if ($st === 'available'): ?>
                                <span class="st-ok" title="Disponível">✅ Disponível</span>
                            <?php elseif ($st === 'used'): ?>
                                <span class="st-used" title="Usado">✔ Usado</span>
                            <?php elseif ($st === 'revoked'): ?>
                                <span class="st-revoked" title="Revogado">✕ Revogado</span>
                            <?php else: ?>
                                <span class="st-used"><?= htmlspecialchars($st !== '' ? $st : '—', ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($st === 'available' && $ic !== ''): ?>
                            <form method="post" action="" onsubmit="return confirm('Revogar este convite?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="return_tab" value="invites">
                                <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                                <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                                <input type="hidden" name="action" value="revoke_invite">
                                <input type="hidden" name="code" value="<?= htmlspecialchars($ic, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn-revoke dark-btn">Revogar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <script>
    (function () {
        var initialTab = <?= json_encode($tab, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        switchTab(initialTab);

        var search = document.getElementById('memberSearch');
        if (search) {
            search.addEventListener('input', function () {
                var q = (this.value || '').toLowerCase().trim();
                var rows = document.querySelectorAll('#membersTable tbody tr');
                rows.forEach(function (tr) {
                    var hay = (tr.getAttribute('data-search') || '').toLowerCase();
                    tr.style.display = (q === '' || hay.indexOf(q) !== -1) ? '' : 'none';
                });
            });
        }
    })();

    function switchTab(name) {
        var tabs = document.querySelectorAll('.tab-btn');
        var panels = document.querySelectorAll('.tab-panel');
        tabs.forEach(function (btn) {
            var t = btn.getAttribute('data-tab');
            var on = t === name;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach(function (p) {
            var pn = p.getAttribute('data-panel');
            var on = pn === name;
            p.classList.toggle('is-active', on);
            if (on) { p.removeAttribute('hidden'); } else { p.setAttribute('hidden', 'hidden'); }
        });
    }

    function copyCode(code, el) {
        if (!code) return;
        var hint = el && el.parentElement ? el.parentElement.querySelector('.copy-feedback') : null;
        function showOk() {
            if (hint) { hint.style.display = 'inline'; hint.textContent = '✓ Copiado!'; }
            setTimeout(function () {
                if (hint) { hint.style.display = 'none'; hint.textContent = ''; }
            }, 1500);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(showOk).catch(function () {
                window.prompt('Copie o código:', code);
            });
        } else {
            window.prompt('Copie o código:', code);
        }
    }
    </script>
</body>
</html>
