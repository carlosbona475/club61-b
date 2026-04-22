<?php
declare(strict_types=1);



require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/config/security_headers.php';
require_once CLUB61_ROOT . '/config/session.php';
require_once CLUB61_ROOT . '/config/rate_limit.php';
require_once CLUB61_ROOT . '/config/validation.php';
require_once CLUB61_ROOT . '/services/auth_service.php';
require_once CLUB61_ROOT . '/config/csrf.php';

club61_security_headers();
club61_session_start_safe();

$error = '';

$lock = club61_login_rate_is_locked();
if ($lock['locked']) {
    $m = max(1, (int) ceil($lock['retry_after'] / 60));
    $error = 'Muitas tentativas falhadas. Tente novamente em cerca de ' . $m . ' minuto(s).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $rl = club61_rate_limit_consume('login_post', 20, 600);
    if (!$rl['allowed']) {
        $error = 'Muitas tentativas. Aguarde alguns minutos antes de tentar de novo.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $error = 'Sessão expirada. Atualize a página e tente novamente.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $v = club61_validate($_POST, [
        'email' => 'required|email|max:320',
        'password' => 'required',
    ]);
    if (!$v['ok']) {
        $error = $v['errors'][0] ?? 'Dados inválidos.';
    } else {
        $email = $v['data']['email'];
        $password = $_POST['password'] ?? '';
        $password = is_string($password) ? $password : '';
        if (strlen($password) > 128) {
            $error = 'Dados inválidos.';
        }

        if ($error === '' && !function_exists('curl_init')) {
            $error = 'ERRO: cURL não está disponível neste servidor!';
        } elseif ($error === '') {
            $login = loginUser($email, $password);
            if ($login['success']) {
                if (!isset($login['access_token']) || !is_string($login['access_token']) || $login['access_token'] === '') {
                    die('Login failed: access_token missing');
                }
                if (substr_count($login['access_token'], '.') !== 2) {
                    die('Login failed: access_token is not a valid JWT');
                }
                if (!isset($login['user_id']) || $login['user_id'] === '') {
                    die('Login failed: user id missing');
                }
                session_regenerate_id(true);
                csrf_rotate();
                $_SESSION['access_token'] = $login['access_token'];
                $_SESSION['user_id'] = (string) $login['user_id'];
                $_SESSION['role'] = isset($login['role']) && is_string($login['role']) ? $login['role'] : 'member';
                require_once CLUB61_ROOT . '/config/profile_helper.php';
                ensureUserProfile($_SESSION['user_id'], $email);
                admin_invalidate_profile_cache();
                club61_login_rate_reset();
                header('Location: /features/feed/index.php');
                exit;
            }
            $fail = club61_login_rate_record_failure(8, 900);
            if ($fail['blocked'] || club61_login_rate_is_locked()['locked']) {
                $error = 'Muitas tentativas falhadas. Aguarde 15 minutos ou atualize após o tempo indicado.';
            } else {
                $error = login_failure_user_message($login);
            }
        }
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
        .cookie-banner {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 999;
            background: #111; border-top: 1px solid #222;
            padding: 14px 20px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; flex-wrap: wrap;
            font-size: 0.8125rem; color: #888;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .cookie-banner.hide {
            opacity: 0; transform: translateY(100%); pointer-events: none;
        }
        .cookie-banner a { color: #C9A84C; text-decoration: none; }
        .cookie-banner a:hover { text-decoration: underline; }
        .cookie-btn {
            flex-shrink: 0;
            padding: 8px 20px;
            background: #C9A84C; color: #000;
            border: none; border-radius: 4px;
            font-size: 0.8125rem; font-weight: 600;
            cursor: pointer; transition: opacity 0.15s;
        }
        .cookie-btn:hover { opacity: 0.85; }
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

            <form class="auth-form" method="POST" autocomplete="on">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" placeholder="seu@email.com" autocomplete="email" maxlength="320" required>
                </div>
                <div class="field">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" maxlength="128" required>
                </div>
                <button class="btn" type="submit">Entrar</button>
            </form>

            <p class="auth-foot">
                <a href="/features/auth/register.php">Não tem conta? Solicite cadastro com convite</a>
            </p>
        </div>
    </div>

    <div class="cookie-banner" id="cookieBanner">
        <span>
            Usamos cookies essenciais para manter sua sessão e segurança do site.
            <a href="#" tabindex="0">Saiba mais</a>
        </span>
        <button class="cookie-btn" id="cookieAccept">Aceitar</button>
    </div>
    <script>
    (function () {
        var banner = document.getElementById('cookieBanner');
        var btn = document.getElementById('cookieAccept');
        if (!banner) return;
        try {
            if (localStorage.getItem('club61_cookies_ok') === '1') {
                banner.style.display = 'none';
                return;
            }
        } catch (e) {}
        btn.addEventListener('click', function () {
            banner.classList.add('hide');
            try { localStorage.setItem('club61_cookies_ok', '1'); } catch (e) {}
            setTimeout(function () { banner.style.display = 'none'; }, 320);
        });
    })();
    </script>
</body>
</html>
