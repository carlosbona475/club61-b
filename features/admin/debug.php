<?php

declare(strict_types=1);

/**
 * Depuração — desativado por defeito. Ative apenas em ambiente controlado:
 *   CLUB61_DEBUG=1 no .env / ambiente do servidor
 */
require_once __DIR__ . '/../../config/supabase.php';

$debugOn = getenv('CLUB61_DEBUG') === '1' || (isset($_ENV['CLUB61_DEBUG']) && $_ENV['CLUB61_DEBUG'] === '1');
if (!$debugOn) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../config/security_headers.php';
require_once __DIR__ . '/../../config/session.php';
club61_security_headers();
club61_session_start_safe();

require_once __DIR__ . '/../../config/profile_helper.php';

if (empty($_SESSION['access_token']) || empty($_SESSION['user_id'])) {
    echo 'SESSÃO VAZIA - faça login primeiro';
    exit;
}

$uid = trim((string) $_SESSION['user_id']);
$sk = defined('SUPABASE_SERVICE_KEY') ? SUPABASE_SERVICE_KEY : '';

echo '<pre>';
echo 'UID: ' . htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') . "\n";
echo 'SK configurada: ' . ($sk === '' ? 'NÃO' : 'SIM') . "\n";

$ch = curl_init(SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($uid) . '&select=role');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . $sk,
        'Authorization: Bearer ' . $sk,
        'Accept: application/json',
    ],
]);
$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo 'HTTP: ' . (int) $code . "\n";
echo 'Resposta: ' . htmlspecialchars(substr((string) $raw, 0, 2000), ENT_QUOTES, 'UTF-8') . "\n";
echo 'isAdmin: ' . (isCurrentUserAdmin() ? 'TRUE' : 'FALSE') . "\n";
echo '</pre>';
