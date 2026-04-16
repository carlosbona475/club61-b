<?php

declare(strict_types=1);

/**
 * Página de diagnóstico rápido (sessão + variáveis Supabase no .env).
 * Ative só em ambiente controlado: CLUB61_DEBUG=1 no .env ou no servidor.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CLUB61_ROOT . '/config/dotenv.php';
require_once CLUB61_ROOT . '/config/security_headers.php';
require_once CLUB61_ROOT . '/config/session.php';

club61_security_headers();

$debugOn = getenv('CLUB61_DEBUG') === '1' || (isset($_ENV['CLUB61_DEBUG']) && $_ENV['CLUB61_DEBUG'] === '1');
if (!$debugOn) {
    http_response_code(404);
    exit;
}

club61_session_start_safe();

header('Content-Type: text/html; charset=utf-8');

$supabaseUrl = (string) (($_ENV['SUPABASE_URL'] ?? getenv('SUPABASE_URL')) ?: '');
$supabaseKey = (string) (($_ENV['SUPABASE_KEY'] ?? getenv('SUPABASE_KEY')) ?: '');
$supabaseAnon = (string) (($_ENV['SUPABASE_ANON_KEY'] ?? getenv('SUPABASE_ANON_KEY')) ?: '');
$supabaseService = (string) (($_ENV['SUPABASE_SERVICE_KEY'] ?? getenv('SUPABASE_SERVICE_KEY')) ?: '');

$keyLabel = $supabaseKey !== '' ? $supabaseKey : $supabaseAnon;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Admin</title>
</head>
<body style="background:#111;color:#eee;font-family:system-ui,sans-serif;padding:24px;">
    <h2>Teste Admin</h2>
    <p>Session user_id: <?= htmlspecialchars((string) ($_SESSION['user_id'] ?? 'NÃO DEFINIDO'), ENT_QUOTES, 'UTF-8') ?></p>
    <p>Supabase URL: <?= strlen($supabaseUrl) > 10 ? 'OK ✅' : htmlspecialchars($supabaseUrl !== '' ? $supabaseUrl : 'NÃO ENCONTRADO', ENT_QUOTES, 'UTF-8') ?></p>
    <p>Supabase KEY (SUPABASE_KEY ou SUPABASE_ANON_KEY): <?= strlen($keyLabel) > 10 ? 'OK ✅' : htmlspecialchars($keyLabel !== '' ? $keyLabel : 'NÃO ENCONTRADO', ENT_QUOTES, 'UTF-8') ?></p>
    <p>SUPABASE_SERVICE_KEY: <?= strlen($supabaseService) > 10 ? 'OK ✅' : 'ausente ou curta' ?></p>
    <p>PHP: <?= htmlspecialchars(phpversion(), ENT_QUOTES, 'UTF-8') ?></p>
    <p>Teste concluído ✅</p>
    <p style="font-size:0.85rem;color:#888;margin-top:24px;">Requer <code>CLUB61_DEBUG=1</code> no .env. Não exponha em produção.</p>
</body>
</html>
