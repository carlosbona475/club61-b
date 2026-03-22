<?php
require_once __DIR__ . '/../../auth_guard.php';
require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../config/profile_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /features/profile/index.php');
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$token = $_SESSION['access_token'] ?? '';

if ($userId === null || $userId === '' || $token === '') {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Sessão inválida. Faça login novamente.'));
    exit;
}

if (!supabase_service_role_available()) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Configure SUPABASE_SERVICE_KEY (service_role) em config/supabase.php no servidor.'));
    exit;
}

$userId = (string) $userId;

$allowedTipo = ['Homem', 'Mulher', 'Casal'];
$tipo = isset($_POST['tipo']) ? trim((string) $_POST['tipo']) : '';
$cidade = isset($_POST['cidade']) ? trim((string) $_POST['cidade']) : '';

if ($tipo === '' || !in_array($tipo, $allowedTipo, true)) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Selecione um tipo válido (Homem, Mulher ou Casal).'));
    exit;
}

$cidadeLen = function_exists('mb_strlen') ? mb_strlen($cidade, 'UTF-8') : strlen($cidade);
if ($cidadeLen > 120) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Cidade muito longa (máximo 120 caracteres).'));
    exit;
}

/**
 * Extrai mensagem de erro do JSON do PostgREST / Supabase.
 */
function club61_supabase_error_message(?string $rawBody, int $httpCode): string
{
    $base = 'HTTP ' . $httpCode;
    if ($rawBody === null || $rawBody === '') {
        return $base;
    }
    $j = json_decode($rawBody, true);
    if (is_array($j)) {
        if (!empty($j['message'])) {
            return (string) $j['message'] . (isset($j['hint']) ? ' — ' . (string) $j['hint'] : '');
        }
        if (!empty($j['error'])) {
            return (string) $j['error'];
        }
    }

    return $base . ': ' . substr($rawBody, 0, 400);
}

function club61_redirect_profile_error(string $ctx, string $detail): void
{
    $msg = $ctx . ': ' . $detail;
    $short = function_exists('mb_substr') ? mb_substr($msg, 0, 1800, 'UTF-8') : substr($msg, 0, 1800);
    header('Location: /features/profile/index.php?status=error&message=' . urlencode($short));
    exit;
}

// Todas as chamadas REST usam service_role (apikey + Authorization Bearer) — não usar JWT da sessão.
$sk = SUPABASE_SERVICE_KEY;
$headersGetService = [
    'apikey: ' . $sk,
    'Authorization: Bearer ' . $sk,
    'Accept: application/json',
];
$headersWriteService = [
    'apikey: ' . $sk,
    'Authorization: Bearer ' . $sk,
    'Content-Type: application/json',
    'Prefer: return=minimal',
];

// 1) GET — verificar se o registro existe
$getUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId) . '&select=id,tipo,cidade,display_id';
$ch = curl_init($getUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headersGetService);
curl_setopt($ch, CURLOPT_HTTPGET, true);
$getBody = curl_exec($ch);
$getCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($getBody === false || $getCode < 200 || $getCode >= 300) {
    club61_redirect_profile_error('GET perfil', club61_supabase_error_message($getBody !== false ? $getBody : null, $getCode));
}

$existingRows = json_decode($getBody, true);
if (!is_array($existingRows)) {
    $existingRows = [];
}

$count = countProfilesTotalUsingServiceRole();
$display_id = 'CL' . str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT);

if (!empty($existingRows)) {
    // PATCH
    $patchUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId);
    $payload = [
        'tipo' => $tipo,
        'cidade' => $cidade,
        'display_id' => $display_id,
    ];
    $ch = curl_init($patchUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $headersWriteService,
    ]);
    $patchBody = curl_exec($ch);
    $patchCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($patchCode < 200 || $patchCode >= 300) {
        club61_redirect_profile_error('PATCH perfil', club61_supabase_error_message($patchBody !== false ? $patchBody : null, $patchCode));
    }
} else {
    // POST — inserir
    $role = $count === 0 ? 'admin' : 'member';

    $username = '';
    if (!empty($_SESSION['user_email'])) {
        $parts = explode('@', (string) $_SESSION['user_email'], 2);
        $username = $parts[0];
    }

    $insertPayload = [
        'id' => $userId,
        'username' => $username,
        'role' => $role,
        'display_id' => $display_id,
        'tipo' => $tipo,
        'cidade' => $cidade,
    ];

    $ch = curl_init(SUPABASE_URL . '/rest/v1/profiles');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($insertPayload),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $headersWriteService,
    ]);
    $postBody = curl_exec($ch);
    $postCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($postCode < 200 || $postCode >= 300) {
        club61_redirect_profile_error('POST perfil', club61_supabase_error_message($postBody !== false ? $postBody : null, $postCode));
    }
}

// 2) GET — confirmar persistência
$verifyUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId) . '&select=tipo,cidade';
$ch = curl_init($verifyUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headersGetService);
curl_setopt($ch, CURLOPT_HTTPGET, true);
$verifyBody = curl_exec($ch);
$verifyCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($verifyBody === false || $verifyCode < 200 || $verifyCode >= 300) {
    club61_redirect_profile_error('GET confirmação', club61_supabase_error_message($verifyBody !== false ? $verifyBody : null, $verifyCode));
}

$verifyRows = json_decode($verifyBody, true);
if (!is_array($verifyRows) || $verifyRows === []) {
    club61_redirect_profile_error('Confirmação', 'Nenhuma linha retornada após salvar.');
}

$v = $verifyRows[0];
$vTipo = isset($v['tipo']) ? trim((string) $v['tipo']) : '';
$vCidade = isset($v['cidade']) ? trim((string) $v['cidade']) : '';

if ($vTipo !== $tipo || $vCidade !== $cidade) {
    club61_redirect_profile_error(
        'Validação',
        'tipo/cidade divergentes. DB: ' . json_encode(['tipo' => $vTipo, 'cidade' => $vCidade], JSON_UNESCAPED_UNICODE)
    );
}

header('Location: /features/profile/index.php?status=ok&message=' . urlencode('Perfil atualizado com sucesso!'));
exit;
