<?php
declare(strict_types=1);

/**
 * Definições do perfil — apenas o próprio utilizador (403 para outros).
 */

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';
require_once CLUB61_ROOT . '/config/csrf.php';

$profileUserId = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : '';
if ($profileUserId === '') {
    header('Location: /features/auth/login.php');
    exit;
}

if (isset($_GET['user']) && trim((string) $_GET['user']) !== '' && (string) $_GET['user'] !== $profileUserId) {
    http_response_code(403);
    header('Location: /features/feed/index.php?status=error&message=' . rawurlencode('Acesso negado.'));
    exit;
}

$csrf = csrf_token();
$status = isset($_GET['status']) ? (string) $_GET['status'] : '';
$message = isset($_GET['message']) ? (string) $_GET['message'] : '';

// Defaults
$bio = '';
$ageVal = '';
$cidade = '';
$rel = '';
$avatarUrl = '';
$isPrivate = false;
$messagePermission = 'all';

if (supabase_service_role_available()) {
    $sel = CLUB61_PROFILE_REST_SELECT;
    $url = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . urlencode($profileUserId) . '&select=' . rawurlencode($sel);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge(supabase_service_rest_headers(false), ['Accept: application/json']),
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body !== false && $code >= 200 && $code < 300) {
        $rows = json_decode($body, true);
        if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
            $p = $rows[0];
            $bio = isset($p['bio']) && $p['bio'] !== null ? trim((string) $p['bio']) : '';
            if (isset($p['age']) && $p['age'] !== null && $p['age'] !== '') {
                $ageVal = (string) (int) $p['age'];
            }
            $cidade = isset($p['cidade']) && $p['cidade'] !== null ? trim((string) $p['cidade']) : '';
            $rel = isset($p['relationship_type']) ? strtolower(trim((string) $p['relationship_type'])) : '';
            $avatarUrl = isset($p['avatar_url']) ? trim((string) $p['avatar_url']) : '';
            if (isset($p['is_private'])) {
                $isPrivate = (bool) $p['is_private'];
            }
            if (isset($p['message_permission']) && is_string($p['message_permission'])) {
                $messagePermission = trim($p['message_permission']);
            }
        }
    }
}

$relLower = $rel;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações — Club61</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #0A0A0A; color: #eee; font-family: 'Segoe UI', system-ui, sans-serif; }
        nav { background: #111; border-bottom: 1px solid #222; padding: 0 20px; display: flex; align-items: center; justify-content: space-between; min-height: 56px; position: sticky; top: 0; z-index: 50; }
        .nav-brand { color: #C9A84C; font-weight: 800; letter-spacing: 2px; text-decoration: none; }
        .nav-links { display: flex; gap: 20px; list-style: none; margin: 0; padding: 0; }
        .nav-links a { color: #888; text-decoration: none; font-size: 0.9rem; }
        .nav-links a:hover { color: #C9A84C; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 20px 16px 48px; display: grid; grid-template-columns: 1fr; gap: 24px; }
        @media (min-width: 800px) {
            .wrap { grid-template-columns: 220px 1fr; align-items: start; }
        }
        .side { display: flex; flex-direction: column; gap: 4px; }
        @media (max-width: 799px) {
            .side { flex-direction: row; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; }
        }
        .tab-btn {
            display: block; width: 100%; text-align: left; padding: 12px 14px; border: 1px solid transparent; border-radius: 8px;
            background: transparent; color: #aaa; font-size: 0.95rem; cursor: pointer; text-decoration: none; font-family: inherit;
        }
        .tab-btn:hover, .tab-btn.is-on { color: #C9A84C; border-color: #333; background: #141414; }
        .panel { display: none; }
        .panel.is-on { display: block; }
        .card {
            background: #111; border: 1px solid #222; border-radius: 12px; padding: 22px 20px; margin-bottom: 20px;
        }
        .card h2 { margin: 0 0 16px; font-size: 1rem; color: #C9A84C; font-weight: 700; letter-spacing: 0.04em; }
        label { display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; color: #888; margin-bottom: 6px; }
        input[type="text"], input[type="number"], textarea, select {
            width: 100%; max-width: 480px; padding: 12px 14px; margin-bottom: 14px; border-radius: 8px;
            border: 1px solid #333; background: #1a1a1a; color: #fff; font-size: 0.95rem; font-family: inherit;
        }
        textarea { min-height: 100px; resize: vertical; }
        .btn {
            display: inline-block; padding: 12px 22px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer;
            background: #7B2EFF; color: #fff; font-size: 0.95rem; font-family: inherit;
        }
        .btn:hover { filter: brightness(1.08); }
        .btn-outline { background: transparent; border: 1px solid #444; color: #ccc; }
        .btn-outline:hover { border-color: #C9A84C; color: #C9A84C; }
        .btn-danger { background: #3a1515; color: #ff6b6b; border: 1px solid #662222; }
        .avatar-preview { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 2px solid #333; background: #1a1a1a; display: block; margin-bottom: 14px; }
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 0.9rem; }
        .alert.ok { background: rgba(47, 158, 68, 0.1); border: 1px solid rgba(47, 158, 68, 0.35); color: #69db7c; }
        .alert.err { background: rgba(255, 107, 107, 0.1); border: 1px solid rgba(255, 107, 107, 0.35); color: #ff6b6b; }
        .toggle-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; padding: 12px 0; border-bottom: 1px solid #222; }
        .toggle-row:last-of-type { border-bottom: none; }
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 200; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.is-open { display: flex; }
        .modal-box { background: #161616; border: 1px solid #333; border-radius: 12px; padding: 24px; max-width: 400px; width: 100%; }
        .modal-box h3 { margin: 0 0 12px; color: #C9A84C; font-size: 1rem; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; flex-wrap: wrap; }
    </style>
</head>
<body>
    <nav>
        <a class="nav-brand" href="/features/feed/index.php">Club61</a>
        <ul class="nav-links">
            <li><a href="/features/feed/index.php">Feed</a></li>
            <li><a href="/features/profile/index.php">Perfil</a></li>
            <li><a href="/features/auth/logout.php">Sair</a></li>
        </ul>
    </nav>

    <div class="wrap">
        <aside class="side">
            <button type="button" class="tab-btn is-on" data-tab="info">Informações</button>
            <button type="button" class="tab-btn" data-tab="foto">Foto</button>
            <button type="button" class="tab-btn" data-tab="priv">Privacidade</button>
            <button type="button" class="tab-btn" data-tab="conta">Conta</button>
        </aside>
        <div>
            <?php if ($status === 'ok' && $message !== ''): ?>
                <div class="alert ok"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php elseif ($status === 'error' && $message !== ''): ?>
                <div class="alert err"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <section id="panel-info" class="panel is-on">
                <div class="card">
                    <h2>Informações do perfil</h2>
                    <form action="/features/profile/update_profile.php" method="post" autocomplete="on">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="return_to" value="/features/profile/settings.php">
                        <label for="bio-s">Bio</label>
                        <textarea id="bio-s" name="bio" maxlength="2000" placeholder="Uma linha sobre você"><?= htmlspecialchars($bio, ENT_QUOTES, 'UTF-8') ?></textarea>
                        <label for="age-s">Idade</label>
                        <input id="age-s" type="number" name="age" min="18" max="120" placeholder="Ex.: 32" value="<?= htmlspecialchars($ageVal, ENT_QUOTES, 'UTF-8') ?>">
                        <label for="cid-s">Cidade</label>
                        <input id="cid-s" type="text" name="cidade" maxlength="120" value="<?= htmlspecialchars($cidade, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ex.: São Paulo">
                        <label for="rel-s">Relacionamento</label>
                        <select id="rel-s" name="relationship_type" required>
                            <option value="" disabled<?= $relLower === '' ? ' selected' : '' ?>>Selecione…</option>
                            <option value="solteiro"<?= $relLower === 'solteiro' || $relLower === 'single' ? ' selected' : '' ?>>Solteiro</option>
                            <option value="namorando"<?= $relLower === 'namorando' ? ' selected' : '' ?>>Namorando</option>
                            <option value="casado"<?= $relLower === 'casado' ? ' selected' : '' ?>>Casado</option>
                            <option value="prefiro_nao_dizer"<?= $relLower === 'prefiro_nao_dizer' ? ' selected' : '' ?>>Prefiro não dizer</option>
                            <option value="solteira"<?= $relLower === 'solteira' ? ' selected' : '' ?>>Solteira</option>
                            <option value="casal"<?= $relLower === 'casal' || $relLower === 'couple' ? ' selected' : '' ?>>Casal</option>
                            <option value="casada"<?= $relLower === 'casada' ? ' selected' : '' ?>>Casada</option>
                        </select>
                        <button type="submit" class="btn">Salvar</button>
                    </form>
                </div>
            </section>

            <section id="panel-foto" class="panel">
                <div class="card" id="foto">
                    <h2>Foto de perfil</h2>
                    <?php if ($avatarUrl !== ''): ?>
                        <img class="avatar-preview" src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                    <?php else: ?>
                        <div class="avatar-preview" style="display:flex;align-items:center;justify-content:center;color:#7B2EFF;font-size:2.5rem;">&#128100;</div>
                    <?php endif; ?>
                    <form action="/features/profile/upload_avatar.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="return_to" value="/features/profile/settings.php">
                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required>
                        <p style="margin-top:14px"><button type="submit" class="btn">Enviar nova foto</button></p>
                    </form>
                </div>
            </section>

            <section id="panel-priv" class="panel">
                <div class="card">
                    <h2>Privacidade</h2>
                    <form action="/features/profile/update_privacy.php" method="post">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="toggle-row">
                            <span>Perfil privado</span>
                            <label><input type="checkbox" name="is_private" value="1"<?= $isPrivate ? ' checked' : '' ?>> Sim</label>
                        </div>
                        <label for="mp-s">Aceitar mensagens de</label>
                        <select id="mp-s" name="message_permission">
                            <option value="all"<?= $messagePermission === 'all' ? ' selected' : '' ?>>Todos</option>
                            <option value="following_only"<?= $messagePermission === 'following_only' ? ' selected' : '' ?>>Só quem sigo</option>
                            <option value="none"<?= $messagePermission === 'none' ? ' selected' : '' ?>>Ninguém</option>
                        </select>
                        <p style="margin-top:16px"><button type="submit" class="btn">Salvar privacidade</button></p>
                    </form>
                </div>
            </section>

            <section id="panel-conta" class="panel">
                <div class="card">
                    <h2>Conta</h2>
                    <p style="color:#888;font-size:0.9rem;margin:0 0 16px;">Sessão e dados da conta.</p>
                    <p><a class="btn btn-outline" href="/features/auth/logout.php">Sair</a></p>
                    <p style="margin-top:20px">
                        <button type="button" class="btn btn-danger" id="btn-del-account">Excluir conta</button>
                    </p>
                </div>
            </section>
        </div>
    </div>

    <div id="modal-del" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="md-title">
        <div class="modal-box">
            <h3 id="md-title">Excluir conta</h3>
            <p style="color:#aaa;font-size:0.9rem;line-height:1.5;">Esta ação será irreversível. Por enquanto é apenas uma confirmação de interface — a exclusão real ainda não está implementada.</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" id="modal-del-cancel">Cancelar</button>
                <button type="button" class="btn btn-danger" id="modal-del-ok">Entendi</button>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var tabs = document.querySelectorAll('.tab-btn');
        var panels = {
            info: document.getElementById('panel-info'),
            foto: document.getElementById('panel-foto'),
            priv: document.getElementById('panel-priv'),
            conta: document.getElementById('panel-conta')
        };
        function show(tab) {
            tabs.forEach(function (b) {
                b.classList.toggle('is-on', b.getAttribute('data-tab') === tab);
            });
            Object.keys(panels).forEach(function (k) {
                if (panels[k]) panels[k].classList.toggle('is-on', k === tab);
            });
        }
        tabs.forEach(function (b) {
            b.addEventListener('click', function () { show(b.getAttribute('data-tab')); });
        });
        var md = document.getElementById('modal-del');
        document.getElementById('btn-del-account').addEventListener('click', function () { md.classList.add('is-open'); });
        document.getElementById('modal-del-cancel').addEventListener('click', function () { md.classList.remove('is-open'); });
        document.getElementById('modal-del-ok').addEventListener('click', function () { md.classList.remove('is-open'); });
        if (window.location.hash === '#foto') show('foto');
    })();
    </script>
</body>
</html>
