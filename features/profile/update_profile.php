<?php


declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';
require_once CLUB61_ROOT . '/config/csrf.php';

/**
 * URL interna segura para redirecionamento pós-POST (ex.: /features/profile/settings.php).
 */
function club61_profile_safe_return_to(?string $raw): ?string
{
    if ($raw === null || $raw === '') {
        return null;
    }
    $t = trim($raw);
    if (!str_starts_with($t, '/') || str_starts_with($t, '//')) {
        return null;
    }
    if (strpbrk($t, "\r\n") !== false) {
        return null;
    }

    return $t;
}

$returnTo = club61_profile_safe_return_to(isset($_POST['return_to']) ? (string) $_POST['return_to'] : null);
$profileErrBase = $returnTo ?? '/features/profile/index.php';

function club61_profile_err(string $message): void
{
    global $profileErrBase;
    $q = str_contains($profileErrBase, '?') ? '&' : '?';
    header('Location: ' . $profileErrBase . $q . 'status=error&message=' . rawurlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /features/profile/index.php');
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$token = $_SESSION['access_token'] ?? '';

if ($userId === null || $userId === '' || $token === '' || !csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    club61_profile_err('Sessão inválida ou token CSRF.');
}

if (!supabase_service_role_available()) {
    club61_profile_err('Configure SUPABASE_SERVICE_KEY (service_role) em config/supabase.php no servidor.');
}

$userId = (string) $userId;

$allowedRel = ['solteiro', 'solteira', 'casal', 'casado', 'casada', 'namorando', 'prefiro_nao_dizer'];
$bio = isset($_POST['bio']) ? trim((string) $_POST['bio']) : '';
$ageStr = isset($_POST['age']) ? trim((string) $_POST['age']) : '';
$cidadeRaw = isset($_POST['cidade']) ? trim((string) $_POST['cidade']) : '';
$relationshipStatus = isset($_POST['relationship_status']) ? strtolower(trim((string) $_POST['relationship_status'])) : '';

$bioLen = function_exists('mb_strlen') ? mb_strlen($bio, 'UTF-8') : strlen($bio);
if ($bioLen > 2000) {
    club61_profile_err('Bio muito longa.');
}

$cidadeLen = function_exists('mb_strlen') ? mb_strlen($cidadeRaw, 'UTF-8') : strlen($cidadeRaw);
if ($cidadeLen > 120) {
    club61_profile_err('Cidade muito longa (máximo 120 caracteres).');
}
$cidade = $cidadeRaw === '' ? null : $cidadeRaw;

if ($relationshipStatus === '' || !in_array($relationshipStatus, $allowedRel, true)) {
    club61_profile_err('Selecione o tipo de relacionamento.');
}

$age = null;
if ($ageStr !== '') {
    if (!ctype_digit($ageStr)) {
        club61_profile_err('Idade inválida.');
    }
    $age = (int) $ageStr;
    if ($age < 18 || $age > 120) {
        club61_profile_err('Idade deve estar entre 18 e 120.');
    }
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
    global $profileErrBase;
    $msg = $ctx . ': ' . $detail;
    $short = function_exists('mb_substr') ? mb_substr($msg, 0, 1800, 'UTF-8') : substr($msg, 0, 1800);
    $q = str_contains($profileErrBase, '?') ? '&' : '?';
    header('Location: ' . $profileErrBase . $q . 'status=error&message=' . rawurlencode($short));
    exit;
}

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

$getUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId) . '&select=' . rawurlencode(CLUB61_PROFILE_REST_SELECT);
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

$payload = [
    'bio' => $bio,
    'age' => $age,
    'relationship_status' => $relationshipStatus,
    'cidade' => $cidade,
];

if (!empty($existingRows)) {
    $patchUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId);
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
    $count = countProfilesTotalUsingServiceRole();
    $role = $count === 0 ? 'admin' : 'membro';

    $insertPayload = array_merge([
        'id' => $userId,
        'role' => $role,
    ], $payload);

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

$verifyUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId) . '&select=bio,age,relationship_status,cidade';
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
$vBio = isset($v['bio']) && $v['bio'] !== null ? trim((string) $v['bio']) : '';
$vRel = isset($v['relationship_status']) && $v['relationship_status'] !== null ? strtolower(trim((string) $v['relationship_status'])) : '';
$vAge = isset($v['age']) && $v['age'] !== null && $v['age'] !== '' ? (int) $v['age'] : null;
$vCidade = isset($v['cidade']) && $v['cidade'] !== null ? trim((string) $v['cidade']) : '';
$expectCidade = $cidade === null ? '' : $cidade;

if ($vRel !== $relationshipStatus) {
    club61_redirect_profile_error(
        'Validação',
        'Relacionamento não persistido como esperado.'
    );
}
if ($vBio !== $bio) {
    club61_redirect_profile_error('Validação', 'Bio não persistida como esperado.');
}
if ($vAge !== $age) {
    club61_redirect_profile_error('Validação', 'Idade não persistida como esperado.');
}
if ($vCidade !== $expectCidade) {
    club61_redirect_profile_error('Validação', 'Cidade não persistida como esperado.');
}

$okBase = $returnTo ?? '/features/profile/index.php';
$okQ = str_contains($okBase, '?') ? '&' : '?';
header('Location: ' . $okBase . $okQ . 'status=ok&message=' . rawurlencode('Perfil atualizado com sucesso!'));
exit;
