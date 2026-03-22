<?php
require_once __DIR__ . '/../../services/auth_service.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Informe email e senha.';
    } elseif (!function_exists('curl_init')) {
        $error = 'ERRO: cURL não está disponível neste servidor!';
    } else {
        $login = loginUser($email, $password);
        if ($login['success']) {
            $_SESSION['access_token'] = $login['access_token'];
            $_SESSION['user_id'] = $login['user_id'];
            require_once __DIR__ . '/../../config/profile_helper.php';
            ensureUserProfile($_SESSION['user_id'], $email);
            header("Location: /features/feed/index.php");
            exit;
        }
        $error = 'RESPOSTA SUPABASE (debug completo): ' . json_encode($login, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Club61</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html { height: 100%; }
        body {
            margin: 0;
            min-height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: #0A0A0A;
            color: #fff;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .auth-wrap {
            width: 100%;
            max-width: 420px;
        }
        .auth-card {
            background: #111111;
            border: 1px solid #222222;
            border-radius: 4px;
            padding: 40px 32px 36px;
        }
        @media (max-width: 480px) {
            .auth-card { padding: 28px 20px 24px; }
        }
        .auth-brand {
            margin: 0 0 8px;
            font-size: clamp(1.75rem, 5vw, 2.25rem);
            font-weight: 600;
            letter-spacing: 0.06em;
            text-align: center;
            color: #C9A84C;
        }
        .auth-sub {
            margin: 0 0 28px;
            font-size: 0.8125rem;
            text-align: center;
            color: #888;
            line-height: 1.45;
        }
        .auth-error {
            margin-bottom: 20px;
            padding: 12px 14px;
            font-size: 0.8125rem;
            line-height: 1.5;
            color: #FF6B6B;
            background: rgba(255, 107, 107, 0.08);
            border: 1px solid rgba(255, 107, 107, 0.25);
            border-radius: 4px;
            word-break: break-word;
            white-space: pre-wrap;
            font-family: ui-monospace, "Cascadia Code", "Consolas", monospace;
            max-height: 320px;
            overflow: auto;
        }
        .auth-form label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #777;
        }
        .auth-form .field { margin-bottom: 18px; }
        .auth-form input[type="email"],
        .auth-form input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            font-size: 1rem;
            color: #fff;
            background: #1A1A1A;
            border: 1px solid #333333;
            border-radius: 4px;
            outline: none;
            transition: border-color 0.15s ease;
        }
        .auth-form input::placeholder { color: #666; }
        .auth-form input:focus {
            border-color: #555;
        }
        .auth-form .btn {
            display: block;
            width: 100%;
            margin-top: 8px;
            padding: 14px 18px;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: #fff;
            background: #7B2EFF;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: box-shadow 0.25s ease, background 0.15s ease;
        }
        .auth-form .btn:hover {
            box-shadow: 0 0 24px rgba(123, 46, 255, 0.55), 0 0 48px rgba(123, 46, 255, 0.2);
        }
        .auth-form .btn:active {
            transform: translateY(1px);
        }
        .auth-foot {
            margin-top: 24px;
            text-align: center;
            font-size: 0.875rem;
        }
        .auth-foot a {
            color: #777;
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: color 0.15s ease, border-color 0.15s ease;
        }
        .auth-foot a:hover {
            color: #C9A84C;
            border-bottom-color: rgba(201, 168, 76, 0.5);
        }
    </style>
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <h1 class="auth-brand">Club61</h1>
            <p class="auth-sub">Acesso reservado a membros. Entre com suas credenciais.</p>

            <?php if ($error): ?>
            <div class="auth-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form class="auth-form" method="POST">
                <div class="field">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" placeholder="seu@email.com" autocomplete="email">
                </div>
                <div class="field">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password">
                </div>
                <button class="btn" type="submit">Entrar</button>
            </form>

            <p class="auth-foot">
                <a href="/features/auth/register.php">Não tem conta? Solicite cadastro com convite</a>
            </p>
        </div>
    </div>
</body>
</html>
