<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/config/security_headers.php';
require_once CLUB61_ROOT . '/config/session.php';
require_once CLUB61_ROOT . '/config/rate_limit.php';
require_once CLUB61_ROOT . '/config/validation.php';
require_once CLUB61_ROOT . '/services/auth_service.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/csrf.php';

/**
 * Normaliza o código de convite para bater em public.invites.code (formato antigo CL-… e hex novo).
 * Ordem: trim → remove espaços → remove prefixo CL- (case-insensitive) → remove hífens → minúsculas (hex na BD).
 *
 * @return array{code: string, cl_prefix_removed: bool}
 */
function club61_normalize_invite_code_for_lookup(string $raw): array
{
    // 1) remove espaços e trim
    $s = preg_replace('/\s+/', '', trim($raw));

    $clPrefixRemoved = false;

    // 2) remove prefixo CL- (case-insensitive)
    if (preg_match('/^CL-/i', $s)) {
        $s = preg_replace('/^CL-/i', '', $s);
        $clPrefixRemoved = true;
    }

    // 3) remove hífens (ex: XXXX-YYYY -> XXXXXXXX)
    $s = str_replace('-', '', $s);

    // 4) NORMALIZA PARA MINÚSCULO (convites gravados em hex minúsculo)
    $s = strtolower($s);

    return [
        'code' => $s,
        'cl_prefix_removed' => $clPrefixRemoved,
    ];
}

club61_security_headers();
club61_session_start_safe();

$errorMessage = '';
$email = '';
$password = '';
$invite_code = '';
$invite_id = null;
$invite_code_norm = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rl = club61_rate_limit_consume('register_post', 8, 3600);
    if (!$rl['allowed']) {
        $errorMessage = 'Muitas tentativas de cadastro a partir deste IP. Tente mais tarde.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage === '') {
    if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errorMessage = 'Sessão expirada. Recarregue a página.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage === '') {
    $v = club61_validate($_POST, [
        'invite_code' => 'required',
        'email' => 'required|email|max:320',
        'password' => 'required|min:8',
    ]);

    if (!$v['ok']) {
        $errorMessage = $v['errors'][0] ?? 'Dados inválidos.';
    } else {
        $raw = $_POST['invite_code'] ?? '';
        $raw = is_string($raw) ? $raw : '';

        $ok = preg_match('/^(?:[A-Fa-f0-9]+|CL-[A-Fa-f0-9]+(?:-[A-Fa-f0-9]+)*)$/', $raw) === 1;

        if (!$ok) {
            $errorMessage = 'Convite inválido, já utilizado ou expirado.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage === '') {
    $invite_code = $_POST['invite_code'] ?? '';
    $invite_code = is_string($invite_code) ? $invite_code : '';

    $email = $v['data']['email'];

    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';

    if (strlen($password) > 128) {
        $errorMessage = 'Senha muito longa.';
    }
}

$invite_id = null;
$invite_code_norm = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage === '') {
    if (!defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
        $errorMessage = 'Servico de convites indisponivel (SUPABASE_SERVICE_KEY).';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage === '') {
    $normPack = club61_normalize_invite_code_for_lookup((string) $invite_code);
    $invite_code_norm = $normPack['code'];

    if ($invite_code_norm === '') {
        $errorMessage = 'Convite inválido, já utilizado ou expirado.';
    } else {
        error_log(
            '[club61-register] invite_lookup len=' . (string) strlen($invite_code_norm)
            . ' cl_prefix_removed=' . ($normPack['cl_prefix_removed'] ? '1' : '0')
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage === '') {
    $nowIso = gmdate('Y-m-d\TH:i:s\Z');

    // Aceita convites sem expiração (expires_at null) OU com expiração futura
    $q = '/rest/v1/invites?code=eq.' . rawurlencode($invite_code_norm)
        . '&used_by=is.null'
        . '&or=(expires_at.is.null,expires_at.gt.' . rawurlencode($nowIso) . ')';

    $url = SUPABASE_URL . $q;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Accept: application/json',
    ]);

    $response = curl_exec($ch);
    $httpInv = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $invite = [];
    if ($response !== false && $httpInv >= 200 && $httpInv < 300) {
        $decoded = json_decode((string) $response, true);
        $invite = is_array($decoded) ? $decoded : [];
    }

    if ($invite === []) {
        $errorMessage = 'Convite inválido, já utilizado ou expirado.';
    } else {
        $invite_id = isset($invite[0]['id']) ? $invite[0]['id'] : null;
        if ($invite_id === null || $invite_id === '') {
            $errorMessage = 'Convite inválido, já utilizado ou expirado.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage === '') {
    $result = registerUser($email, $password, $invite_code_norm);

    if ($result['success']) {
        $newUserId = isset($result['user_id']) ? trim((string) $result['user_id']) : '';

        if ($newUserId === '') {
            $errorMessage = 'Conta criada, mas não foi possível obter o ID do usuário. Contacte o suporte.';
        } elseif ($invite_id !== null) {
            $usedAt = gmdate('Y-m-d\TH:i:s\Z');
            $patchBody = [
                'used_by' => $newUserId,
                'used_at' => $usedAt,
            ];

            if (isset($invite[0]['status'])) {
                $patchBody['status'] = 'used';
            }

            $patchUrl = SUPABASE_URL . '/rest/v1/invites?id=eq.' . rawurlencode((string) $invite_id);

            $chUpd = curl_init($patchUrl);
            curl_setopt_array($chUpd, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_POSTFIELDS => json_encode($patchBody, JSON_UNESCAPED_SLASHES),
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . SUPABASE_SERVICE_KEY,
                    'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
                    'Content-Type: application/json',
                    'Prefer: return=minimal',
                ],
            ]);

            curl_exec($chUpd);
            $patchCode = (int) curl_getinfo($chUpd, CURLINFO_HTTP_CODE);
            curl_close($chUpd);

            if ($patchCode < 200 || $patchCode >= 300) {
                $errorMessage = 'Conta criada, mas o convite não foi marcado como usado. Peça ajuda a um administrador.';
            }
        }

        if ($errorMessage === '') {
            header('Location: /features/auth/login.php');
            exit;
        }
    } else {
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

        <form class="auth-form" action="" method="POST" autocomplete="on">
            <?= csrf_field() ?>
            <div class="field">
                <label for="invite_code">Código de convite</label>
                <input type="text" id="invite_code" name="invite_code" placeholder="Cole seu código aqui" autocomplete="off" required />
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