<?php
declare(strict_types=1);

require_once __DIR__ . '/php_polyfills.php';

/**
 * Carrega .env simples (KEY=VALUE), sem dependências externas.
 */
function club61_load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));
        $val = trim($val, "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $val);
            $_ENV[$key] = $val;
        }
    }
}

club61_load_env_file(dirname(__DIR__) . '/.env');

$config = [
    'url' => (string) (getenv('SUPABASE_URL') ?: ''),
    'anon_key' => (string) (getenv('SUPABASE_ANON_KEY') ?: ''),
    'service_key' => (string) (getenv('SUPABASE_SERVICE_KEY') ?: ''),
];

if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', rtrim($config['url'], '/'));
}
if (!defined('SUPABASE_ANON_KEY')) {
    define('SUPABASE_ANON_KEY', $config['anon_key']);
}
if (!defined('SUPABASE_SERVICE_KEY')) {
    define('SUPABASE_SERVICE_KEY', $config['service_key']);
}

/**
 * Claim "role" do JWT Supabase (anon | authenticated | service_role).
 * A chave service_role é um JWT longo (tipicamente começa com eyJ), distinta da anon.
 */
function club61_supabase_jwt_role(string $jwt): ?string
{
    $jwt = trim($jwt);
    if ($jwt === '') {
        return null;
    }
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return null;
    }
    $b64 = $parts[1];
    $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
    $b64 = strtr($b64, '-_', '+/');
    $raw = base64_decode($b64, true);
    if ($raw === false) {
        return null;
    }
    $payload = json_decode($raw, true);
    if (!is_array($payload) || !isset($payload['role']) || !is_string($payload['role'])) {
        return null;
    }

    return $payload['role'];
}

// SUPABASE_SERVICE_KEY deve ser a service_role (JWT com role=service_role; não usar anon).
$sk = defined('SUPABASE_SERVICE_KEY') ? (string) SUPABASE_SERVICE_KEY : '';
$svcRole = $sk !== '' ? club61_supabase_jwt_role($sk) : null;
if ($sk === '' || $svcRole !== 'service_role' || !str_starts_with($sk, 'eyJ')) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Location: /features/errors/supabase_service_key.php');
        exit;
    }
    fwrite(STDERR, "SUPABASE_SERVICE_KEY inválida. Use a service_role key do painel Supabase (JWT eyJ... com role=service_role).\n");
    exit(1);
}

return $config;
