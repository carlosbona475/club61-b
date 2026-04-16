<?php

/**
 * GET/POST /mensagens/lista | conversa | enviar | lida
 * Também: ?r=lista|conversa|enviar|lida
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/csrf.php';
require_once CLUB61_ROOT . '/config/message_requests.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';

if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'service']);

    exit;
}

$uid = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
if ($uid === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth']);

    exit;
}

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
$action = '';
if (preg_match('#/mensagens/(lista|conversa|enviar|lida)/?$#', $path, $m)) {
    $action = $m[1];
}
if ($action === '') {
    $action = (string) ($_GET['r'] ?? '');
}

$h = [
    'apikey: ' . SUPABASE_SERVICE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
    'Accept: application/json',
];
$hj = array_merge($h, ['Content-Type: application/json']);

function club61_msg_fetch_profiles(array $ids): array
{
    $ids = array_values(array_unique(array_filter($ids)));
    if ($ids === []) {
        return [];
    }
    $in = implode(',', $ids);
    $sel = rawurlencode('id,display_id,avatar_url');
    $url = SUPABASE_URL . '/rest/v1/profiles?select=' . $sel . '&id=in.(' . $in . ')';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Accept: application/json',
        ],
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $rows = json_decode((string) $raw, true);
    $out = [];
    if (is_array($rows)) {
        foreach ($rows as $r) {
            if (isset($r['id'])) {
                $out[(string) $r['id']] = $r;
            }
        }
    }

    return $out;
}

if ($action === 'lista' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $orFilter = '(sender_id.eq.' . $uid . ',receiver_id.eq.' . $uid . ')';
    $url = SUPABASE_URL . '/rest/v1/direct_messages?or=' . rawurlencode($orFilter)
        . '&select=id,sender_id,receiver_id,content,created_at,read_at&order=created_at.desc&limit=800';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $h,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $rows = json_decode((string) $raw, true);
    if (!is_array($rows)) {
        $rows = [];
    }
    $threads = [];
    foreach ($rows as $row) {
        $sid = isset($row['sender_id']) ? (string) $row['sender_id'] : '';
        $rid = isset($row['receiver_id']) ? (string) $row['receiver_id'] : '';
        if ($sid === '' || $rid === '') {
            continue;
        }
        $other = $sid === $uid ? $rid : $sid;
        if (!isset($threads[$other])) {
            $threads[$other] = [
                'user_id' => $other,
                'last_message' => isset($row['content']) ? (string) $row['content'] : '',
                'last_at' => isset($row['created_at']) ? (string) $row['created_at'] : '',
                'unread' => 0,
            ];
        }
    }
    foreach ($rows as $row) {
        $sid = isset($row['sender_id']) ? (string) $row['sender_id'] : '';
        $rid = isset($row['receiver_id']) ? (string) $row['receiver_id'] : '';
        if ($sid === $uid || $rid !== $uid) {
            continue;
        }
        if (($row['read_at'] ?? null) !== null && $row['read_at'] !== '') {
            continue;
        }
        $other = $sid;
        if (isset($threads[$other])) {
            $threads[$other]['unread']++;
        }
    }
    $list = array_values($threads);
    usort($list, static function ($a, $b) {
        return strcmp((string) ($b['last_at'] ?? ''), (string) ($a['last_at'] ?? ''));
    });
    $pids = [];
    foreach ($list as $t) {
        $pids[] = (string) ($t['user_id'] ?? '');
    }
    $prof = club61_msg_fetch_profiles($pids);
    foreach ($list as &$t) {
        $oid = (string) ($t['user_id'] ?? '');
        $pr = $prof[$oid] ?? [];
        $t['label'] = $pr !== [] ? club61_display_id_label(isset($pr['display_id']) ? (string) $pr['display_id'] : null) : 'Membro';
        $t['avatar_url'] = isset($pr['avatar_url']) ? trim((string) $pr['avatar_url']) : '';
    }
    unset($t);
    echo json_encode(['ok' => true, 'conversas' => $list]);

    exit;
}

if ($action === 'conversa' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $com = trim((string) ($_GET['com'] ?? ''));
    if ($com === '' || $com === $uid) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'com']);

        exit;
    }
    $pairOr = '(and(sender_id.eq.' . $uid . ',receiver_id.eq.' . $com . '),and(sender_id.eq.' . $com . ',receiver_id.eq.' . $uid . '))';
    $url = SUPABASE_URL . '/rest/v1/direct_messages?or=' . rawurlencode($pairOr)
        . '&select=id,sender_id,receiver_id,content,created_at,read_at&order=created_at.asc&limit=500';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $h,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $rows = json_decode((string) $raw, true);
    if (!is_array($rows)) {
        $rows = [];
    }
    echo json_encode(['ok' => true, 'mensagens' => $rows]);

    exit;
}

if ($action === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = [];
    if (stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
        $d = json_decode(file_get_contents('php://input') ?: '[]', true);
        if (is_array($d)) {
            $input = $d;
        }
    }
    $receiver = trim((string) ($input['receiver_id'] ?? $_POST['receiver_id'] ?? ''));
    $conteudo = trim((string) ($input['conteudo'] ?? $_POST['conteudo'] ?? ''));
    $tok = (string) ($input['csrf'] ?? $_POST['csrf'] ?? '');
    if (!csrf_validate($tok)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'csrf']);

        exit;
    }
    if ($receiver === '' || $receiver === $uid || $conteudo === '' || strlen($conteudo) > 4000) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid']);

        exit;
    }
    if (!mr_can_open_dm($uid, $receiver)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'dm_not_allowed']);

        exit;
    }
    $payload = json_encode([
        'sender_id' => $uid,
        'receiver_id' => $receiver,
        'content' => $conteudo,
    ], JSON_UNESCAPED_SLASHES);
    $ch = curl_init(SUPABASE_URL . '/rest/v1/direct_messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge($hj, ['Prefer: return=representation']),
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'save']);

        exit;
    }
    $dec = json_decode((string) $raw, true);
    $row = is_array($dec) && isset($dec[0]) ? $dec[0] : (is_array($dec) && isset($dec['id']) ? $dec : null);
    echo json_encode(['ok' => true, 'mensagem' => $row]);

    exit;
}

if ($action === 'lida' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = [];
    if (stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
        $d = json_decode(file_get_contents('php://input') ?: '[]', true);
        if (is_array($d)) {
            $input = $d;
        }
    }
    $sender = trim((string) ($input['sender_id'] ?? $_POST['sender_id'] ?? ''));
    $tok = (string) ($input['csrf'] ?? $_POST['csrf'] ?? '');
    if (!csrf_validate($tok)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'csrf']);

        exit;
    }
    if ($sender === '' || $sender === $uid) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid']);

        exit;
    }
    $now = (new DateTimeImmutable('now'))->format('Y-m-d\TH:i:s.v\Z');
    $patchUrl = SUPABASE_URL . '/rest/v1/direct_messages?sender_id=eq.' . urlencode($sender)
        . '&receiver_id=eq.' . urlencode($uid) . '&read_at=is.null';
    $patchBody = json_encode(['read_at' => $now], JSON_UNESCAPED_SLASHES);
    $ch = curl_init($patchUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $patchBody,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge($hj, ['Prefer: return=minimal']),
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'patch']);

        exit;
    }
    echo json_encode(['ok' => true]);

    exit;
}

http_response_code(404);
echo json_encode(['ok' => false, 'error' => 'not_found']);
