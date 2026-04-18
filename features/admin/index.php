<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__, 2) . '/php_errors.log');
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/session.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/csrf.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';

// Service role obrigatória para ler perfil e operações admin (variáveis vêm de .env via config/supabase.php)
if (!defined('SUPABASE_URL') || SUPABASE_URL === ''
    || !defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    error_log('[admin] SUPABASE_URL ou SUPABASE_SERVICE_KEY ausentes.');
    header('Location: /features/feed/index.php?status=error&message=' . urlencode('Serviço indisponível.'));
    exit;
}

if (!function_exists('curl_init')) {
    error_log('[admin] Extensão PHP curl não está disponível.');
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Admin</title></head><body style="background:#0A0A0A;color:#fff;font-family:system-ui;padding:24px">';
    echo '<p>O painel admin precisa da extensão <strong>curl</strong> do PHP ativa no servidor.</p></body></html>';
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
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
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
    if (!is_array($rows)) {
        return [];
    }
    // PostgREST devolve objeto de erro em vez de array de linhas
    if (isset($rows['code'], $rows['message']) && !isset($rows[0])) {
        error_log('[admin] PostgREST: ' . (string) $rows['message']);
        return [];
    }
    if ($rows === []) {
        return [];
    }
    // Lista de registos (preferido)
    if (function_exists('array_is_list')) {
        if (array_is_list($rows)) {
            return $rows;
        }
    } else {
        $keys = array_keys($rows);
        $expected = range(0, count($rows) - 1);
        if ($keys === $expected) {
            return $rows;
        }
    }
    // Único objeto { id: ... }
    if (isset($rows['id'])) {
        return [$rows];
    }

    return [];
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

function admin_now_iso_utc(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

/**
 * Rótulo de role na UI (membro | admin; legado member).
 */
function admin_role_is_membro(string $role): bool
{
    $r = strtolower(trim($role));

    return $r === 'membro' || $r === 'member';
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
    return club61_display_id_label(isset($p['display_id']) ? (string) $p['display_id'] : null);
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
    // Tabs na URL: dashboard | convites | usuarios | posts | stories (aliases legados abaixo)
    $tabAliasesPost = ['invites' => 'convites', 'members' => 'usuarios'];
    if (isset($tabAliasesPost[$tab])) {
        $tab = $tabAliasesPost[$tab];
    }
    $allowedTabs = ['dashboard', 'convites', 'usuarios', 'posts', 'stories'];
    if (!in_array($tab, $allowedTabs, true)) {
        $tab = 'dashboard';
    }

    $mPage = max(1, (int) ($_POST['m_page'] ?? 1));
    $pPage = max(1, (int) ($_POST['p_page'] ?? 1));
    $stPage = max(1, (int) ($_POST['st_page'] ?? 1));
    $pageQs = [
        'm_page' => $mPage,
        'p_page' => $pPage,
        'st_page' => $stPage,
    ];

    if ($action === 'gerar_convite' || $action === 'gen_invite') {
        $code = strtolower(bin2hex(random_bytes(6)));
        $expires = gmdate('c', time() + 7 * 86400);
        $payload = [
            'code' => $code,
            'created_by' => $current_user_id,
            'expires_at' => $expires,
        ];
        admin_json_post('/rest/v1/invites', json_encode($payload, JSON_UNESCAPED_SLASHES));
        admin_redirect_prg('convites', 'Convite gerado: ' . $code, 'ok', $pageQs);
    }

    if ($action === 'enviar_convite_email' || $action === 'enviar_convite_sms') {
        require_once CLUB61_ROOT . '/config/invite_notify.php';
        $code = strtolower(bin2hex(random_bytes(6)));
        $expires = gmdate('c', time() + 7 * 86400);
        $payload = [
            'code' => $code,
            'created_by' => $current_user_id,
            'expires_at' => $expires,
        ];
        if ($action === 'enviar_convite_email') {
            $email = trim((string) ($_POST['email_destino'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                admin_redirect_prg('convites', 'E-mail inválido.', 'error', $pageQs);
            }
            $httpCode = admin_json_post('/rest/v1/invites', json_encode($payload, JSON_UNESCAPED_SLASHES));
            if ($httpCode < 200 || $httpCode >= 300) {
                admin_redirect_prg('convites', 'Falha ao gravar convite no Supabase (HTTP ' . $httpCode . ').', 'error', $pageQs);
            }
            $err = '';
            if (!club61_send_invite_email($email, $code, $err)) {
                admin_redirect_prg('convites', 'Convite criado (' . $code . ') mas o e-mail não foi enviado. ' . $err, 'error', $pageQs);
            }
            admin_redirect_prg('convites', 'Convite ' . $code . ' enviado por e-mail.', 'ok', $pageQs);
        }
        $phoneRaw = trim((string) ($_POST['telefone_destino'] ?? ''));
        $e164 = club61_normalize_br_phone($phoneRaw);
        if ($e164 === null) {
            admin_redirect_prg('convites', 'Telefone inválido. Use DDD + número (ex.: 11999998888).', 'error', $pageQs);
        }
        $httpCode = admin_json_post('/rest/v1/invites', json_encode($payload, JSON_UNESCAPED_SLASHES));
        if ($httpCode < 200 || $httpCode >= 300) {
            admin_redirect_prg('convites', 'Falha ao gravar convite no Supabase (HTTP ' . $httpCode . ').', 'error', $pageQs);
        }
        $err = '';
        if (!club61_send_invite_sms($e164, $code, $err)) {
            admin_redirect_prg('convites', 'Convite criado (' . $code . ') mas o SMS não foi enviado. ' . $err, 'error', $pageQs);
        }
        admin_redirect_prg('convites', 'Convite ' . $code . ' enviado por SMS (' . $e164 . ').', 'ok', $pageQs);
    }

    if ($action === 'excluir_convite') {
        $inviteId = trim((string) ($_POST['invite_id'] ?? ''));
        if ($inviteId !== '') {
            $rows = admin_json_get_list('/rest/v1/invites?id=eq.' . urlencode($inviteId) . '&select=id,used_by');
            if ($rows !== [] && ($rows[0]['used_by'] ?? null) === null) {
                admin_http_delete('/rest/v1/invites?id=eq.' . urlencode($inviteId));
                admin_redirect_prg('convites', 'Convite excluído.', 'ok', $pageQs);
            }
        }
        admin_redirect_prg('convites', 'Não foi possível excluir (já usado ou inválido).', 'error', $pageQs);
    }

    if ($action === 'revoke_invite') {
        $code = trim((string) ($_POST['code'] ?? ''));
        if ($code !== '') {
            admin_json_patch(
                '/rest/v1/invites?code=eq.' . urlencode($code),
                json_encode(['status' => 'revoked'], JSON_UNESCAPED_SLASHES)
            );
        }
        admin_redirect_prg('convites', 'Convite revogado.', 'ok', $pageQs);
    }

    if ($action === 'alterar_role' || $action === 'set_role') {
        $uid = trim((string) ($_POST['target_user_id'] ?? $_POST['uid'] ?? ''));
        $role = trim((string) ($_POST['nova_role'] ?? $_POST['role'] ?? ''));
        if ($role === 'member') {
            $role = 'membro';
        }
        if ($uid !== '' && $uid !== $current_user_id && ($role === 'admin' || $role === 'membro')) {
            $patch = [
                'role' => $role,
                'is_admin' => $role === 'admin',
            ];
            admin_json_patch(
                '/rest/v1/profiles?id=eq.' . urlencode($uid),
                json_encode($patch, JSON_UNESCAPED_SLASHES)
            );
        }
        admin_redirect_prg('usuarios', 'Função atualizada.', 'ok', $pageQs);
    }

    if ($action === 'excluir_usuario') {
        $exUid = trim((string) ($_POST['target_user_id'] ?? ''));
        if ($exUid !== '' && $exUid === $current_user_id) {
            admin_redirect_prg('usuarios', 'Você não pode excluir a própria conta por aqui.', 'error', $pageQs);
        }
        admin_redirect_prg('usuarios', 'Exclusão permanente de conta ainda não está disponível.', 'error', $pageQs);
    }

    if ($action === 'ban_member') {
        $uid = trim((string) ($_POST['uid'] ?? ''));
        if ($uid !== '' && $uid !== $current_user_id) {
            admin_json_patch(
                '/rest/v1/profiles?id=eq.' . urlencode($uid),
                json_encode(['status' => 'banned'], JSON_UNESCAPED_SLASHES)
            );
        }
        admin_redirect_prg('usuarios', 'Membro banido (status=banned).', 'ok', $pageQs);
    }

    if ($action === 'remove_member') {
        $uid = trim((string) ($_POST['uid'] ?? ''));
        if ($uid !== '' && $uid !== $current_user_id) {
            admin_http_delete('/rest/v1/profiles?id=eq.' . urlencode($uid));
        }
        admin_redirect_prg('usuarios', 'Membro removido.', 'ok', $pageQs);
    }

    if ($action === 'excluir_post' || $action === 'delete_post') {
        $pid = trim((string) ($_POST['post_id'] ?? $_POST['pid'] ?? ''));
        if ($pid !== '') {
            admin_http_delete('/rest/v1/posts?id=eq.' . urlencode($pid));
        }
        admin_redirect_prg('posts', 'Post removido.', 'ok', $pageQs);
    }

    if ($action === 'excluir_story') {
        $sid = trim((string) ($_POST['story_id'] ?? ''));
        if ($sid !== '') {
            admin_http_delete('/rest/v1/stories?id=eq.' . urlencode($sid));
        }
        admin_redirect_prg('stories', 'Story removido.', 'ok', $pageQs);
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

try {
$csrf = admin_csrf_token();

$tab = (string) ($_GET['tab'] ?? 'dashboard');
// Navegação: ?tab=convites|usuarios|... — aliases legados (invites/members) mantidos
$tabAliasesGet = [
    'invites' => 'convites',
    'members' => 'usuarios',
];
if (isset($tabAliasesGet[$tab])) {
    $tab = $tabAliasesGet[$tab];
}
$allowedTabsGet = ['dashboard', 'convites', 'usuarios', 'posts', 'stories'];
if (!in_array($tab, $allowedTabsGet, true)) {
    $tab = 'dashboard';
}

$mPage = max(1, (int) ($_GET['m_page'] ?? 1));
$pPage = max(1, (int) ($_GET['p_page'] ?? 1));
$stPage = max(1, (int) ($_GET['st_page'] ?? 1));
$offsetMembers = ($mPage - 1) * ADMIN_PAGE_LIMIT;
$offsetPosts = ($pPage - 1) * ADMIN_PAGE_LIMIT;
$offsetStories = ($stPage - 1) * ADMIN_PAGE_LIMIT;

$startUtc = admin_utc_midnight_iso();
$nowIso = admin_now_iso_utc();

$total_members = admin_count_exact('/rest/v1/profiles?select=id');
$total_posts = admin_count_exact('/rest/v1/posts?select=id');
$totalStoriesAll = admin_count_exact('/rest/v1/stories?select=id');
$total_stories_active = admin_count_exact('/rest/v1/stories?select=id&expires_at=gt.' . rawurlencode($nowIso));
$posts_today = admin_count_exact('/rest/v1/posts?select=id&created_at=gte.' . rawurlencode($startUtc));
$new_members_today = admin_count_exact('/rest/v1/profiles?select=id&created_at=gte.' . rawurlencode($startUtc));

$convitesDisp = admin_count_exact('/rest/v1/invites?select=id&used_by=is.null&expires_at=gt.' . rawurlencode($nowIso));
$convitesUsados = admin_count_exact('/rest/v1/invites?select=id&used_by=not.is.null');
$totalInvitesAvailable = admin_count_exact('/rest/v1/invites?select=id&status=eq.available');
// Admins: role=admin OU (is_admin sem ser já contado em role=admin) — evita filtro or= frágil no PostgREST
$totalAdmins = admin_count_exact('/rest/v1/profiles?select=id&role=eq.admin')
    + admin_count_exact('/rest/v1/profiles?select=id&is_admin=eq.true&role=neq.admin');

$membersPath = '/rest/v1/profiles?select=id,display_id,avatar_url,role,status,is_admin,created_at,cidade'
    . '&order=created_at.asc&limit=' . ADMIN_PAGE_LIMIT . '&offset=' . $offsetMembers;
$members = admin_json_get_list($membersPath);

$postsPath = '/rest/v1/posts?select=id,user_id,image_url,caption,created_at'
    . '&order=created_at.desc&limit=' . ADMIN_PAGE_LIMIT . '&offset=' . $offsetPosts;
$posts = admin_json_get_list($postsPath);

$invitesPath = '/rest/v1/invites?select=*&order=created_at.desc&limit=80';
$invites = admin_json_get_list($invitesPath);

$storiesPath = '/rest/v1/stories?select=id,user_id,image_url,created_at,expires_at&expires_at=gt.' . rawurlencode($nowIso)
    . '&order=created_at.desc&limit=' . ADMIN_PAGE_LIMIT . '&offset=' . $offsetStories;
$stories = admin_json_get_list($storiesPath);

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
    $extraProfiles = admin_json_get_list('/rest/v1/profiles?select=id,display_id,avatar_url,role,status,created_at,cidade&id=in.(' . $inList . ')');
    foreach ($extraProfiles as $row) {
        if (isset($row['id'])) {
            $memberMap[(string) $row['id']] = $row;
        }
    }
}

$postStoryIds = [];
foreach ($stories as $sr) {
    if (!empty($sr['user_id'])) {
        $postStoryIds[(string) $sr['user_id']] = true;
    }
}
$missingStoryAuthors = array_keys(array_diff_key($postStoryIds, $memberMap));
if ($missingStoryAuthors !== []) {
    $inListS = implode(',', $missingStoryAuthors);
    $extraStoryProf = admin_json_get_list('/rest/v1/profiles?select=id,display_id,avatar_url,role,status,created_at,cidade&id=in.(' . $inListS . ')');
    foreach ($extraStoryProf as $row) {
        if (isset($row['id'])) {
            $memberMap[(string) $row['id']] = $row;
        }
    }
}

/** @var array<string, string> $displayIdByUserId — display_id público por user id (UUID nunca na UI) */
$displayIdByUserId = [];
foreach ($memberMap as $uid => $row) {
    $displayIdByUserId[$uid] = clLabel($row);
}
$inviteUsedMissing = [];
foreach ($invites as $inv) {
    if (!empty($inv['used_by'])) {
        $ub = (string) $inv['used_by'];
        if (!isset($displayIdByUserId[$ub])) {
            $inviteUsedMissing[$ub] = true;
        }
    }
}
if ($inviteUsedMissing !== []) {
    $inListU = implode(',', array_keys($inviteUsedMissing));
    $extraUsedProf = admin_json_get_list('/rest/v1/profiles?select=id,display_id&id=in.(' . $inListU . ')');
    foreach ($extraUsedProf as $row) {
        if (isset($row['id'])) {
            $displayIdByUserId[(string) $row['id']] = clLabel($row);
        }
    }
}

$totalMembersPages = max(1, (int) ceil($total_members / ADMIN_PAGE_LIMIT));
$totalPostsPages = max(1, (int) ceil($total_posts / ADMIN_PAGE_LIMIT));
$totalStoriesPages = max(1, (int) ceil(max(0, $total_stories_active) / ADMIN_PAGE_LIMIT));

$flashStatus = (string) ($_GET['status'] ?? '');
$flashMessage = (string) ($_GET['message'] ?? '');
} catch (Throwable $e) {
    error_log('[admin] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin — erro</title></head>';
    echo '<body style="background:#0A0A0A;color:#e0e0e0;font-family:system-ui,sans-serif;padding:24px;max-width:560px">';
    echo '<h1 style="color:#C9A84C;font-size:1.1rem">Painel admin temporariamente indisponível</h1>';
    echo '<p>Ocorreu um erro ao carregar dados. Detalhes foram registados no log do servidor (PHP error log).</p>';
    echo '<p><a href="/features/feed/index.php" style="color:#7B2EFF">Voltar ao feed</a></p></body></html>';
    exit;
}

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

function admin_format_iso(?string $iso): string
{
    $iso = trim((string) $iso);
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

function admin_page_url(string $tab, int $mPageNum, int $pPageNum, int $stPageNum = 1): string
{
    return '/features/admin/index.php?' . http_build_query([
        'tab' => $tab,
        'm_page' => $mPageNum,
        'p_page' => $pPageNum,
        'st_page' => $stPageNum,
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
        :root {
            --bg: #0A0A0A;
            --card: #111111;
            --border: #222222;
            --gold: #C9A84C;
            --purple: #7B2EFF;
            --text: #FFFFFF;
            --muted: #888888;
            --danger: #FF4444;
            --success: #2ECC71;
            --row-a: #111111;
            --row-b: #0d0d0d;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { height: 100%; }
        body {
            min-height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        a { color: inherit; text-decoration: none; }
        .admin-shell { display: flex; min-height: 100vh; }
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 200;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--gold);
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .sidebar-toggle:hover { background: #1a1a1a; }
        .admin-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 240px;
            height: 100vh;
            background: var(--card);
            border-right: 1px solid var(--border);
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            z-index: 150;
            transition: transform 0.25s ease;
        }
        .sidebar-brand {
            padding: 0 20px 16px;
            font-weight: 700;
            font-size: 1rem;
            color: var(--gold);
            letter-spacing: 0.06em;
        }
        .admin-nav { flex: 1; display: flex; flex-direction: column; gap: 4px; padding: 0 10px; }
        .admin-nav a {
            display: block;
            padding: 10px 12px;
            border-radius: 8px;
            color: var(--muted);
            font-size: 0.9rem;
            transition: background 0.2s ease, color 0.2s ease;
        }
        .admin-nav a:hover { background: #1a1a1a; color: var(--text); }
        .admin-nav a.is-active { background: #1e1e1e; color: var(--text); border-left: 3px solid var(--gold); }
        .sidebar-feed {
            margin: 12px 16px 0;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            color: var(--gold);
            font-size: 0.85rem;
            text-align: center;
            transition: background 0.2s ease;
        }
        .sidebar-feed:hover { background: rgba(201, 168, 76, 0.08); }
        .admin-main {
            flex: 1;
            margin-left: 240px;
            min-width: 0;
            padding: 20px 20px 40px;
        }
        .main-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .main-title { font-size: 1.25rem; font-weight: 700; }
        .badge-admin-inline {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            background: var(--purple);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.08em;
        }
        .admin-toast {
            display: none;
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 0.88rem;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--gold);
        }
        .admin-toast.is-visible { display: block; }
        .admin-toast.error { color: #ff6b6b; border-color: rgba(255,107,107,0.35); }
        .stats-grid-dash {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 8px;
        }
        @media (max-width: 900px) {
            .stats-grid-dash { grid-template-columns: repeat(2, 1fr); }
        }
        .stat-card-dash {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px 14px;
            text-align: center;
            transition: background 0.2s ease;
        }
        .stat-card-dash:hover { background: #161616; }
        .stat-num-dash {
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--gold);
            line-height: 1.15;
            margin-bottom: 8px;
        }
        .stat-label-dash {
            font-size: 0.8rem;
            color: var(--muted);
            line-height: 1.3;
        }
        .panel-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
        }
        .panel-toolbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .panel-toolbar h2 { font-size: 1rem; font-weight: 600; }
        .invite-send-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 768px) {
            .invite-send-grid { grid-template-columns: 1fr; }
        }
        .invite-send-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            background: var(--row-a);
        }
        .btn-gold {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(180deg, #d4b45c, var(--gold));
            color: #111;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            transition: filter 0.2s ease, transform 0.15s ease;
        }
        .btn-gold:hover { filter: brightness(1.08); }
        .dark-input {
            width: 100%;
            max-width: 360px;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text);
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s ease;
        }
        .dark-input:focus { border-color: var(--purple); }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.86rem;
        }
        .data-table th, .data-table td {
            padding: 11px 10px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .data-table thead th {
            color: var(--muted);
            font-weight: 600;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .data-table tbody tr:nth-child(odd) { background: var(--row-a); }
        .data-table tbody tr:nth-child(even) { background: var(--row-b); }
        .mono-code {
            font-family: ui-monospace, 'Cascadia Code', monospace;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--gold);
            letter-spacing: 0.04em;
        }
        .badge-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-pill.ok { background: rgba(46, 204, 113, 0.15); color: var(--success); border: 1px solid rgba(46, 204, 113, 0.35); }
        .badge-pill.muted { background: #1a1a1a; color: var(--muted); border: 1px solid #333; }
        .badge-pill.admin { background: rgba(201, 168, 76, 0.15); color: var(--gold); border: 1px solid rgba(201, 168, 76, 0.35); }
        .badge-pill.membro { background: #1a1a1a; color: var(--muted); border: 1px solid #333; }
        .btn-sm {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #1a1a1a;
            color: #ddd;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background 0.2s ease, border-color 0.2s ease;
        }
        .btn-sm:hover { background: #222; }
        .btn-purple {
            border-color: rgba(123, 46, 255, 0.5);
            background: rgba(123, 46, 255, 0.2);
            color: #e0d0ff;
        }
        .btn-purple:hover { background: rgba(123, 46, 255, 0.35); }
        .btn-danger {
            border-color: rgba(255, 68, 68, 0.45);
            background: rgba(255, 68, 68, 0.12);
            color: #ff8a8a;
        }
        .btn-danger:hover { background: rgba(255, 68, 68, 0.22); }
        .btn-excluir-dark {
            border-color: #442222;
            background: #1a0a0a;
            color: #cc6666;
        }
        .btn-excluir-dark:hover { background: #2a1010; }
        .btn-sm:disabled, .btn-sm[disabled] {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .posts-cards { display: flex; flex-direction: column; gap: 12px; }
        .post-admin-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
            background: var(--row-b);
            display: grid;
            gap: 8px;
        }
        .post-admin-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px 16px;
            font-size: 0.88rem;
        }
        .post-admin-author { font-weight: 600; color: var(--gold); }
        .post-admin-date { color: var(--muted); font-size: 0.8rem; }
        .post-admin-snippet { color: #ccc; font-size: 0.88rem; line-height: 1.45; word-break: break-word; }
        .stories-grid-adm {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }
        @media (max-width: 768px) {
            .stories-grid-adm { grid-template-columns: 1fr; }
        }
        @media (min-width: 769px) and (max-width: 1024px) {
            .stories-grid-adm { grid-template-columns: repeat(2, 1fr); }
        }
        .story-cell {
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            background: var(--row-a);
        }
        .story-cell-img {
            width: 100%;
            aspect-ratio: 9/16;
            max-height: 220px;
            object-fit: cover;
            background: #0a0a0a;
            display: block;
        }
        .story-cell-body { padding: 10px; font-size: 0.82rem; }
        .story-cell-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
        .admin-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 300;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .admin-modal.is-open { display: flex; }
        .admin-modal-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.72);
        }
        .admin-modal-card {
            position: relative;
            z-index: 1;
            max-width: 420px;
            width: 100%;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 16px 48px rgba(0,0,0,0.55);
        }
        .admin-modal-card p { color: #ddd; font-size: 0.92rem; line-height: 1.5; margin-bottom: 18px; }
        .admin-modal-actions { display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
        .btn-modal-cancel {
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .btn-modal-cancel:hover { background: #1a1a1a; color: var(--text); }
        .btn-modal-ok {
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid #662222;
            background: #2a1010;
            color: #ff8888;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s ease;
        }
        .btn-modal-ok:hover { background: #3a1818; }
        @media (max-width: 900px) {
            .sidebar-toggle { display: block; }
            .admin-sidebar {
                transform: translateX(-100%);
                box-shadow: 8px 0 24px rgba(0,0,0,0.4);
            }
            body.sidebar-open .admin-sidebar { transform: translateX(0); }
            .admin-main { margin-left: 0; padding-top: 56px; }
        }
    </style>
</head>
<body>
<?php
$hDash = htmlspecialchars(admin_page_url('dashboard', $mPage, $pPage, $stPage), ENT_QUOTES, 'UTF-8');
$hConv = htmlspecialchars(admin_page_url('convites', $mPage, $pPage, $stPage), ENT_QUOTES, 'UTF-8');
$hUser = htmlspecialchars(admin_page_url('usuarios', $mPage, $pPage, $stPage), ENT_QUOTES, 'UTF-8');
$hPost = htmlspecialchars(admin_page_url('posts', $mPage, $pPage, $stPage), ENT_QUOTES, 'UTF-8');
$hStory = htmlspecialchars(admin_page_url('stories', $mPage, $pPage, $stPage), ENT_QUOTES, 'UTF-8');
?>
<div class="admin-shell">
    <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Abrir menu">☰</button>
    <aside class="admin-sidebar" id="adminSidebar" aria-label="Navegação admin">
        <div class="sidebar-brand">Club61 · Admin</div>
        <nav class="admin-nav">
            <a href="<?= $hDash ?>" class="<?= $tab === 'dashboard' ? 'is-active' : '' ?>">📊 Dashboard</a>
            <a href="<?= $hConv ?>" class="<?= $tab === 'convites' ? 'is-active' : '' ?>">🎫 Convites</a>
            <a href="<?= $hUser ?>" class="<?= $tab === 'usuarios' ? 'is-active' : '' ?>">👥 Usuários</a>
            <a href="<?= $hPost ?>" class="<?= $tab === 'posts' ? 'is-active' : '' ?>">📝 Posts</a>
            <a href="<?= $hStory ?>" class="<?= $tab === 'stories' ? 'is-active' : '' ?>">📸 Stories</a>
        </nav>
        <a class="sidebar-feed" href="/features/feed/index.php">← Voltar ao feed</a>
    </aside>
    <div class="admin-main">
        <header class="main-topbar">
            <div>
                <span class="main-title">Painel administrativo</span>
                <span class="badge-admin-inline" style="margin-left:8px">ADMIN</span>
            </div>
        </header>

        <?php if ($flashStatus === 'ok' && $flashMessage !== ''): ?>
            <div class="admin-toast is-visible" role="status"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif ($flashStatus === 'error' && $flashMessage !== ''): ?>
            <div class="admin-toast is-visible error" role="alert"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($tab === 'dashboard'): ?>
        <section class="admin-section-dashboard" aria-label="Dashboard">
            <div class="stats-grid-dash">
                <div class="stat-card-dash">
                    <div class="stat-num-dash"><?= htmlspecialchars((string) $total_members, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="stat-label-dash">👥 Total usuários</div>
                </div>
                <div class="stat-card-dash">
                    <div class="stat-num-dash"><?= htmlspecialchars((string) $total_posts, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="stat-label-dash">📝 Total posts</div>
                </div>
                <div class="stat-card-dash">
                    <div class="stat-num-dash"><?= htmlspecialchars((string) $total_stories_active, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="stat-label-dash">📸 Stories ativos</div>
                </div>
                <div class="stat-card-dash">
                    <div class="stat-num-dash"><?= htmlspecialchars((string) $convitesDisp, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="stat-label-dash">🎫 Convites disponíveis</div>
                </div>
                <div class="stat-card-dash">
                    <div class="stat-num-dash"><?= htmlspecialchars((string) $convitesUsados, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="stat-label-dash">✅ Convites usados</div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($tab === 'convites'): ?>
        <section class="admin-section-invites" aria-label="Convites">
            <div class="panel-card" style="margin-bottom:18px">
                <h2 style="margin:0 0 10px;font-size:1rem;color:var(--gold)">Enviar convite (e-mail ou SMS)</h2>
                <p style="color:var(--muted);font-size:0.82rem;line-height:1.5;margin:0 0 14px">
                    Gera um código no <strong>Supabase</strong> (tabela <code>invites</code>) e envia por <strong>Resend</strong> (e-mail) ou <strong>Twilio</strong> (SMS).
                    Configure as chaves no <code>.env</code> do servidor (ver <code>config/invite_notify.php</code>).
                </p>
                <div class="invite-send-grid">
                    <div class="invite-send-card">
                        <h3 style="margin:0 0 10px;font-size:0.88rem;color:#ddd">E-mail</h3>
                        <form method="post" action="" style="display:flex;flex-direction:column;gap:10px">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="return_tab" value="convites">
                            <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                            <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                            <input type="hidden" name="st_page" value="<?= (int) $stPage ?>">
                            <input type="hidden" name="action" value="enviar_convite_email">
                            <label for="email_destino" class="stat-label-dash" style="display:block;font-size:0.72rem;color:var(--muted)">Destinatário</label>
                            <input type="email" class="dark-input" id="email_destino" name="email_destino" required placeholder="nome@exemplo.com" autocomplete="email">
                            <button type="submit" class="btn-gold" style="align-self:flex-start">📧 Gerar e enviar por e-mail</button>
                        </form>
                    </div>
                    <div class="invite-send-card">
                        <h3 style="margin:0 0 10px;font-size:0.88rem;color:#ddd">SMS (Brasil)</h3>
                        <form method="post" action="" style="display:flex;flex-direction:column;gap:10px">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="return_tab" value="convites">
                            <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                            <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                            <input type="hidden" name="st_page" value="<?= (int) $stPage ?>">
                            <input type="hidden" name="action" value="enviar_convite_sms">
                            <label for="telefone_destino" class="stat-label-dash" style="display:block;font-size:0.72rem;color:var(--muted)">Telefone (DDD + número)</label>
                            <input type="tel" class="dark-input" id="telefone_destino" name="telefone_destino" required placeholder="11999998888" inputmode="numeric" autocomplete="tel">
                            <button type="submit" class="btn-gold" style="align-self:flex-start">📱 Gerar e enviar por SMS</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="panel-card">
                <div class="panel-toolbar">
                    <h2>Convites</h2>
                    <form method="post" action="" style="margin:0">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="return_tab" value="convites">
                        <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                        <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                        <input type="hidden" name="st_page" value="<?= (int) $stPage ?>">
                        <input type="hidden" name="action" value="gerar_convite">
                        <button type="submit" class="btn-gold">＋ Gerar novo convite</button>
                    </form>
                </div>
                <div style="overflow-x:auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Criado em</th>
                                <th>Expira em</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($invites as $inv): ?>
                            <?php
                            $ic = isset($inv['code']) ? (string) $inv['code'] : '';
                            $iid = isset($inv['id']) ? (string) $inv['id'] : '';
                            $st = isset($inv['status']) ? (string) $inv['status'] : '';
                            $usedBy = $inv['used_by'] ?? null;
                            $expRaw = isset($inv['expires_at']) ? (string) $inv['expires_at'] : '';
                            $crRaw = isset($inv['created_at']) ? (string) $inv['created_at'] : '';
                            $expTs = $expRaw !== '' ? strtotime($expRaw) : false;
                            $isExpired = ($expTs !== false && $expTs < time());
                            $isUsed = ($usedBy !== null && $usedBy !== '');
                            $derivedAvail = !$isUsed && !$isExpired && $st !== 'revoked';
                            $usedByDisp = '';
                            if ($isUsed && is_string($usedBy) && $usedBy !== '') {
                                $usedByDisp = $displayIdByUserId[$usedBy] ?? '—';
                            }
                            ?>
                            <tr>
                                <td><span class="mono-code"><?= htmlspecialchars($ic, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td style="color:var(--muted)"><?= htmlspecialchars(admin_format_iso($crRaw !== '' ? $crRaw : null), ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="color:var(--muted)"><?= htmlspecialchars(admin_format_iso($expRaw !== '' ? $expRaw : null), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if ($isUsed): ?>
                                        <span class="badge-pill muted">Usado por <?= htmlspecialchars($usedByDisp, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php elseif ($isExpired): ?>
                                        <span class="badge-pill muted">Expirado</span>
                                    <?php elseif ($st === 'revoked'): ?>
                                        <span class="badge-pill muted">Revogado</span>
                                    <?php else: ?>
                                        <span class="badge-pill ok">Disponível</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn-sm js-copy-code" data-code="<?= htmlspecialchars($ic, ENT_QUOTES, 'UTF-8') ?>">📋 Copiar</button>
                                    <?php if ($iid !== '' && $derivedAvail): ?>
                                        <form method="post" action="" style="display:inline;margin-left:6px" onsubmit="return confirm('Excluir este convite?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="return_tab" value="convites">
                                            <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                                            <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                                            <input type="hidden" name="st_page" value="<?= (int) $stPage ?>">
                                            <input type="hidden" name="action" value="excluir_convite">
                                            <input type="hidden" name="invite_id" value="<?= htmlspecialchars($iid, ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="btn-sm btn-danger" title="Excluir">🗑 Excluir</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($tab === 'usuarios'): ?>
        <section aria-label="Usuários">
            <div class="panel-card">
                <div style="margin-bottom:14px">
                    <label for="userSearch" class="stat-label-dash" style="display:block;margin-bottom:6px;color:var(--muted);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em">Buscar</label>
                    <input type="search" id="userSearch" class="dark-input" placeholder="display_id ou cidade…" autocomplete="off">
                </div>
                <div style="overflow-x:auto">
                    <table class="data-table" id="usuariosTable">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Cidade</th>
                                <th>Tipo</th>
                                <th>Cadastro</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($members as $mem): ?>
                            <?php
                            $mid = isset($mem['id']) ? (string) $mem['id'] : '';
                            $lbl = clLabel($mem);
                            $isAdminRow = club61_profile_row_is_admin($mem);
                            $cidade = isset($mem['cidade']) ? trim((string) $mem['cidade']) : '';
                            $isSelf = ($mid !== '' && $mid === $current_user_id);
                            $cadRow = isset($mem['created_at']) ? admin_format_iso((string) $mem['created_at']) : '—';
                            $searchBlob = strtolower($lbl . ' ' . $cidade);
                            ?>
                            <tr data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
                                <td><span class="mono-code" style="font-size:0.95rem"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td style="color:var(--muted)"><?= $cidade !== '' ? htmlspecialchars($cidade, ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                <td>
                                    <?php if ($isAdminRow): ?>
                                        <span class="badge-pill admin">Admin</span>
                                    <?php else: ?>
                                        <span class="badge-pill membro">Membro</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:var(--muted);white-space:nowrap"><?= htmlspecialchars($cadRow, ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if ($isSelf && $isAdminRow): ?>
                                        <button type="button" class="btn-sm btn-danger" disabled title="Não é possível alterar o próprio papel">Remover Admin</button>
                                    <?php elseif ($isSelf): ?>
                                        <span style="color:var(--muted);font-size:0.8rem">—</span>
                                    <?php elseif (!$isAdminRow): ?>
                                        <form method="post" action="" style="display:inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="return_tab" value="usuarios">
                                            <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                                            <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                                            <input type="hidden" name="st_page" value="<?= (int) $stPage ?>">
                                            <input type="hidden" name="action" value="alterar_role">
                                            <input type="hidden" name="target_user_id" value="<?= htmlspecialchars($mid, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="nova_role" value="admin">
                                            <button type="submit" class="btn-sm btn-purple">Tornar Admin</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="" style="display:inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="return_tab" value="usuarios">
                                            <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                                            <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                                            <input type="hidden" name="st_page" value="<?= (int) $stPage ?>">
                                            <input type="hidden" name="action" value="alterar_role">
                                            <input type="hidden" name="target_user_id" value="<?= htmlspecialchars($mid, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="nova_role" value="membro">
                                            <button type="submit" class="btn-sm btn-danger">Remover Admin</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!$isSelf): ?>
                                        <button type="button" class="btn-sm btn-excluir-dark js-open-delete-user"
                                            data-user-id="<?= htmlspecialchars($mid, ENT_QUOTES, 'UTF-8') ?>"
                                            data-display-id="<?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?>"
                                            style="margin-left:6px">Excluir</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($totalMembersPages > 1) {
                $prevM = max(1, $mPage - 1);
                $nextM = min($totalMembersPages, $mPage + 1);
                ?>
            <div class="panel-card" style="margin-top:12px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;font-size:0.85rem;color:var(--muted)">
                <span>Usuários · Página <?= htmlspecialchars((string) $mPage, ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars((string) $totalMembersPages, ENT_QUOTES, 'UTF-8') ?></span>
                <span>
                    <?php if ($mPage > 1): ?>
                        <a class="btn-sm" style="text-decoration:none;display:inline-block" href="<?= htmlspecialchars(admin_page_url('usuarios', $prevM, $pPage, $stPage), ENT_QUOTES, 'UTF-8') ?>">← Anterior</a>
                    <?php endif; ?>
                    <?php if ($mPage < $totalMembersPages): ?>
                        <a class="btn-sm" style="text-decoration:none;display:inline-block;margin-left:8px" href="<?= htmlspecialchars(admin_page_url('usuarios', $nextM, $pPage, $stPage), ENT_QUOTES, 'UTF-8') ?>">Próxima →</a>
                    <?php endif; ?>
                </span>
            </div>
            <?php } ?>
        </section>
        <?php endif; ?>

        <?php if ($tab === 'posts'): ?>
        <section aria-label="Posts">
            <div class="panel-card">
                <h2 style="font-size:1rem;margin-bottom:14px;font-weight:600">Posts recentes</h2>
                <?php if ($posts === []): ?>
                    <p style="color:var(--muted);font-size:0.9rem">Nenhum post encontrado.</p>
                <?php endif; ?>
                <div class="posts-cards">
                    <?php foreach ($posts as $post): ?>
                        <?php
                        $pid = isset($post['id']) ? (string) $post['id'] : '';
                        $uid = isset($post['user_id']) ? (string) $post['user_id'] : '';
                        $author = $uid !== '' && isset($memberMap[$uid]) ? $memberMap[$uid] : [];
                        $authLbl = $author !== [] ? clLabel($author) : 'Membro';
                        $capShow = htmlspecialchars(admin_truncate_caption($post), ENT_QUOTES, 'UTF-8');
                        $dt = admin_format_dt($post);
                        ?>
                        <article class="post-admin-card">
                            <div class="post-admin-meta">
                                <span class="post-admin-author"><?= htmlspecialchars($authLbl, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="post-admin-date"><?= $dt ?></span>
                            </div>
                            <p class="post-admin-snippet"><?= $capShow ?></p>
                            <?php if ($pid !== ''): ?>
                                <form method="post" action="" style="margin-top:4px" onsubmit="return confirm('Remover este post?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="return_tab" value="posts">
                                    <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                                    <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                                    <input type="hidden" name="st_page" value="<?= (int) $stPage ?>">
                                    <input type="hidden" name="action" value="excluir_post">
                                    <input type="hidden" name="post_id" value="<?= htmlspecialchars($pid, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn-sm btn-danger">🗑 Excluir</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($totalPostsPages > 1) {
                $prevP = max(1, $pPage - 1);
                $nextP = min($totalPostsPages, $pPage + 1);
                ?>
            <div class="panel-card" style="margin-top:12px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;font-size:0.85rem;color:var(--muted)">
                <span>Posts · Página <?= htmlspecialchars((string) $pPage, ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars((string) $totalPostsPages, ENT_QUOTES, 'UTF-8') ?></span>
                <span>
                    <?php if ($pPage > 1): ?>
                        <a class="btn-sm" style="text-decoration:none;display:inline-block" href="<?= htmlspecialchars(admin_page_url('posts', $mPage, $prevP, $stPage), ENT_QUOTES, 'UTF-8') ?>">← Anterior</a>
                    <?php endif; ?>
                    <?php if ($pPage < $totalPostsPages): ?>
                        <a class="btn-sm" style="text-decoration:none;display:inline-block;margin-left:8px" href="<?= htmlspecialchars(admin_page_url('posts', $mPage, $nextP, $stPage), ENT_QUOTES, 'UTF-8') ?>">Próxima →</a>
                    <?php endif; ?>
                </span>
            </div>
            <?php } ?>
        </section>
        <?php endif; ?>

        <?php if ($tab === 'stories'): ?>
        <section aria-label="Stories">
            <?php if ($stories === []): ?>
                <p style="color:var(--muted);font-size:0.9rem">Nenhum story ativo no momento.</p>
            <?php endif; ?>
            <div class="stories-grid-adm">
                <?php foreach ($stories as $story): ?>
                    <?php
                    $sid = isset($story['id']) ? (string) $story['id'] : '';
                    $suid = isset($story['user_id']) ? (string) $story['user_id'] : '';
                    $sauthor = $suid !== '' && isset($memberMap[$suid]) ? $memberMap[$suid] : [];
                    $sauthLbl = $sauthor !== [] ? clLabel($sauthor) : 'Membro';
                    $simg = isset($story['image_url']) ? trim((string) $story['image_url']) : '';
                    $sdt = admin_format_dt($story);
                    ?>
                    <div class="story-cell">
                        <?php if ($simg !== ''): ?>
                            <img class="story-cell-img" src="<?= htmlspecialchars($simg, ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <?php else: ?>
                            <div class="story-cell-img" style="display:flex;align-items:center;justify-content:center;font-size:2rem">📸</div>
                        <?php endif; ?>
                        <div class="story-cell-body">
                            <div class="story-cell-row">
                                <span style="font-weight:600;color:var(--gold)"><?= htmlspecialchars($sauthLbl, ENT_QUOTES, 'UTF-8') ?></span>
                                <span style="color:var(--muted);font-size:0.78rem"><?= $sdt ?></span>
                            </div>
                            <?php if ($sid !== ''): ?>
                                <form method="post" action="" onsubmit="return confirm('Remover este story?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="return_tab" value="stories">
                                    <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
                                    <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
                                    <input type="hidden" name="st_page" value="<?= (int) $stPage ?>">
                                    <input type="hidden" name="action" value="excluir_story">
                                    <input type="hidden" name="story_id" value="<?= htmlspecialchars($sid, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn-sm btn-danger" style="width:100%">🗑 Excluir</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($totalStoriesPages > 1) {
                $prevSt = max(1, $stPage - 1);
                $nextSt = min($totalStoriesPages, $stPage + 1);
                ?>
            <div class="panel-card" style="margin-top:14px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;font-size:0.85rem;color:var(--muted)">
                <span>Stories · Página <?= htmlspecialchars((string) $stPage, ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars((string) $totalStoriesPages, ENT_QUOTES, 'UTF-8') ?></span>
                <span>
                    <?php if ($stPage > 1): ?>
                        <a class="btn-sm" style="text-decoration:none;display:inline-block" href="<?= htmlspecialchars(admin_page_url('stories', $mPage, $pPage, $prevSt), ENT_QUOTES, 'UTF-8') ?>">← Anterior</a>
                    <?php endif; ?>
                    <?php if ($stPage < $totalStoriesPages): ?>
                        <a class="btn-sm" style="text-decoration:none;display:inline-block;margin-left:8px" href="<?= htmlspecialchars(admin_page_url('stories', $mPage, $pPage, $nextSt), ENT_QUOTES, 'UTF-8') ?>">Próxima →</a>
                    <?php endif; ?>
                </span>
            </div>
            <?php } ?>
        </section>
        <?php endif; ?>

    </div>
</div>

<div id="modal-excluir-usuario" class="admin-modal" aria-hidden="true" role="dialog" aria-labelledby="modal-del-title">
    <div class="admin-modal-overlay js-close-modal" tabindex="-1"></div>
    <div class="admin-modal-card">
        <p id="modal-del-msg"></p>
        <form id="form-excluir-usuario" method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="return_tab" value="usuarios">
            <input type="hidden" name="m_page" value="<?= (int) $mPage ?>">
            <input type="hidden" name="p_page" value="<?= (int) $pPage ?>">
            <input type="hidden" name="st_page" value="<?= (int) $stPage ?>">
            <input type="hidden" name="action" value="excluir_usuario">
            <input type="hidden" name="target_user_id" id="modal_target_user_id" value="">
            <div class="admin-modal-actions">
                <button type="button" class="btn-modal-cancel js-close-modal">Cancelar</button>
                <button type="submit" class="btn-modal-ok">Confirmar exclusão</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var toggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('adminSidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-open');
        });
        sidebar.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                document.body.classList.remove('sidebar-open');
            });
        });
    }

    var userSearch = document.getElementById('userSearch');
    if (userSearch) {
        userSearch.addEventListener('input', function () {
            var q = (this.value || '').toLowerCase().trim();
            document.querySelectorAll('#usuariosTable tbody tr').forEach(function (tr) {
                var hay = (tr.getAttribute('data-search') || '').toLowerCase();
                tr.style.display = (q === '' || hay.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }

    document.querySelectorAll('.js-copy-code').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var code = btn.getAttribute('data-code') || '';
            if (!code) return;
            function ok() { btn.textContent = '✓ Copiado'; setTimeout(function () { btn.textContent = '📋 Copiar'; }, 1400); }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(ok).catch(function () {
                    window.prompt('Copie o código:', code);
                });
            } else {
                window.prompt('Copie o código:', code);
            }
        });
    });

    var modal = document.getElementById('modal-excluir-usuario');
    var modalMsg = document.getElementById('modal-del-msg');
    var modalUid = document.getElementById('modal_target_user_id');
    function closeModal() {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }
    if (modal) {
        modal.querySelectorAll('.js-close-modal').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
    }
    document.querySelectorAll('.js-open-delete-user').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var did = btn.getAttribute('data-display-id') || '';
            var uid = btn.getAttribute('data-user-id') || '';
            if (!modal || !modalMsg || !modalUid) return;
            modalMsg.textContent = 'Tem certeza que deseja excluir o usuário ' + did + '? Esta ação não pode ser desfeita.';
            modalUid.value = uid;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        });
    });
})();
</script>
</body>
</html>
