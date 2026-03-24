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
        $config = require __DIR__ . '/../config/supabase.php';
    }

    return $config;
}

/**
 * Login via Supabase Auth (password grant).
 * Retorna o JSON decodificado da API, ou arrays de diagnostico em falha de rede/parse.
 */
function supabaseLogin(string $email, string $password): array
{
    $config = getSupabaseConfig();

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim($config['url'], '/') . '/auth/v1/token?grant_type=password',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $config['anon_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'email' => $email,
            'password' => $password,
        ]),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
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
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
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
        $userId = $data['user']['id'] ?? null;
        if ($userId === null) {
            return [
                'success' => false,
                'supabase_response' => $data,
            ];
        }

        club61_session_start_safe();

        $_SESSION['access_token'] = $data['access_token'];
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = isset($data['user']['user_metadata']['role']) ? (string) $data['user']['user_metadata']['role'] : 'member';

        return [
            'success' => true,
            'access_token' => $data['access_token'],
            'user_id' => $userId,
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
    return supabaseAuthRequest('/auth/v1/signup', [
        'email' => $email,
        'password' => $password,
    ]);
}
