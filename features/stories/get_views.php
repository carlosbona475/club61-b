<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';

$userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
$storyId = isset($_GET['story_id']) ? (string) $_GET['story_id'] : '';

if ($userId === '' || $storyId === '' || !defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    echo json_encode(['views' => []]);

    exit;
}

// Só o autor do story pode ver a lista
$chS = curl_init(SUPABASE_URL . '/rest/v1/stories?id=eq.' . rawurlencode($storyId) . '&select=user_id');
curl_setopt_array($chS, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Accept: application/json',
    ],
]);
$rawS = curl_exec($chS);
curl_close($chS);
$storyRows = json_decode((string) $rawS, true);
$owner = is_array($storyRows) && isset($storyRows[0]['user_id']) ? (string) $storyRows[0]['user_id'] : '';
if ($owner === '' || $owner !== $userId) {
    http_response_code(403);
    echo json_encode(['views' => [], 'error' => 'forbidden']);

    exit;
}

$url = SUPABASE_URL . '/rest/v1/story_views?story_id=eq.' . rawurlencode($storyId)
    . '&select=viewer_id,viewed_at&order=viewed_at.desc&limit=100';
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
$rows = json_decode((string) $raw, true) ?? [];

$uids = [];
foreach ($rows as $r) {
    if (is_array($r) && !empty($r['viewer_id'])) {
        $uids[] = (string) $r['viewer_id'];
    }
}
$uids = array_values(array_unique($uids));

$profiles = [];
if ($uids !== []) {
    $inList = implode(',', $uids);
    $ch2 = curl_init(SUPABASE_URL . '/rest/v1/profiles?id=in.(' . $inList . ')&select=id,display_id,avatar_url');
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Accept: application/json',
        ],
    ]);
    $rawP = curl_exec($ch2);
    curl_close($ch2);
    foreach (json_decode((string) $rawP, true) ?? [] as $p) {
        if (isset($p['id'])) {
            $profiles[(string) $p['id']] = $p;
        }
    }
}

$out = [];
foreach ($rows as $r) {
    if (!is_array($r) || empty($r['viewer_id'])) {
        continue;
    }
    $vid = (string) $r['viewer_id'];
    $pr = $profiles[$vid] ?? [];
    $out[] = [
        'viewer_id' => $vid,
        'viewed_at' => $r['viewed_at'] ?? null,
        'display_id' => isset($pr['display_id']) ? (string) $pr['display_id'] : '',
        'avatar_url' => isset($pr['avatar_url']) ? trim((string) $pr['avatar_url']) : '',
    ];
}

echo json_encode(['views' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;
