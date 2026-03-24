<?php
declare(strict_types=1);

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

return $config;
