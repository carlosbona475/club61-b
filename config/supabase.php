<?php

$config = [
    // Preencha com os valores do seu projeto Supabase.
    'url' => 'https://YOUR_PROJECT_ID.supabase.co',
    'anon_key' => 'YOUR_SUPABASE_ANON_KEY',
];

if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', rtrim($config['url'], '/'));
}

if (!defined('SUPABASE_ANON_KEY')) {
    define('SUPABASE_ANON_KEY', $config['anon_key']);
}

return $config;
