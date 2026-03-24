<?php
/**
 * Cria ou reabre pedido de mensagem (pending).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../auth_guard.php';
require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/message_requests.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /features/profile/index.php');

    exit;
}

$from = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
$token = isset($_SESSION['access_token']) ? (string) $_SESSION['access_token'] : '';

if ($from === '' || $token === '' || !csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Sessão inválida ou CSRF.'));

    exit;
}

$to = isset($_POST['to_user']) ? trim((string) $_POST['to_user']) : '';
$return = isset($_POST['return_to']) ? (string) $_POST['return_to'] : '/features/profile/index.php';

if ($to === '' || $to === $from) {
    header('Location: ' . $return . '?status=error&message=' . urlencode('Pedido inválido.'));

    exit;
}

if (!mr_service_available()) {
    header('Location: ' . $return . '?status=error&message=' . urlencode('Serviço indisponível.'));

    exit;
}

$row = mr_find_pair_row($from, $to);
$headers = [
    'apikey: ' . SUPABASE_SERVICE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
    'Content-Type: application/json',
    'Prefer: return=minimal',
];

if ($row !== null) {
    $st = (string) ($row['status'] ?? '');
    $id = isset($row['id']) ? (string) $row['id'] : '';
    if ($st === 'accepted') {
        header('Location: /features/chat/dm.php?with=' . rawurlencode($to));

        exit;
    }
    if ($st === 'pending') {
        header('Location: ' . $return . '?status=ok&message=' . urlencode('Já existe um pedido pendente.'));

        exit;
    }
    if ($st === 'rejected' && $id !== '') {
        $patchUrl = SUPABASE_URL . '/rest/v1/message_requests?id=eq.' . urlencode($id);
        $ch = curl_init($patchUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => json_encode(['status' => 'pending'], JSON_UNESCAPED_UNICODE),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            header('Location: ' . $return . '?status=ok&message=' . urlencode('Pedido de mensagem enviado.'));

            exit;
        }
    }
}

$body = json_encode([
    'from_user' => $from,
    'to_user' => $to,
    'status' => 'pending',
], JSON_UNESCAPED_UNICODE);

$ch = curl_init(SUPABASE_URL . '/rest/v1/message_requests');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => $headers,
]);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code >= 200 && $code < 300) {
    header('Location: ' . $return . '?status=ok&message=' . urlencode('Pedido de mensagem enviado.'));

    exit;
}

header('Location: ' . $return . '?status=error&message=' . urlencode('Não foi possível enviar o pedido.'));

exit;
