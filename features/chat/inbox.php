<?php
require_once dirname(__DIR__, 2) . '/auth_guard.php';
require_once dirname(__DIR__, 2) . '/config/supabase.php';
require_once dirname(__DIR__, 2) . '/config/profile_helper.php';
require_once dirname(__DIR__, 2) . '/config/online.php';

$current_user_id = trim((string) ($_SESSION['user_id'] ?? ''));
$access_token = trim((string) ($_SESSION['access_token'] ?? ''));

if ($current_user_id === '' || $access_token === '' || !defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    header('Location: /features/auth/login.php');
    exit;
}

function chat_inbox_headers(bool $json = false): array
{
    $h = [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Accept: application/json',
    ];
    if ($json) {
        $h[] = 'Content-Type: application/json';
    }

    return $h;
}

function clLabelInbox(array $author): string
{
    $disp = isset($author['display_id']) ? trim((string) $author['display_id']) : '';
    $uname = isset($author['username']) ? trim((string) $author['username']) : '';
    if ($disp !== '') {
        $num = null;
        if (preg_match('/^CL\s*0*(\d+)$/i', $disp, $m)) {
            $num = (int) $m[1];
        } else {
            $digits = preg_replace('/\D/', '', $disp);
            if ($digits !== '') {
                $num = (int) $digits;
            }
        }
        if ($num !== null && $num > 0) {
            return 'CL' . str_pad((string) min(999, $num), 2, '0', STR_PAD_LEFT);
        }
    }
    if ($uname !== '') {
        return '@' . $uname;
    }

    return 'Membro';
}

function timeAgo(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '';
    }
    try {
        $t = new DateTimeImmutable($iso);
        $now = new DateTimeImmutable('now');
        $diff = $now->getTimestamp() - $t->getTimestamp();
        if ($diff < 45) {
            return 'agora';
        }
        if ($diff < 3600) {
            return max(1, (int) floor($diff / 60)) . 'min';
        }
        if ($diff < 86400) {
            return max(1, (int) floor($diff / 3600)) . 'h';
        }
        if ($diff < 86400 * 7) {
            return max(1, (int) floor($diff / 86400)) . 'd';
        }

        return $t->format('d/m');
    } catch (Exception $e) {
        return '';
    }
}

$orFilter = '(sender_id.eq.' . $current_user_id . ',receiver_id.eq.' . $current_user_id . ')';
$dmUrl = SUPABASE_URL . '/rest/v1/direct_messages?or=' . rawurlencode($orFilter)
    . '&select=id,sender_id,receiver_id,content,created_at,read_at&order=created_at.desc&limit=200';

$ch = curl_init($dmUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => chat_inbox_headers(false),
    CURLOPT_HTTPGET => true,
]);
$rawDm = curl_exec($ch);
$codeDm = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$dmRows = [];
if ($rawDm !== false && $codeDm >= 200 && $codeDm < 300) {
    $decoded = json_decode($rawDm, true);
    if (is_array($decoded)) {
        $dmRows = $decoded;
    }
}

$convos = [];
foreach ($dmRows as $msg) {
    $sid = isset($msg['sender_id']) ? (string) $msg['sender_id'] : '';
    $rid = isset($msg['receiver_id']) ? (string) $msg['receiver_id'] : '';
    if ($sid === '' || $rid === '') {
        continue;
    }
    $other = ($sid === $current_user_id) ? $rid : $sid;
    $content = isset($msg['content']) ? (string) $msg['content'] : '';
    $created = isset($msg['created_at']) ? (string) $msg['created_at'] : '';
    $readAt = isset($msg['read_at']) ? $msg['read_at'] : null;

    if (!isset($convos[$other])) {
        $convos[$other] = [
            'other_id' => $other,
            'last_msg' => $content,
            'last_time' => $created,
            'unread' => 0,
        ];
    }
    if ($rid === $current_user_id && $sid === $other && ($readAt === null || $readAt === '')) {
        $convos[$other]['unread']++;
    }
}

$convosList = array_values($convos);
usort($convosList, function ($a, $b) {
    $ta = strtotime($a['last_time'] ?? '0') ?: 0;
    $tb = strtotime($b['last_time'] ?? '0') ?: 0;

    return $tb <=> $ta;
});

$otherIds = array_column($convosList, 'other_id');
$profilesById = [];
if ($otherIds !== []) {
    $inList = implode(',', $otherIds);
    $pUrl = SUPABASE_URL . '/rest/v1/profiles?select=id,display_id,username,avatar_url,last_seen&id=in.(' . $inList . ')';
    $chP = curl_init($pUrl);
    curl_setopt_array($chP, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => chat_inbox_headers(false),
        CURLOPT_HTTPGET => true,
    ]);
    $rawP = curl_exec($chP);
    $codeP = curl_getinfo($chP, CURLINFO_HTTP_CODE);
    curl_close($chP);
    if ($rawP !== false && $codeP >= 200 && $codeP < 300) {
        $rows = json_decode($rawP, true);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (isset($row['id'])) {
                    $profilesById[(string) $row['id']] = $row;
                }
            }
        }
    }
}

$allMembers = [];
$mUrl = SUPABASE_URL . '/rest/v1/profiles?select=id,display_id,username,avatar_url,last_seen&order=display_id.asc&limit=100';
$chM = curl_init($mUrl);
curl_setopt_array($chM, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => chat_inbox_headers(false),
    CURLOPT_HTTPGET => true,
]);
$rawM = curl_exec($chM);
$codeM = curl_getinfo($chM, CURLINFO_HTTP_CODE);
curl_close($chM);
if ($rawM !== false && $codeM >= 200 && $codeM < 300) {
    $decodedM = json_decode($rawM, true);
    if (is_array($decodedM)) {
        foreach ($decodedM as $row) {
            if (isset($row['id']) && (string) $row['id'] !== $current_user_id) {
                $allMembers[] = $row;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Mensagens — Club61</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{
  background:#0A0A0A;color:#fff;font-family:'Segoe UI',system-ui,sans-serif;
  max-width:480px;margin:0 auto;min-height:100dvh;
  padding-bottom:calc(56px + env(safe-area-inset-bottom,0px));
}
.in-top{
  display:flex;align-items:center;justify-content:space-between;padding:10px 12px;
  background:#0A0A0A;border-bottom:1px solid #1a1a1a;position:sticky;top:0;z-index:50;
}
.in-top a{color:#aaa;text-decoration:none;font-size:1.2rem}
.in-top h1{font-size:1rem;color:#C9A84C;font-weight:700}
.tabs{display:flex;border-bottom:1px solid #1a1a1a}
.tabs a{
  flex:1;text-align:center;padding:12px;font-size:0.85rem;color:#888;text-decoration:none;border-bottom:2px solid transparent;
}
.tabs a.active{color:#7B2EFF;border-color:#7B2EFF;font-weight:600}
.search-wrap{padding:10px 12px;background:#111}
.search-wrap input{
  width:100%;background:#1a1a1a;border:1px solid #222;border-radius:10px;color:#fff;padding:10px 12px;font-size:0.9rem;outline:none;
}
.search-wrap input:focus{border-color:#7B2EFF}
.conv-list{padding:0 0 8px}
.conv-item{
  display:flex;align-items:center;gap:12px;padding:12px 14px;border-bottom:1px solid #141414;text-decoration:none;color:inherit;
}
.conv-item:hover{background:rgba(255,255,255,0.03)}
.conv-av{width:50px;height:50px;border-radius:50%;object-fit:cover;background:#111;border:1px solid #222;flex-shrink:0}
.avatar-wrapper{position:relative;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
.online-dot{position:absolute;bottom:2px;right:2px;width:10px;height:10px;background:#00ff88;border-radius:50%;border:2px solid #111}
.conv-body{flex:1;min-width:0}
.conv-name{font-weight:600;color:#C9A84C;font-size:0.92rem}
.conv-preview{font-size:0.8rem;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:4px}
.conv-meta{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0}
.conv-time{font-size:0.68rem;color:#555}
.conv-badge{min-width:20px;height:20px;padding:0 6px;border-radius:10px;background:#7B2EFF;color:#fff;font-size:0.72rem;font-weight:700;display:flex;align-items:center;justify-content:center}
.empty-inbox{text-align:center;padding:48px 24px;color:#555}
.empty-inbox .ico{font-size:2.5rem;margin-bottom:12px}
.modal-backdrop{
  display:none;position:fixed;inset:0;z-index:400;background:rgba(0,0,0,0.65);
  align-items:flex-end;justify-content:center;
}
.modal-backdrop.is-open{display:flex}
.modal-sheet{
  width:100%;max-width:480px;max-height:85vh;overflow:auto;background:#111;border:1px solid #222;
  border-radius:16px 16px 0 0;padding:12px 0 24px;
  transform:translateY(100%);transition:transform .3s ease;
}
.modal-backdrop.is-open .modal-sheet{transform:translateY(0)}
.modal-handle{width:40px;height:4px;background:#444;border-radius:4px;margin:4px auto 12px}
.modal-title{text-align:center;color:#C9A84C;font-weight:700;margin-bottom:8px}
.member-row{
  display:flex;align-items:center;gap:12px;padding:12px 16px;text-decoration:none;color:#fff;border-bottom:1px solid #1a1a1a;
}
.member-row:hover{background:#1a1a1a}
.member-av{width:44px;height:44px;border-radius:50%;object-fit:cover;background:#1a1a1a}
.bottomnav{
  position:fixed;left:0;right:0;bottom:0;z-index:300;
  display:flex;align-items:center;justify-content:space-around;
  height:56px;padding:0 4px;padding-bottom:env(safe-area-inset-bottom,0px);
  background:#0A0A0A;border-top:1px solid #1a1a1a;max-width:480px;margin:0 auto;
}
.bottomnav a{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  text-decoration:none;color:#888;font-size:0.58rem;gap:2px;padding:6px 4px;
}
.bottomnav a span:first-child{font-size:1.05rem;line-height:1}
.bottomnav a.is-active{color:#7B2EFF}
</style>
</head>
<body>

<header class="in-top">
  <a href="/features/chat/general.php" aria-label="Voltar">←</a>
  <h1>Mensagens</h1>
  <button type="button" id="btnNewDm" style="background:none;border:none;color:#C9A84C;font-size:1.3rem;cursor:pointer" aria-label="Nova conversa">✏️</button>
</header>

<div class="tabs">
  <a href="/features/chat/general.php">💬 Chat Geral</a>
  <a class="active" href="/features/chat/inbox.php">✉️ Privado</a>
</div>
<p style="text-align:right;padding:0 12px 10px;font-size:0.75rem"><a href="/features/chat/message_requests_inbox.php" style="color:#C9A84C;text-decoration:none">Pedidos de mensagem →</a></p>

<div class="search-wrap">
  <input type="search" id="convSearch" placeholder="Buscar conversas..." autocomplete="off" aria-label="Buscar">
</div>

<div class="conv-list" id="convList">
<?php if (empty($convosList)): ?>
  <div class="empty-inbox">
    <div class="ico" aria-hidden="true">✉️</div>
    <p>Nenhuma conversa ainda. Toque em ✏️ para começar.</p>
  </div>
<?php else: ?>
  <?php foreach ($convosList as $c):
      $oid = $c['other_id'];
      $prof = isset($profilesById[$oid]) ? $profilesById[$oid] : [];
      $name = $prof !== [] ? clLabelInbox($prof) : 'Membro';
      $av = $prof !== [] && !empty($prof['avatar_url']) ? trim((string) $prof['avatar_url']) : '';
      $lastSeen = $prof !== [] && isset($prof['last_seen']) ? (string) $prof['last_seen'] : null;
      $isOnline = isUserOnline($lastSeen);
      $lm = $c['last_msg'];
      $preview = strlen($lm) > 80 ? substr($lm, 0, 77) . '…' : $lm;
      $ta = timeAgo($c['last_time']);
      $unread = (int) ($c['unread'] ?? 0);
      ?>
  <a class="conv-item" href="/features/chat/dm.php?with=<?= urlencode($oid) ?>" data-name="<?= htmlspecialchars(strtolower($name), ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($av !== ''): ?>
    <span class="avatar-wrapper"><img class="conv-av" src="<?= htmlspecialchars($av, ENT_QUOTES, 'UTF-8') ?>" alt=""><?php if ($isOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?></span>
    <?php else: ?>
    <span class="conv-av avatar-wrapper" style="display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#7B2EFF">👤<?php if ($isOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?></span>
    <?php endif; ?>
    <div class="conv-body">
      <div class="conv-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></div>
      <div class="conv-preview"><?= htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <div class="conv-meta">
      <span class="conv-time"><?= htmlspecialchars($ta, ENT_QUOTES, 'UTF-8') ?></span>
      <?php if ($unread > 0): ?>
      <span class="conv-badge"><?= (int) min(99, $unread) ?></span>
      <?php endif; ?>
    </div>
  </a>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<div id="newDmModal" class="modal-backdrop" aria-hidden="true">
  <div class="modal-sheet" role="dialog" aria-labelledby="mdT" onclick="event.stopPropagation()">
    <div class="modal-handle"></div>
    <div id="mdT" class="modal-title">Nova mensagem</div>
    <?php foreach ($allMembers as $mem):
        $mid = (string) $mem['id'];
        $nm = clLabelInbox($mem);
        $mav = !empty($mem['avatar_url']) ? trim((string) $mem['avatar_url']) : '';
        $mLastSeen = isset($mem['last_seen']) ? (string) $mem['last_seen'] : null;
        $mOnline = isUserOnline($mLastSeen);
        ?>
    <a class="member-row" href="/features/chat/dm.php?with=<?= urlencode($mid) ?>">
      <?php if ($mav !== ''): ?>
      <span class="avatar-wrapper"><img class="member-av" src="<?= htmlspecialchars($mav, ENT_QUOTES, 'UTF-8') ?>" alt=""><?php if ($mOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?></span>
      <?php else: ?>
      <span class="member-av avatar-wrapper" style="display:flex;align-items:center;justify-content:center;color:#7B2EFF">👤<?php if ($mOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?></span>
      <?php endif; ?>
      <span><?= htmlspecialchars($nm, ENT_QUOTES, 'UTF-8') ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<nav class="bottomnav" aria-label="Navegação">
  <a href="/features/feed/index.php"><span>🏠</span>Feed</a>
  <a href="/features/profile/upload_story.php"><span>📷</span>Story</a>
  <a class="is-active" href="/features/chat/general.php"><span>💬</span>Chat</a>
  <a href="/features/profile/index.php"><span>👤</span>Perfil</a>
  <a href="/features/auth/logout.php"><span>🚪</span>Sair</a>
</nav>

<script>
(function(){
  var modal = document.getElementById('newDmModal');
  var btn = document.getElementById('btnNewDm');
  if (btn && modal) {
    btn.addEventListener('click', function(){ modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); });
    modal.addEventListener('click', function(){ modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); });
  }
  var search = document.getElementById('convSearch');
  if (search) {
    search.addEventListener('input', function(){
      var q = this.value.trim().toLowerCase();
      document.querySelectorAll('.conv-item').forEach(function(el){
        var n = el.getAttribute('data-name') || '';
        el.style.display = (!q || n.indexOf(q) !== -1) ? '' : 'none';
      });
    });
  }
})();
</script>
</body>
</html>
