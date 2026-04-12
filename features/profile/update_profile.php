<?php


declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';
require_once CLUB61_ROOT . '/config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /features/profile/index.php');
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$token = $_SESSION['access_token'] ?? '';

if ($userId === null || $userId === '' || $token === '' || !csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Sessão inválida ou token CSRF.'));
    exit;
}

if (!supabase_service_role_available()) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Configure SUPABASE_SERVICE_KEY (service_role) em config/supabase.php no servidor.'));
    exit;
}

$userId = (string) $userId;

$allowedTipo = ['Homem', 'Mulher', 'Casal'];
$allowedRel = ['solteiro', 'solteira', 'casal', 'casado', 'casada'];
$tipo = isset($_POST['tipo']) ? trim((string) $_POST['tipo']) : '';
$cidade = isset($_POST['cidade']) ? trim((string) $_POST['cidade']) : '';
$bio = isset($_POST['bio']) ? trim((string) $_POST['bio']) : '';
$ageStr = isset($_POST['age']) ? trim((string) $_POST['age']) : '';
$relationshipType = isset($_POST['relationship_type']) ? strtolower(trim((string) $_POST['relationship_type'])) : '';
$partnerAgeStr = isset($_POST['partner_age']) ? trim((string) $_POST['partner_age']) : '';

if ($tipo === '' || !in_array($tipo, $allowedTipo, true)) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Selecione um tipo válido (Homem, Mulher ou Casal).'));
    exit;
}

$cidadeLen = function_exists('mb_strlen') ? mb_strlen($cidade, 'UTF-8') : strlen($cidade);
if ($cidadeLen > 120) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Cidade muito longa (máximo 120 caracteres).'));
    exit;
}

$bioLen = function_exists('mb_strlen') ? mb_strlen($bio, 'UTF-8') : strlen($bio);
if ($bioLen > 2000) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Bio muito longa.'));
    exit;
}

if ($relationshipType === '' || !in_array($relationshipType, $allowedRel, true)) {
    header('Location: /features/profile/index.php?status=error&message=' . urlencode('Selecione o tipo de relacionamento.'));
    exit;
}

$age = null;
if ($ageStr !== '') {
    if (!ctype_digit($ageStr)) {
        header('Location: /features/profile/index.php?status=error&message=' . urlencode('Idade inválida.'));
        exit;
    }
    $age = (int) $ageStr;
    if ($age < 18 || $age > 120) {
        header('Location: /features/profile/index.php?status=error&message=' . urlencode('Idade deve estar entre 18 e 120.'));
        exit;
    }
}

$partnerAge = null;
if ($relationshipType === 'casal') {
    if ($partnerAgeStr === '' || !ctype_digit($partnerAgeStr)) {
        header('Location: /features/profile/index.php?status=error&message=' . urlencode('Informe a idade do(a) parceiro(a).'));
        exit;
    }
    $partnerAge = (int) $partnerAgeStr;
    if ($partnerAge < 18 || $partnerAge > 120) {
        header('Location: /features/profile/index.php?status=error&message=' . urlencode('Idade do parceiro(a) inválida.'));
        exit;
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
$getUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId) . '&select=id,tipo,cidade,display_id,bio,age,relationship_type,partner_age';
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
        'bio' => $bio,
        'age' => $age,
        'relationship_type' => $relationshipType,
        'partner_age' => $relationshipType === 'casal' ? $partnerAge : null,
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
        'bio' => $bio,
        'age' => $age,
        'relationship_type' => $relationshipType,
        'partner_age' => $relationshipType === 'casal' ? $partnerAge : null,
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
$verifyUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId) . '&select=tipo,cidade,bio,age,relationship_type,partner_age';
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
$vBio = isset($v['bio']) && $v['bio'] !== null ? trim((string) $v['bio']) : '';
$vRel = isset($v['relationship_type']) && $v['relationship_type'] !== null ? strtolower(trim((string) $v['relationship_type'])) : '';

if ($vTipo !== $tipo || $vCidade !== $cidade || $vRel !== $relationshipType) {
    club61_redirect_profile_error(
        'Validação',
        'tipo/cidade/relacionamento divergentes após salvar.'
    );
}
if ($vBio !== $bio) {
    club61_redirect_profile_error('Validação', 'Bio não persistida como esperado.');
}

header('Location: /features/profile/index.php?status=ok&message=' . urlencode('Perfil atualizado com sucesso!'));
exit;
