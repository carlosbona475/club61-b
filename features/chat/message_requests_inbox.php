<?php

/**
 * Pedidos de mensagem recebidos (pending).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/csrf.php';
require_once CLUB61_ROOT . '/config/feed_interactions.php';
require_once CLUB61_ROOT . '/config/message_requests.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';

$uid = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
if ($uid === '' || !mr_service_available()) {
    header('Location: /features/auth/login.php');

    exit;
}

$url = SUPABASE_URL . '/rest/v1/message_requests?to_user=eq.' . urlencode($uid)
    . '&status=eq.pending&select=id,from_user,created_at&order=created_at.desc';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Accept: application/json',
    ],
]);
$raw = curl_exec($ch);
curl_close($ch);
$rows = json_decode((string) $raw, true);
$pending = is_array($rows) ? $rows : [];

$fromIds = [];
foreach ($pending as $r) {
    if (!empty($r['from_user'])) {
        $fromIds[] = (string) $r['from_user'];
    }
}
$profiles = feed_fetch_profiles_by_ids($fromIds);
$csrf = csrf_token();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pedidos de mensagem — Club61</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:#0A0A0A;color:#fff;font-family:'Segoe UI',system-ui,sans-serif;padding:16px;padding-bottom:48px;max-width:520px;margin:0 auto}
a{color:#C9A84C}
.top{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
h1{font-size:1.1rem;font-weight:700;color:#C9A84C}
.row{
  display:flex;align-items:center;justify-content:space-between;gap:12px;
  padding:14px;border:1px solid #222;border-radius:10px;background:#111;margin-bottom:10px;flex-wrap:wrap
}
.name{font-weight:600;font-size:0.95rem}
.meta{font-size:0.75rem;color:#666;margin-top:4px}
.actions{display:flex;gap:8px}
.btn{
  padding:8px 14px;border-radius:8px;border:none;font-size:0.85rem;font-weight:600;cursor:pointer;font-family:inherit
}
.btn-ok{background:#1a3a1f;color:#69db7c;border:1px solid rgba(105,219,124,0.25)}
.btn-no{background:#2a1a1a;color:#ff6b6b;border:1px solid rgba(255,107,107,0.25)}
.empty{color:#666;text-align:center;padding:40px 16px;font-size:0.9rem}
.flash.ok{color:#69db7c;margin-bottom:12px;font-size:0.88rem}
.flash.err{color:#ff6b6b;margin-bottom:12px;font-size:0.88rem}
</style>
</head>
<body>
<div class="top">
  <h1>Pedidos de mensagem</h1>
  <a href="/features/chat/inbox.php">← Inbox</a>
</div>
<?php if (isset($_GET['ok'])): ?><p class="flash ok">Atualizado.</p><?php endif; ?>
<?php if (isset($_GET['err'])): ?><p class="flash err">Não foi possível concluir.</p><?php endif; ?>

<?php if ($pending === []): ?>
  <p class="empty">Nenhum pedido pendente.</p>
<?php else: ?>
  <?php foreach ($pending as $r): ?>
    <?php

    $fid = isset($r['from_user']) ? (string) $r['from_user'] : '';
    $pr = $profiles[$fid] ?? [];
    $label = club61_display_id_label(isset($pr['display_id']) ? (string) $pr['display_id'] : null);
    $rid = isset($r['id']) ? (string) $r['id'] : '';
    ?>
    <div class="row">
      <div>
        <div class="name"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="meta">Quer conversar com você</div>
      </div>
      <div class="actions">
        <form action="/features/chat/message_request_action.php" method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="request_id" value="<?= htmlspecialchars($rid, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="accept">
          <button class="btn btn-ok" type="submit">Aceitar</button>
        </form>
        <form action="/features/chat/message_request_action.php" method="post">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="request_id" value="<?= htmlspecialchars($rid, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="reject">
          <button class="btn btn-no" type="submit">Recusar</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
