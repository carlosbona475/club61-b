<?php
/**
 * Guarda latitude/longitude do perfil (JSON).
 * POST: latitude, longitude, csrf
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/auth_guard.php';
require_once dirname(__DIR__, 2) . '/config/supabase.php';
require_once dirname(__DIR__, 2) . '/config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);

    exit;
}

if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'csrf']);

    exit;
}

$userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
if ($userId === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth']);

    exit;
}

if (!supabase_service_role_available()) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'service_unavailable']);

    exit;
}

$now = time();
$last = isset($_SESSION['save_location_last_ts']) ? (int) $_SESSION['save_location_last_ts'] : 0;
if ($last > 0 && ($now - $last) < 2) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'rate_limit', 'retry_after' => 2 - ($now - $last)]);

    exit;
}

$latIn = isset($_POST['latitude']) ? trim((string) $_POST['latitude']) : '';
$lngIn = isset($_POST['longitude']) ? trim((string) $_POST['longitude']) : '';
$lat = filter_var($latIn, FILTER_VALIDATE_FLOAT);
$lng = filter_var($lngIn, FILTER_VALIDATE_FLOAT);
if ($lat === false || $lng === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_coordinates']);

    exit;
}

if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'out_of_range']);

    exit;
}

$payload = json_encode([
    'latitude' => round($lat, 7),
    'longitude' => round($lng, 7),
], JSON_UNESCAPED_UNICODE);

$patchUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId);
$ch = curl_init($patchUrl);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_POSTFIELDS => $payload,
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
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code < 200 || $code >= 300) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'save_failed']);

    exit;
}

$_SESSION['save_location_last_ts'] = $now;

echo json_encode([
    'ok' => true,
    'latitude' => round($lat, 7),
    'longitude' => round($lng, 7),
    'csrf' => csrf_token(),
]);
