<?php

function getSupabaseConfig()
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../../config/supabase.php';
    }

    return $config;
}

function supabaseAuthRequest($endpoint, $payload)
{
    $config = getSupabaseConfig();
    $url = rtrim($config['url'], '/') . $endpoint;

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
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

function loginUser($email, $password)
{
    $result = supabaseAuthRequest('/auth/v1/token?grant_type=password', [
        'email' => $email,
        'password' => $password,
    ]);

    if (!$result['success']) {
        return $result;
    }

    $data = $result['data'];
    $accessToken = $data['access_token'] ?? null;
    $userId = $data['user']['id'] ?? null;

    if (!$accessToken || !$userId) {
        return [
            'success' => false,
            'error' => 'Resposta de login invalida.',
        ];
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION['access_token'] = $accessToken;
    $_SESSION['user_id'] = $userId;

    return [
        'success' => true,
        'data' => $data,
    ];
}

function registerUser($email, $password)
{
    return supabaseAuthRequest('/auth/v1/signup', [
        'email' => $email,
        'password' => $password,
    ]);
}