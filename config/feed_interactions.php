<?php
/**
 * Feed: post_likes, post_comments — chamadas REST com service role (servidor).
 *
 * Requer: config/supabase.php com SUPABASE_SERVICE_KEY
 */

declare(strict_types=1);

require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/session.php';

function feed_sk_available(): bool
{
    return defined('SUPABASE_URL') && defined('SUPABASE_SERVICE_KEY') && SUPABASE_SERVICE_KEY !== '';
}

/**
 * @param list<mixed> $postIds
 * @return list<string>
 */
function feed_normalize_post_ids_for_in_clause(array $postIds): array
{
    $seen = [];
    foreach ($postIds as $x) {
        $s = trim((string) $x);
        if ($s !== '') {
            $seen[$s] = true;
        }
    }

    return array_keys($seen);
}

/** @var bool|null */
$GLOBALS['_club61_post_likes_probe'] = null;

/** Emoji padrão (compatível com toggle_like legado). */
function feed_default_like_emoji(): string
{
    return '❤️';
}

/** Emojis permitidos no picker / API (evita lixo na coluna). */
function feed_allowed_reaction_emojis(): array
{
    return ['❤️', '😂', '😮', '😢', '🔥', '👏'];
}

function feed_reaction_emoji_valid(?string $emoji): bool
{
    if ($emoji === null || $emoji === '') {
        return false;
    }

    return in_array($emoji, feed_allowed_reaction_emojis(), true);
}

function feed_post_likes_table_ready(): bool
{
    if ($GLOBALS['_club61_post_likes_probe'] !== null) {
        return (bool) $GLOBALS['_club61_post_likes_probe'];
    }
    $GLOBALS['_club61_post_likes_probe'] = false;
    if (!feed_sk_available()) {
        return false;
    }
    $url = SUPABASE_URL . '/rest/v1/post_likes?select=id&limit=1';
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
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $GLOBALS['_club61_post_likes_probe'] = ($code >= 200 && $code < 300);

    return (bool) $GLOBALS['_club61_post_likes_probe'];
}

/** @var bool|null */
$GLOBALS['_club61_post_comments_probe'] = null;

function feed_post_comments_table_ready(): bool
{
    if ($GLOBALS['_club61_post_comments_probe'] !== null) {
        return (bool) $GLOBALS['_club61_post_comments_probe'];
    }
    $GLOBALS['_club61_post_comments_probe'] = false;
    if (!feed_sk_available()) {
        return false;
    }
    $url = SUPABASE_URL . '/rest/v1/post_comments?select=id&limit=1';
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
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $GLOBALS['_club61_post_comments_probe'] = ($code >= 200 && $code < 300);

    return (bool) $GLOBALS['_club61_post_comments_probe'];
}

/** @var bool|null */
$GLOBALS['_club61_comments_probe'] = null;

function feed_comments_table_ready(): bool
{
    if ($GLOBALS['_club61_comments_probe'] !== null) {
        return (bool) $GLOBALS['_club61_comments_probe'];
    }
    $GLOBALS['_club61_comments_probe'] = false;
    if (!feed_sk_available()) {
        return false;
    }
    $url = SUPABASE_URL . '/rest/v1/comments?select=id&limit=1';
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
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $GLOBALS['_club61_comments_probe'] = ($code >= 200 && $code < 300);

    return (bool) $GLOBALS['_club61_comments_probe'];
}

/**
 * Contagem exata via PostgREST (Prefer: count=exact + Range: 0-0).
 */
function feed_count_rows_exact(string $table, string $filterEq): ?int
{
    if (!feed_sk_available()) {
        return null;
    }
    $url = SUPABASE_URL . '/rest/v1/' . $table . '?' . $filterEq . '&select=id';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Accept: application/json',
            'Prefer: count=exact',
            'Range: 0-0',
        ],
    ]);
    $resp = curl_exec($ch);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $hs = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $hs === 404) {
        return null;
    }
    if ($hs < 200 || $hs >= 300) {
        return null;
    }
    $headers = substr($resp, 0, $headerSize);
    if (preg_match('/content-range:\s*[^/]*\/(\d+)/i', $headers, $m)) {
        return (int) $m[1];
    }

    return 0;
}

/**
 * Curtidas de um post (contagem exata Supabase / PostgREST).
 */
function getLikesCount(string $postId): int
{
    if ($postId === '' || !feed_sk_available()) {
        return 0;
    }
    $enc = rawurlencode($postId);
    if (feed_post_likes_table_ready()) {
        $n = feed_count_rows_exact('post_likes', 'post_id=eq.' . $enc);

        return $n ?? 0;
    }
    $n = feed_count_rows_exact('likes', 'post_id=eq.' . $enc);

    return $n ?? 0;
}

/**
 * @return array{success:bool, status:?string, error:?string}
 */
function feed_toggle_post_likes_row(string $userId, string $postId): array
{
    $fail = ['success' => false, 'status' => null, 'error' => 'toggle_failed'];
    if ($userId === '' || $postId === '' || !feed_sk_available()) {
        $fail['error'] = 'config';

        return $fail;
    }
    $pidEq = rawurlencode($postId);
    $checkUrl = SUPABASE_URL . '/rest/v1/post_likes?post_id=eq.' . $pidEq . '&user_id=eq.' . urlencode($userId)
        . '&select=id';
    $ch = curl_init($checkUrl);
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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        $fail['error'] = 'post_likes_unavailable';

        return $fail;
    }
    $existing = json_decode((string) $raw, true);
    $liked = is_array($existing) && $existing !== [];

    if ($liked) {
        $delUrl = SUPABASE_URL . '/rest/v1/post_likes?post_id=eq.' . $pidEq . '&user_id=eq.' . urlencode($userId);
        $ch = curl_init($delUrl);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_SERVICE_KEY,
                'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
                'Prefer: return=minimal',
            ],
        ]);
        curl_exec($ch);
        $delCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($delCode < 200 || $delCode >= 300) {
            return $fail;
        }

        return ['success' => true, 'status' => 'unliked', 'error' => null];
    }

    $body = json_encode(['post_id' => $postId, 'user_id' => $userId], JSON_UNESCAPED_UNICODE);
    $ch = curl_init(SUPABASE_URL . '/rest/v1/post_likes');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ],
    ]);
    curl_exec($ch);
    $insCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($insCode < 200 || $insCode >= 300) {
        return $fail;
    }

    return ['success' => true, 'status' => 'liked', 'error' => null];
}

/**
 * Alterna reação (post_id, user_id, emoji): insere ou remove uma linha.
 *
 * @return array{success:bool, acao?:string, error?:string}
 */
function feed_reagir_toggle(string $userId, string $postId, string $emoji): array
{
    $fail = ['success' => false, 'error' => 'toggle_failed'];
    if ($userId === '' || $postId === '' || !feed_sk_available()) {
        $fail['error'] = 'config';

        return $fail;
    }
    if (!feed_reaction_emoji_valid($emoji)) {
        $fail['error'] = 'invalid_emoji';

        return $fail;
    }
    if (!feed_post_likes_table_ready()) {
        $fail['error'] = 'post_likes_unavailable';

        return $fail;
    }

    /** Uma linha por (user_id, post_id): mesmo comportamento que toggle_like (alternar). */
    $r = feed_toggle_post_likes_row($userId, $postId);
    if (!$r['success'] || $r['status'] === null) {
        $fail['error'] = $r['error'] ?? 'toggle_failed';

        return $fail;
    }

    return [
        'success' => true,
        'acao' => $r['status'] === 'liked' ? 'adicionado' : 'removido',
    ];
}

/**
 * Lista reações de um post para o JSON do feed (emoji + user_id).
 *
 * @return list<array{emoji:string,user_id:string}>
 */
function feed_fetch_post_reactions(string $postId): array
{
    if ($postId === '' || !feed_sk_available() || !feed_post_likes_table_ready()) {
        return [];
    }
    $url = SUPABASE_URL . '/rest/v1/post_likes?post_id=eq.' . rawurlencode($postId) . '&select=user_id';
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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return [];
    }
    $rows = json_decode((string) $raw, true);
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        if (!is_array($r) || !isset($r['user_id'])) {
            continue;
        }
        $out[] = [
            'emoji' => feed_default_like_emoji(),
            'user_id' => (string) $r['user_id'],
        ];
    }

    return $out;
}

/**
 * Toggle na tabela legado `likes` (JWT do utilizador).
 *
 * @return array{success:bool, status:?string, error:?string}
 */
function feed_toggle_legacy_likes_row(string $userId, string $accessToken, string $postId): array
{
    $fail = ['success' => false, 'status' => null, 'error' => 'toggle_failed'];
    if ($userId === '' || $accessToken === '' || $postId === '') {
        $fail['error'] = 'auth';

        return $fail;
    }
    $pidEq = rawurlencode($postId);
    $hdr = [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json',
    ];
    $checkUrl = SUPABASE_URL . '/rest/v1/likes?user_id=eq.' . urlencode($userId) . '&post_id=eq.' . $pidEq . '&select=id';
    $ch = curl_init($checkUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $hdr,
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        $fail['error'] = 'likes_unavailable';

        return $fail;
    }
    $existing = json_decode((string) $raw, true);
    $liked = is_array($existing) && $existing !== [];

    if ($liked) {
        $delUrl = SUPABASE_URL . '/rest/v1/likes?user_id=eq.' . urlencode($userId) . '&post_id=eq.' . $pidEq;
        $ch = curl_init($delUrl);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $hdr,
        ]);
        curl_exec($ch);
        $delCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($delCode < 200 || $delCode >= 300) {
            return $fail;
        }

        return ['success' => true, 'status' => 'unliked', 'error' => null];
    }

    $body = json_encode(['user_id' => $userId, 'post_id' => $postId], JSON_UNESCAPED_UNICODE);
    $ch = curl_init(SUPABASE_URL . '/rest/v1/likes');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge($hdr, [
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ]),
    ]);
    curl_exec($ch);
    $insCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($insCode < 200 || $insCode >= 300) {
        return $fail;
    }

    return ['success' => true, 'status' => 'liked', 'error' => null];
}

function feed_render_comment_line_html(
    ?string $commentId,
    string $displayLabel,
    string $commentText,
    string $currentUserId = '',
    string $postId = ''
): string {
    $hasId = $commentId !== null && $commentId !== '';
    $idAttr = $hasId
        ? ' data-comment-id="' . htmlspecialchars($commentId, ENT_QUOTES, 'UTF-8') . '"'
        : '';

    $delBtn = '';
    if ($currentUserId !== '' && $hasId) {
        $delBtn = '<button type="button" class="btn-del-comment"'
            . ' data-comment-id="' . htmlspecialchars($commentId, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-post-id="' . htmlspecialchars($postId, ENT_QUOTES, 'UTF-8') . '"'
            . ' aria-label="Excluir comentário" title="Excluir">×</button>';
    }

    return '<div class="comment-line"' . $idAttr . '><span class="comment-user">'
        . htmlspecialchars($displayLabel, ENT_QUOTES, 'UTF-8') . '</span>'
        . htmlspecialchars($commentText, ENT_QUOTES, 'UTF-8')
        . $delBtn
        . '</div>';
}

/**
 * @return array<string, int> post_id string => count
 */
function feed_get_likes_count_map(array $postIds): array
{
    if ($postIds === [] || !feed_sk_available()) {
        return [];
    }
    $ids = feed_normalize_post_ids_for_in_clause($postIds);
    if ($ids === []) {
        return [];
    }
    $in = implode(',', $ids);
    $table = feed_post_likes_table_ready() ? 'post_likes' : 'likes';
    $url = SUPABASE_URL . '/rest/v1/' . $table . '?select=post_id&post_id=in.(' . $in . ')';
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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return [];
    }
    $rows = json_decode($raw, true);
    if (!is_array($rows)) {
        return [];
    }
    $map = [];
    foreach ($rows as $r) {
        if (!isset($r['post_id'])) {
            continue;
        }
        $pid = trim((string) $r['post_id']);
        if ($pid === '') {
            continue;
        }
        $map[$pid] = ($map[$pid] ?? 0) + 1;
    }

    return $map;
}

/**
 * @param list<mixed> $postIds
 * @return array<string, true> liked post ids as string keys
 */
function feed_get_user_liked_post_ids(string $userId, array $postIds): array
{
    $out = [];
    if ($userId === '' || $postIds === [] || !feed_sk_available()) {
        return $out;
    }
    $ids = feed_normalize_post_ids_for_in_clause($postIds);
    if ($ids === []) {
        return $out;
    }
    $in = implode(',', $ids);
    $table = feed_post_likes_table_ready() ? 'post_likes' : 'likes';
    $url = SUPABASE_URL . '/rest/v1/' . $table . '?select=post_id&user_id=eq.' . urlencode($userId)
        . '&post_id=in.(' . $in . ')';
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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return $out;
    }
    $rows = json_decode($raw, true);
    if (!is_array($rows)) {
        return $out;
    }
    foreach ($rows as $r) {
        if (isset($r['post_id'])) {
            $pid = trim((string) $r['post_id']);
            if ($pid !== '') {
                $out[$pid] = true;
            }
        }
    }

    return $out;
}

/**
 * @return array<string, list<array<string,mixed>>> post_id => até $limit comentários (mais recentes primeiro)
 */
function feed_get_recent_comments_map(array $postIds, int $limitPerPost = 3): array
{
    $result = [];
    if ($postIds === [] || !feed_sk_available()) {
        return $result;
    }
    $ids = feed_normalize_post_ids_for_in_clause($postIds);
    if ($ids === []) {
        return $result;
    }
    foreach ($ids as $pid) {
        $result[$pid] = [];
    }
    $in = implode(',', $ids);
    $cap = max(1, min(200, count($ids) * max(1, $limitPerPost) * 2));
    $table = feed_post_comments_table_ready() ? 'post_comments' : (feed_comments_table_ready() ? 'comments' : '');
    if ($table === '') {
        return $result;
    }
    $textCol = $table === 'post_comments' ? 'comment_text' : 'text';
    $url = SUPABASE_URL . '/rest/v1/' . $table . '?select=id,post_id,user_id,' . $textCol . ',created_at'
        . '&post_id=in.(' . $in . ')'
        . '&order=created_at.desc&limit=' . $cap;
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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return $result;
    }
    $rows = json_decode($raw, true);
    if (!is_array($rows)) {
        return $result;
    }
    foreach ($rows as $r) {
        if (!isset($r['post_id'])) {
            continue;
        }
        $pid = trim((string) $r['post_id']);
        if ($pid === '' || !isset($result[$pid])) {
            continue;
        }
        if (count($result[$pid]) >= $limitPerPost) {
            continue;
        }
        if ($table === 'comments') {
            $r['comment_text'] = isset($r['text']) ? (string) $r['text'] : '';
        }
        $result[$pid][] = $r;
    }

    return $result;
}

/**
 * @return array<string, array<string,mixed>> user_id => profile row
 */
function feed_fetch_profiles_by_ids(array $userIds): array
{
    $out = [];
    if ($userIds === [] || !feed_sk_available()) {
        return $out;
    }
    $ids = array_unique(array_filter(array_map('strval', $userIds)));
    if ($ids === []) {
        return $out;
    }
    $in = implode(',', $ids);
    $url = SUPABASE_URL . '/rest/v1/profiles?select=id,display_id,avatar_url&id=in.(' . $in . ')';
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
    if (!is_array($rows)) {
        return $out;
    }
    foreach ($rows as $row) {
        if (isset($row['id'])) {
            $out[(string) $row['id']] = $row;
        }
    }

    return $out;
}

function feed_post_exists(string $postId): bool
{
    if ($postId === '') {
        return false;
    }
    $idEq = rawurlencode($postId);
    if (feed_sk_available()) {
        $url = SUPABASE_URL . '/rest/v1/posts?id=eq.' . $idEq . '&select=id';
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
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw !== false && $code >= 200 && $code < 300) {
            $rows = json_decode($raw, true);
            if (is_array($rows) && $rows !== []) {
                return true;
            }
        }
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $tok = isset($_SESSION['access_token']) ? (string) $_SESSION['access_token'] : '';
    if ($tok === '' || !defined('SUPABASE_ANON_KEY')) {
        return false;
    }
    $url = SUPABASE_URL . '/rest/v1/posts?id=eq.' . $idEq . '&select=id';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $tok,
            'Accept: application/json',
        ],
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return false;
    }
    $rows = json_decode($raw, true);

    return is_array($rows) && $rows !== [];
}

function feed_csrf_token(): string
{
    club61_session_start_safe();
    if (empty($_SESSION['feed_csrf']) || !is_string($_SESSION['feed_csrf'])) {
        $_SESSION['feed_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['feed_csrf'];
}

function feed_csrf_validate(?string $token): bool
{
    club61_session_start_safe();
    $s = $_SESSION['feed_csrf'] ?? '';

    return is_string($token) && $s !== '' && hash_equals($s, $token);
}

/** @deprecated use getLikesCount() */
function getPostLikesCount(string $postId): int
{
    return getLikesCount($postId);
}

function hasUserLiked(string $userId, string $postId): bool
{
    if ($userId === '') {
        return false;
    }
    $m = feed_get_user_liked_post_ids($userId, [$postId]);

    return isset($m[$postId]);
}

/**
 * @return list<array<string,mixed>>
 */
function getRecentComments(string $postId, int $limit = 3): array
{
    $m = feed_get_recent_comments_map([$postId], $limit);

    return $m[$postId] ?? [];
}

/**
 * Comentários de um post (mais antigos primeiro), para página "ver todos".
 *
 * @return list<array<string,mixed>>
 */
function feed_get_all_comments_for_post(string $postId, int $limit = 100): array
{
    if ($postId === '' || !feed_sk_available()) {
        return [];
    }
    $table = feed_post_comments_table_ready() ? 'post_comments' : (feed_comments_table_ready() ? 'comments' : '');
    if ($table === '') {
        return [];
    }
    $textCol = $table === 'post_comments' ? 'comment_text' : 'text';
    $url = SUPABASE_URL . '/rest/v1/' . $table . '?select=id,post_id,user_id,' . $textCol . ',created_at'
        . '&post_id=eq.' . rawurlencode($postId)
        . '&order=created_at.asc&limit=' . max(1, min(500, $limit));
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
    if (!is_array($rows)) {
        return [];
    }
    if ($table === 'comments') {
        foreach ($rows as &$rr) {
            if (is_array($rr)) {
                $rr['comment_text'] = isset($rr['text']) ? (string) $rr['text'] : '';
            }
        }
        unset($rr);
    }

    return $rows;
}

/**
 * Exclui um post: apenas se user_id corresponder. Remove arquivo do bucket `posts` quando possível.
 *
 * @return array{success:bool, message?:string}
 */
function feed_delete_owned_post(string $userId, string $postId): array
{
    if ($userId === '' || $postId === '') {
        return ['success' => false, 'message' => 'Pedido inválido.'];
    }
    if (!feed_sk_available() || !defined('SUPABASE_URL') || !defined('SUPABASE_SERVICE_KEY')) {
        return ['success' => false, 'message' => 'Serviço indisponível.'];
    }
    $idEq = rawurlencode($postId);

    $getUrl = SUPABASE_URL . '/rest/v1/posts?id=eq.' . $idEq . '&select=id,user_id,image_url';
    $ch = curl_init($getUrl);
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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return ['success' => false, 'message' => 'Não foi possível localizar o post.'];
    }
    $rows = json_decode((string) $raw, true);
    if (!is_array($rows) || $rows === []) {
        return ['success' => false, 'message' => 'Post não encontrado.'];
    }
    $row = $rows[0];
    $owner = isset($row['user_id']) ? (string) $row['user_id'] : '';
    if ($owner === '' || $owner !== $userId) {
        return ['success' => false, 'message' => 'Sem permissão para excluir este post.'];
    }

    $imageUrl = isset($row['image_url']) ? trim((string) $row['image_url']) : '';
    if ($imageUrl !== '' && preg_match('#/public/posts/([^?]+)#', $imageUrl, $m)) {
        $fname = rawurldecode($m[1]);
        $fname = str_replace(['/', '\\', "\0"], '', $fname);
        if ($fname !== '') {
            $delObj = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/posts/' . rawurlencode($fname);
            $chS = curl_init($delObj);
            curl_setopt_array($chS, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . SUPABASE_SERVICE_KEY,
                    'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
                ],
            ]);
            curl_exec($chS);
            curl_close($chS);
        }
    }

    $delUrl = SUPABASE_URL . '/rest/v1/posts?id=eq.' . $idEq;
    $chD = curl_init($delUrl);
    curl_setopt_array($chD, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Prefer: return=minimal',
        ],
    ]);
    curl_exec($chD);
    $codeD = curl_getinfo($chD, CURLINFO_HTTP_CODE);
    curl_close($chD);
    if ($codeD < 200 || $codeD >= 300) {
        return ['success' => false, 'message' => 'Falha ao excluir o post no banco.'];
    }

    return ['success' => true];
}
