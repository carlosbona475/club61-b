<?php
declare(strict_types=1);

/**
 * Servico de autenticacao Supabase (Auth REST API).
 * Configuracao: config/supabase.php
 */

require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../config/session.php';

function getSupabaseConfig(): array
{
    static $config = null;

    if ($config === null) {
        $config = [
            'url' => defined('SUPABASE_URL') ? (string) SUPABASE_URL : '',
            'anon_key' => defined('SUPABASE_ANON_KEY') ? (string) SUPABASE_ANON_KEY : '',
            'service_key' => defined('SUPABASE_SERVICE_KEY') ? (string) SUPABASE_SERVICE_KEY : '',
        ];
    }

    return $config;
}

/**
 * Extrai o claim "sub" (user id) de um JWT Supabase.
 */
function club61_jwt_extract_sub(string $jwt): ?string
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }
    $b64 = $parts[1];
    $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
    $b64 = strtr($b64, '-_', '+/');
    $json = base64_decode($b64, true);
    if ($json === false) {
        return null;
    }
    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        return null;
    }
    $sub = $payload['sub'] ?? null;

    return is_string($sub) && $sub !== '' ? $sub : null;
}

/**
 * Mensagem segura para o utilizador quando loginUser() falha.
 *
 * @param array<string, mixed> $login
 */
function login_failure_user_message(array $login): string
{
    if (!empty($login['config_error']) && is_string($login['config_error'])) {
        return $login['config_error'];
    }

    $r = $login['supabase_response'] ?? null;
    if (!is_array($r)) {
        return 'E-mail ou senha incorretos.';
    }

    if (!empty($r['curl_error']) && is_string($r['curl_error'])) {
        return 'Não foi possível ligar ao Supabase. Verifique a internet ou o SUPABASE_URL no .env.';
    }

    if (isset($r['raw'])) {
        return 'Resposta inválida do Supabase. Confirme SUPABASE_URL e SUPABASE_ANON_KEY (chave anon JWT, não sb_publishable).';
    }

    $msg = null;
    if (isset($r['error_description']) && is_string($r['error_description'])) {
        $msg = $r['error_description'];
    } elseif (isset($r['msg']) && is_string($r['msg'])) {
        $msg = $r['msg'];
    } elseif (isset($r['message']) && is_string($r['message'])) {
        $msg = $r['message'];
    } elseif (isset($r['error']) && is_string($r['error'])) {
        $msg = $r['error'];
    }

    if (is_string($msg) && $msg !== '') {
        $low = strtolower($msg);
        if (str_contains($low, 'email not confirmed') || str_contains($low, 'confirm your email')) {
            return 'Confirme o e-mail (link do Supabase) ou desative “Confirm email” em Authentication → Providers → Email.';
        }
        if (
            str_contains($low, 'invalid login')
            || str_contains($low, 'invalid_grant')
            || str_contains($low, 'invalid credentials')
        ) {
            return 'E-mail ou senha incorretos.';
        }

        return substr(trim($msg), 0, 280);
    }

    return 'E-mail ou senha incorretos.';
}

/**
 * Login via Supabase Auth (password grant).
 * Retorna o JSON decodificado da API, ou arrays de diagnostico em falha de rede/parse.
 */
function supabaseLogin(string $email, string $password): array
{
    $config = getSupabaseConfig();

    $ch = curl_init();
    $anon = $config['anon_key'];
    $headers = [
        'apikey: ' . $anon,
        'Content-Type: application/json',
    ];
    if ($anon !== '') {
        $headers[] = 'Authorization: Bearer ' . $anon;
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim($config['url'], '/') . '/auth/v1/token?grant_type=password',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode([
            'email' => $email,
            'password' => $password,
        ]),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $response === '') {
        return ['curl_error' => $curlError, 'http_code' => $httpCode];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['raw' => $response, 'http_code' => $httpCode];
    }

    return $decoded;
}

function supabaseAuthRequest(string $endpoint, array $payload): array
{
    $config = getSupabaseConfig();
    $url = rtrim($config['url'], '/') . $endpoint;

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . $config['anon_key'],
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($rawResponse === false) {
        return [
            'success' => false,
            'error' => $curlError ?: 'Falha na requisicao ao Supabase.',
        ];
    }

    $response = json_decode($rawResponse, true);

    if ($statusCode < 200 || $statusCode >= 300) {
        $message = $response['msg'] ?? $response['error_description'] ?? $response['error'] ?? 'Erro de autenticacao.';
        return [
            'success' => false,
            'error' => $message,
            'status_code' => $statusCode,
        ];
    }

    return [
        'success' => true,
        'data' => $response,
    ];
}

function loginUser(string $email, string $password): array
{
    $config = getSupabaseConfig();
    if ($config['url'] === '' || $config['anon_key'] === '') {
        return [
            'success' => false,
            'config_error' => 'Falta configurar .env na raiz do site: SUPABASE_URL e SUPABASE_ANON_KEY (chave anon longa, em Project Settings → API).',
        ];
    }

    $data = supabaseLogin($email, $password);

    if (!is_array($data)) {
        return [
            'success' => false,
            'supabase_response' => $data,
        ];
    }

    // Diagnostico de rede / corpo nao-JSON (retornado por supabaseLogin)
    if (isset($data['curl_error']) || isset($data['raw'])) {
        return [
            'success' => false,
            'supabase_response' => $data,
        ];
    }

    if (isset($data['access_token'])) {
        $token = (string) $data['access_token'];
        $userId = null;
        if (isset($data['user']) && is_array($data['user'])) {
            $uid = $data['user']['id'] ?? null;
            $userId = is_string($uid) ? $uid : (is_numeric($uid) ? (string) $uid : null);
        }
        if ($userId === null || $userId === '') {
            $userId = club61_jwt_extract_sub($token);
        }
        if ($userId === null || $userId === '') {
            return [
                'success' => false,
                'supabase_response' => $data,
            ];
        }

        $userRow = isset($data['user']) && is_array($data['user']) ? $data['user'] : [];
        $meta = isset($userRow['user_metadata']) && is_array($userRow['user_metadata']) ? $userRow['user_metadata'] : [];
        $role = isset($meta['role']) ? (string) $meta['role'] : 'member';

        return [
            'success' => true,
            'access_token' => $token,
            'user_id' => $userId,
            'role' => $role,
        ];
    }

    // Erro da API (401, invalid credentials, etc.) — resposta completa para debug
    return [
        'success' => false,
        'supabase_response' => $data,
    ];
}

function registerUser(string $email, string $password): array
{
    $r = supabaseAuthRequest('/auth/v1/signup', [
        'email' => $email,
        'password' => $password,
    ]);
    if (!empty($r['success']) && !empty($r['data']) && is_array($r['data'])) {
        $data = $r['data'];
        $userId = null;
        if (isset($data['user']) && is_array($data['user']) && isset($data['user']['id'])) {
            $userId = (string) $data['user']['id'];
        } elseif (isset($data['id'])) {
            $userId = (string) $data['id'];
        }
        $r['user_id'] = $userId;
    }

    return $r;
}
