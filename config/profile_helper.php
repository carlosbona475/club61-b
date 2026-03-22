<?php

require_once __DIR__ . '/supabase.php';

/**
 * Service role configurada (servidor apenas).
 */
function supabase_service_role_available(): bool
{
    return defined('SUPABASE_SERVICE_KEY') && SUPABASE_SERVICE_KEY !== '';
}

/**
 * Headers REST com service_role (bypass RLS). Uso exclusivo no PHP servidor.
 *
 * @return list<string>
 */
function supabase_service_rest_headers(bool $jsonBody = false): array
{
    $key = SUPABASE_SERVICE_KEY;
    $h = [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
    ];
    if ($jsonBody) {
        $h[] = 'Content-Type: application/json';
    }

    return $h;
}

/**
 * Conta perfis usando service_role (quando RLS bloqueia leitura com JWT de usuário).
 */
function countProfilesTotalUsingServiceRole(): int
{
    if (!supabase_service_role_available()) {
        return 0;
    }
    $url = SUPABASE_URL . '/rest/v1/profiles?select=id';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(supabase_service_rest_headers(false), [
        'Prefer: count=exact',
        'Range: 0-0',
    ]));
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return 0;
    }
    $sep = strpos($raw, "\r\n\r\n");
    if ($sep === false) {
        return 0;
    }
    $headerBlock = substr($raw, 0, $sep);
    if (preg_match('/Content-Range:\s*[\d]+-[\d]+\/(\d+)/i', $headerBlock, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/Content-Range:\s*\*\/(\d+)/i', $headerBlock, $m)) {
        return (int) $m[1];
    }

    return 0;
}

/**
 * Se display_id estiver vazio, grava o próximo CL sequencial (service_role).
 * Retorna o novo display_id ou null se não alterou / indisponível.
 */
function assignDisplayIdIfEmptyForUser(string $userId): ?string
{
    if (!supabase_service_role_available() || $userId === '') {
        return null;
    }

    $getUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId) . '&select=display_id';
    $ch = curl_init($getUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(supabase_service_rest_headers(false), [
        'Accept: application/json',
    ]));
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300) {
        return null;
    }
    $rows = json_decode($body, true);
    if (!is_array($rows) || $rows === []) {
        return null;
    }
    $disp = isset($rows[0]['display_id']) ? trim((string) $rows[0]['display_id']) : '';
    if ($disp !== '') {
        return null;
    }

    $cnt = countProfilesTotalUsingServiceRole();
    $newId = buildDisplayIdForNewProfile(max(0, $cnt - 1));

    $patchUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($userId);
    $ch = curl_init($patchUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode(['display_id' => $newId]),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge(supabase_service_rest_headers(true), [
            'Prefer: return=minimal',
        ]),
    ]);
    curl_exec($ch);
    $patchCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($patchCode < 200 || $patchCode >= 300) {
        return null;
    }

    return $newId;
}

/**
 * Conta o total de linhas em profiles (header Content-Range do PostgREST).
 */
function countProfilesTotal(string $token): int
{
    $url = SUPABASE_URL . '/rest/v1/profiles?select=id';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $token,
        'Prefer: count=exact',
        'Range: 0-0',
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) {
        return 0;
    }
    $sep = strpos($raw, "\r\n\r\n");
    if ($sep === false) {
        return 0;
    }
    $headerBlock = substr($raw, 0, $sep);
    if (preg_match('/Content-Range:\s*[\d]+-[\d]+\/(\d+)/i', $headerBlock, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/Content-Range:\s*\*\/(\d+)/i', $headerBlock, $m)) {
        return (int) $m[1];
    }

    return 0;
}

/**
 * Gera display_id sequencial: 0 registros -> CL01, 1 -> CL02, etc. (mínimo 2 dígitos).
 *
 * @param int $rowCountBeforeInsert Quantidade de linhas em profiles antes do INSERT
 */
function buildDisplayIdForNewProfile(int $rowCountBeforeInsert): string
{
    $next = $rowCountBeforeInsert + 1;

    return 'CL' . str_pad((string) $next, 2, '0', STR_PAD_LEFT);
}

/**
 * Garante que exista um registro em profiles apos o login.
 * Primeiro usuario da tabela recebe role admin; demais, member.
 */
function ensureUserProfile($user_id, $email)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $token = $_SESSION['access_token'] ?? '';
    if ($token === '' || $user_id === null || $user_id === '') {
        return;
    }

    // STEP 1 — perfil ja existe?
    $url1 = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode((string) $user_id);
    $ch = curl_init($url1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $token,
    ]);
    $response1 = curl_exec($ch);
    curl_close($ch);

    $existing = json_decode($response1, true);
    if (!is_array($existing)) {
        $existing = [];
    }
    if (!empty($existing)) {
        return;
    }

    // STEP 2 — contar perfis e definir role + display_id sequencial (CL01, CL02...)
    $rowCount = countProfilesTotal($token);
    $role = $rowCount === 0 ? 'admin' : 'member';
    $display_id = buildDisplayIdForNewProfile($rowCount);

    // STEP 3 — inserir perfil
    $username = '';
    if ($email !== null && $email !== '') {
        $parts = explode('@', (string) $email, 2);
        $username = $parts[0];
    }

    $payload = [
        'id' => $user_id,
        'username' => $username,
        'role' => $role,
        'display_id' => $display_id,
    ];

    $ch = curl_init(SUPABASE_URL . '/rest/v1/profiles');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal',
        'Authorization: Bearer ' . $token,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_exec($ch);
    curl_close($ch);
}

/**
 * Busca uma linha em profiles por id (Supabase REST).
 *
 * @return array<string, mixed>|null
 */
function fetchProfileById($userId, $select)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $token = $_SESSION['access_token'] ?? '';
    if ($token === '' || $userId === null || $userId === '') {
        return null;
    }

    $url = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode((string) $userId) . '&select=' . rawurlencode($select);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $token,
    ]);
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $statusCode < 200 || $statusCode >= 300) {
        return null;
    }

    $rows = json_decode($response, true);
    if (!is_array($rows) || $rows === []) {
        return null;
    }

    return $rows[0];
}

/**
 * Verifica se o usuario logado tem role admin em profiles.
 */
function isCurrentUserAdmin()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $uid = $_SESSION['user_id'] ?? null;
    if ($uid === null || $uid === '') {
        return false;
    }

    $row = fetchProfileById($uid, 'role');

    return $row !== null && (($row['role'] ?? '') === 'admin');
}
