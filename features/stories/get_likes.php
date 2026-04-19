<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';

$userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
$storyId = isset($_GET['story_id']) ? trim((string) $_GET['story_id']) : '';

if ($userId === '' || $storyId === '' || !defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    echo json_encode(['count' => 0, 'liked' => false]);
    exit;
}

$url = SUPABASE_URL . '/rest/v1/story_likes?story_id=eq.' . rawurlencode($storyId) . '&select=user_id';

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
$count = count($rows);
$liked = false;

foreach ($rows as $r) {
    if (isset($r['user_id']) && (string) $r['user_id'] === $userId) {
        $liked = true;
        break;
    }
}

echo json_encode(['count' => $count, 'liked' => $liked]);
exit;
