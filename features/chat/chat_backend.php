<?php

declare(strict_types=1);

/**
 * Lógica compartilhada do chat geral (Supabase REST, service role).
 */

require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';

/** @var list<string> */
const CLUB61_CHAT_ALLOWED_REACTION_EMOJIS = ['❤️', '😂', '😮', '👏', '🔥'];

function club61_chat_service_headers(bool $jsonBody = false): array
{
    if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
        return [];
    }
    $sk = SUPABASE_SERVICE_KEY;
    $h = [
        'apikey: ' . $sk,
        'Authorization: Bearer ' . $sk,
        'Accept: application/json',
    ];
    if ($jsonBody) {
        $h[] = 'Content-Type: application/json';
    }

    return $h;
}

function club61_chat_member_line(array $row): string
{
    $cl = club61_display_id_label(isset($row['display_id']) ? (string) $row['display_id'] : null);
    $bio = trim((string) ($row['bio'] ?? ''));
    $cidade = trim((string) ($row['cidade'] ?? ''));
    $nick = '';
    if ($bio !== '') {
        $parts = preg_split('/\r\n|\r|\n/', $bio, 2);
        $line = trim((string) ($parts[0] ?? ''));
        if ($line !== '') {
            if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                $nick = mb_strlen($line) > 32 ? mb_substr($line, 0, 29) . '…' : $line;
            } else {
                $nick = strlen($line) > 32 ? substr($line, 0, 29) . '…' : $line;
            }
        }
    }
    if ($nick === '') {
        $nick = $cidade !== '' ? $cidade : 'Membro';
    }

    return $cl . ' — ' . $nick;
}

/**
 * @param list<string> $ids
 * @return array<string, array<string, mixed>>
 */
function club61_chat_fetch_profiles_by_ids(array $ids): array
{
    $ids = array_values(array_unique(array_filter($ids, static fn ($id) => $id !== '')));
    if ($ids === [] || !defined('SUPABASE_URL')) {
        return [];
    }
    $inList = implode(',', $ids);
    $sel = rawurlencode('id,display_id,avatar_url,last_seen,cidade,bio');
    $url = SUPABASE_URL . '/rest/v1/profiles?select=' . $sel . '&id=in.(' . $inList . ')';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => club61_chat_service_headers(false),
        CURLOPT_HTTPGET => true,
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $row) {
        if (isset($row['id'])) {
            $out[(string) $row['id']] = $row;
        }
    }

    return $out;
}

/**
 * @param list<string> $messageIds
 * @return array<string, array<string, array{count:int, users:list<string>}>>
 */
function club61_chat_reactions_grouped(array $messageIds): array
{
    if ($messageIds === [] || !defined('SUPABASE_URL')) {
        return [];
    }
    $inList = implode(',', $messageIds);
    $url = SUPABASE_URL . '/rest/v1/chat_reactions?message_id=in.(' . $inList . ')'
        . '&select=message_id,user_id,emoji';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => club61_chat_service_headers(false),
        CURLOPT_HTTPGET => true,
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return [];
    }
    $rows = json_decode($raw, true);
    if (!is_array($rows)) {
        return [];
    }
    $userIds = [];
    foreach ($rows as $r) {
        $uid = isset($r['user_id']) ? (string) $r['user_id'] : '';
        if ($uid !== '') {
            $userIds[$uid] = true;
        }
    }
    $profiles = club61_chat_fetch_profiles_by_ids(array_keys($userIds));
    $out = [];
    foreach ($rows as $r) {
        $mid = isset($r['message_id']) ? (string) $r['message_id'] : '';
        $emoji = isset($r['emoji']) ? (string) $r['emoji'] : '';
        $uid = isset($r['user_id']) ? (string) $r['user_id'] : '';
        if ($mid === '' || $emoji === '' || $uid === '') {
            continue;
        }
        if (!isset($out[$mid])) {
            $out[$mid] = [];
        }
        if (!isset($out[$mid][$emoji])) {
            $out[$mid][$emoji] = ['count' => 0, 'users' => []];
        }
        $out[$mid][$emoji]['count']++;
        $line = isset($profiles[$uid]) ? club61_chat_member_line($profiles[$uid]) : 'Membro';
        $out[$mid][$emoji]['users'][] = $line;
    }

    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function club61_chat_fetch_messages_for_sala(string $salaId, ?string $afterIso, int $limit): array
{
    if ($salaId === '' || !defined('SUPABASE_URL')) {
        return [];
    }
    $sel = rawurlencode('id,user_id,conteudo,tipo,media_url,created_at,sala_id');
    $base = SUPABASE_URL . '/rest/v1/chat_messages?select=' . $sel
        . '&sala_id=eq.' . rawurlencode($salaId);
    if ($afterIso !== null && $afterIso !== '') {
        $base .= '&created_at=gt.' . rawurlencode($afterIso);
        $base .= '&order=created_at.asc&limit=' . (int) $limit;
    } else {
        $base .= '&order=created_at.desc&limit=' . (int) $limit;
    }
    $ch = curl_init($base);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => club61_chat_service_headers(false),
        CURLOPT_HTTPGET => true,
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    if ($afterIso === null || $afterIso === '') {
        $decoded = array_reverse($decoded);
    }

    return $decoded;
}

/**
 * Monta mensagens com autor e reações para API/SSR.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function club61_chat_enrich_messages(array $rows): array
{
    $ids = [];
    foreach ($rows as $m) {
        $uid = isset($m['user_id']) ? (string) $m['user_id'] : '';
        if ($uid !== '') {
            $ids[] = $uid;
        }
    }
    $profiles = club61_chat_fetch_profiles_by_ids($ids);
    $mids = [];
    foreach ($rows as $m) {
        if (isset($m['id'])) {
            $mids[] = (string) $m['id'];
        }
    }
    $reactions = club61_chat_reactions_grouped($mids);
    $out = [];
    foreach ($rows as $m) {
        $uid = isset($m['user_id']) ? (string) $m['user_id'] : '';
        $mid = isset($m['id']) ? (string) $m['id'] : '';
        $prof = $profiles[$uid] ?? [];
        $author = [
            'id' => $uid,
            'avatar_url' => isset($prof['avatar_url']) ? trim((string) $prof['avatar_url']) : '',
            'member_line' => $prof !== [] ? club61_chat_member_line($prof) : club61_display_id_label(null) . ' — Membro',
            'label' => $prof !== [] ? club61_display_id_label(isset($prof['display_id']) ? (string) $prof['display_id'] : null) : 'Membro',
        ];
        $out[] = array_merge($m, [
            'author' => $author,
            'reactions' => $reactions[$mid] ?? [],
        ]);
    }

    return $out;
}

function club61_chat_mime_to_tipo(string $mime): string
{
    if (str_starts_with($mime, 'image/')) {
        return 'imagem';
    }
    if (str_starts_with($mime, 'video/')) {
        return 'video';
    }

    return 'texto';
}

/**
 * Upload binário para bucket chat-media; retorna URL pública ou null.
 */
function club61_chat_upload_media(string $binary, string $mime, string $filename): ?string
{
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
        return null;
    }
    $upUrl = SUPABASE_URL . '/storage/v1/object/chat-media/' . $filename;
    $chUp = curl_init($upUrl);
    curl_setopt_array($chUp, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $binary,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: ' . $mime,
            'x-upsert: true',
        ],
    ]);
    curl_exec($chUp);
    $upCode = curl_getinfo($chUp, CURLINFO_HTTP_CODE);
    curl_close($chUp);
    if ($upCode < 200 || $upCode >= 300) {
        return null;
    }

    return SUPABASE_URL . '/storage/v1/object/public/chat-media/' . $filename;
}

/**
 * @return array{ok:bool, error?:string, id?:string}
 */
function club61_chat_insert_message(
    string $salaId,
    string $userId,
    string $conteudo,
    string $tipo,
    ?string $mediaUrl
): array {
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
        return ['ok' => false, 'error' => 'supabase'];
    }
    $payload = [
        'sala_id' => $salaId,
        'user_id' => $userId,
        'conteudo' => $conteudo === '' ? null : $conteudo,
        'tipo' => $tipo,
        'media_url' => $mediaUrl,
    ];
    $ch = curl_init(SUPABASE_URL . '/rest/v1/chat_messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge(club61_chat_service_headers(true), [
            'Prefer: return=representation',
        ]),
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return ['ok' => false, 'error' => 'insert'];
    }
    $dec = json_decode($raw, true);
    if (is_array($dec) && isset($dec[0]['id'])) {
        return ['ok' => true, 'id' => (string) $dec[0]['id']];
    }
    if (is_array($dec) && isset($dec['id'])) {
        return ['ok' => true, 'id' => (string) $dec['id']];
    }

    return ['ok' => true];
}

/**
 * @return array{ok:bool, toggled?:string, error?:string}
 */
function club61_chat_toggle_reaction(string $messageId, string $userId, string $emoji): array
{
    if (!in_array($emoji, CLUB61_CHAT_ALLOWED_REACTION_EMOJIS, true)) {
        return ['ok' => false, 'error' => 'emoji'];
    }
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
        return ['ok' => false, 'error' => 'supabase'];
    }
    $checkUrl = SUPABASE_URL . '/rest/v1/chat_reactions?message_id=eq.' . rawurlencode($messageId)
        . '&user_id=eq.' . rawurlencode($userId) . '&emoji=eq.' . rawurlencode($emoji) . '&select=id';
    $ch = curl_init($checkUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => club61_chat_service_headers(false),
        CURLOPT_HTTPGET => true,
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $exists = false;
    if ($raw !== false && $code >= 200 && $code < 300) {
        $rows = json_decode($raw, true);
        $exists = is_array($rows) && $rows !== [];
    }
    if ($exists) {
        $delUrl = SUPABASE_URL . '/rest/v1/chat_reactions?message_id=eq.' . rawurlencode($messageId)
            . '&user_id=eq.' . rawurlencode($userId) . '&emoji=eq.' . rawurlencode($emoji);
        $chD = curl_init($delUrl);
        curl_setopt_array($chD, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => club61_chat_service_headers(false),
        ]);
        curl_exec($chD);
        $cD = curl_getinfo($chD, CURLINFO_HTTP_CODE);
        curl_close($chD);
        if ($cD < 200 || $cD >= 300) {
            return ['ok' => false, 'error' => 'delete'];
        }

        return ['ok' => true, 'toggled' => 'removed'];
    }
    $ins = [
        'message_id' => $messageId,
        'user_id' => $userId,
        'emoji' => $emoji,
    ];
    $chI = curl_init(SUPABASE_URL . '/rest/v1/chat_reactions');
    curl_setopt_array($chI, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($ins, JSON_UNESCAPED_SLASHES),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge(club61_chat_service_headers(true), ['Prefer: return=minimal']),
    ]);
    curl_exec($chI);
    $cI = curl_getinfo($chI, CURLINFO_HTTP_CODE);
    curl_close($chI);
    if ($cI < 200 || $cI >= 300) {
        return ['ok' => false, 'error' => 'insert'];
    }

    return ['ok' => true, 'toggled' => 'added'];
}

/**
 * @return list<array<string, mixed>>
 */
function club61_chat_online_in_sala(string $salaId, int $maxAgeSeconds = 120): array
{
    if ($salaId === '' || !defined('SUPABASE_URL')) {
        return [];
    }
    $since = (new DateTimeImmutable('now'))->sub(new DateInterval('PT' . max(1, $maxAgeSeconds) . 'S'))->format('Y-m-d\TH:i:s.v\Z');
    $sel = rawurlencode('user_id,sala_id,last_seen');
    $url = SUPABASE_URL . '/rest/v1/chat_presence?select=' . $sel
        . '&sala_id=eq.' . rawurlencode($salaId)
        . '&last_seen=gte.' . rawurlencode($since)
        . '&order=last_seen.desc';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => club61_chat_service_headers(false),
        CURLOPT_HTTPGET => true,
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return [];
    }
    $rows = json_decode($raw, true);
    if (!is_array($rows)) {
        return [];
    }
    $uids = [];
    foreach ($rows as $r) {
        $u = isset($r['user_id']) ? (string) $r['user_id'] : '';
        if ($u !== '') {
            $uids[] = $u;
        }
    }
    $profiles = club61_chat_fetch_profiles_by_ids($uids);
    $out = [];
    foreach ($rows as $r) {
        $u = isset($r['user_id']) ? (string) $r['user_id'] : '';
        if ($u === '') {
            continue;
        }
        $p = $profiles[$u] ?? [];
        $out[] = [
            'user_id' => $u,
            'last_seen' => $r['last_seen'] ?? null,
            'member_line' => $p !== [] ? club61_chat_member_line($p) : club61_display_id_label(null) . ' — Membro',
            'label' => $p !== [] ? club61_display_id_label(isset($p['display_id']) ? (string) $p['display_id'] : null) : 'Membro',
            'avatar_url' => isset($p['avatar_url']) ? trim((string) $p['avatar_url']) : '',
        ];
    }
    usort($out, static function (array $a, array $b): int {
        return strcasecmp((string) ($a['member_line'] ?? ''), (string) ($b['member_line'] ?? ''));
    });

    return $out;
}

/**
 * @return array{ok:bool, error?:string}
 */
function club61_chat_upsert_presence(string $userId, string $salaId): array
{
    if (!defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
        return ['ok' => false, 'error' => 'supabase'];
    }
    $now = (new DateTimeImmutable('now'))->format('Y-m-d\TH:i:s.v\Z');
    $payload = [
        'user_id' => $userId,
        'sala_id' => $salaId,
        'last_seen' => $now,
    ];
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $ch = curl_init(SUPABASE_URL . '/rest/v1/chat_presence');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge(club61_chat_service_headers(true), ['Prefer: return=minimal']),
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) {
        return ['ok' => true];
    }
    if ($code !== 409) {
        return ['ok' => false, 'error' => 'presence'];
    }
    $patchBody = json_encode(['sala_id' => $salaId, 'last_seen' => $now], JSON_UNESCAPED_SLASHES);
    $patchUrl = SUPABASE_URL . '/rest/v1/chat_presence?user_id=eq.' . rawurlencode($userId);
    $chP = curl_init($patchUrl);
    curl_setopt_array($chP, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $patchBody,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge(club61_chat_service_headers(true), ['Prefer: return=minimal']),
    ]);
    curl_exec($chP);
    $codeP = curl_getinfo($chP, CURLINFO_HTTP_CODE);
    curl_close($chP);
    if ($codeP < 200 || $codeP >= 300) {
        return ['ok' => false, 'error' => 'presence'];
    }

    return ['ok' => true];
}
