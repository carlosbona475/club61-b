<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../services/auth_service.php';
require_once __DIR__ . '/../../config/supabase.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invite_code = trim($_POST['invite_code'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($invite_code === '' || $email === '' || $password === '') {
        $errorMessage = 'Informe codigo de convite, email e senha.';
    } else {
        $url = SUPABASE_URL . '/rest/v1/invites?code=eq.' . urlencode($invite_code) . '&status=eq.available';

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_ANON_KEY,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $invite = json_decode($response, true);
        if (!is_array($invite)) {
            $invite = [];
        }

        if (empty($invite)) {
            $errorMessage = "Convite inválido ou já utilizado";
        }
    }

    if (empty($errorMessage)) {
        // Signup no Supabase
        $result = registerUser($email, $password);

        if ($result['success']) {
            header('Location: /features/auth/login.php');
            exit;
        }

        $errorMessage = $result['error'] ?? 'Nao foi possivel criar a conta.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro — Club61</title>
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
            font-size: 0.875rem;
            line-height: 1.4;
            color: #FF6B6B;
            background: rgba(255, 107, 107, 0.08);
            border: 1px solid rgba(255, 107, 107, 0.25);
            border-radius: 4px;
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
        .auth-form input[type="text"],
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
            <p class="auth-sub">Cadastro exclusivo com código de convite. Preencha os dados abaixo.</p>

            <?php if ($errorMessage): ?>
            <div class="auth-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form class="auth-form" action="" method="POST">
                <div class="field">
                    <label for="invite_code">Código de convite</label>
                    <input type="text" id="invite_code" name="invite_code" placeholder="Cole seu código" autocomplete="off" required />
                </div>
                <div class="field">
                    <label for="email">E-mail</label>
                    <input type="text" name="email" id="email" placeholder="seu@email.com" autocomplete="email" required />
                </div>
                <div class="field">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" placeholder="Crie uma senha forte" autocomplete="new-password" required />
                </div>
                <button class="btn" type="submit">Registrar</button>
            </form>

            <p class="auth-foot">
                <a href="/features/auth/login.php">Já é membro? Entrar</a>
            </p>
        </div>
    </div>
</body>
</html>