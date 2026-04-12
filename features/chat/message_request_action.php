<?php

/**
 * Aceitar ou rejeitar pedido de mensagem (receiver).
 * POST: request_id, action=accept|reject, csrf
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/csrf.php';
require_once CLUB61_ROOT . '/config/message_requests.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /features/chat/message_requests_inbox.php');

    exit;
}

$uid = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
if ($uid === '' || !csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    header('Location: /features/chat/message_requests_inbox.php?err=1');

    exit;
}

if (!mr_service_available()) {
    header('Location: /features/chat/message_requests_inbox.php?err=2');

    exit;
}

$rid = isset($_POST['request_id']) ? trim((string) $_POST['request_id']) : '';
$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
$newStatus = $action === 'accept' ? 'accepted' : ($action === 'reject' ? 'rejected' : '');

if ($rid === '' || $newStatus === '') {
    header('Location: /features/chat/message_requests_inbox.php?err=3');

    exit;
}

$url = SUPABASE_URL . '/rest/v1/message_requests?id=eq.' . urlencode($rid) . '&select=id,to_user,status';
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
if (!is_array($rows) || empty($rows[0])) {
    header('Location: /features/chat/message_requests_inbox.php?err=4');

    exit;
}
$row = $rows[0];
if ((string) ($row['to_user'] ?? '') !== $uid) {
    header('Location: /features/chat/message_requests_inbox.php?err=5');

    exit;
}
if ((string) ($row['status'] ?? '') !== 'pending') {
    header('Location: /features/chat/message_requests_inbox.php');

    exit;
}

$patchUrl = SUPABASE_URL . '/rest/v1/message_requests?id=eq.' . urlencode($rid);
$ch = curl_init($patchUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_POSTFIELDS => json_encode(['status' => $newStatus], JSON_UNESCAPED_UNICODE),
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

if ($code >= 200 && $code < 300) {
    header('Location: /features/chat/message_requests_inbox.php?ok=1');

    exit;
}

header('Location: /features/chat/message_requests_inbox.php?err=6');

exit;
