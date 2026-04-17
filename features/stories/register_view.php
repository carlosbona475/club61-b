<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';

$userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
$storyId = isset($_GET['story_id']) ? (string) $_GET['story_id'] : '';

if ($userId === '' || $storyId === '') {
    echo json_encode(['ok' => false]);

    exit;
}

if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    echo json_encode(['ok' => false]);

    exit;
}

$payload = json_encode([
    'story_id' => $storyId,
    'viewer_id' => $userId,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init(SUPABASE_URL . '/rest/v1/story_views');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: application/json',
        'Prefer: resolution=ignore-duplicates',
    ],
]);
curl_exec($ch);
curl_close($ch);

echo json_encode(['ok' => true]);

exit;
