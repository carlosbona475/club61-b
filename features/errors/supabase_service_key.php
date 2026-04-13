<?php
declare(strict_types=1);

/**
 * Página estática: não inclui config/supabase.php (evita redirecionamento em loop).
 */
http_response_code(503);
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração — Club61</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0A0A0A; color: #eee; margin: 0; padding: 32px 16px; text-align: center; }
        h1 { color: #C9A84C; font-size: 1.1rem; }
        p { max-width: 480px; margin: 16px auto; line-height: 1.5; color: #aaa; }
        a { color: #7B2EFF; }
    </style>
</head>
<body>
    <h1>SUPABASE_SERVICE_KEY inválida</h1>
    <p>Use a chave <strong>service_role</strong> (não a <strong>anon</strong>) em Project Settings → API. É um JWT longo que começa com <code>eyJ</code> e no payload tem <code>role: service_role</code>.</p>
    <p><a href="/features/auth/login.php">Voltar ao login</a></p>
</body>
</html>
