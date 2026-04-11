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

require_once dirname(__DIR__, 2) . '/config/city_rooms.php';

$sala = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sala = trim((string) ($_POST['sala'] ?? ''));
} else {
    $sala = trim((string) ($_GET['sala'] ?? ''));
}
$roomMeta = club61_city_room_by_slug($sala);
if ($roomMeta === null) {
    header('Location: /features/chat/salas.php');
    exit;
}

function chat_service_headers(bool $json = false): array
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

function clLabel(array $author): string
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content  = trim($_POST['content'] ?? '');
    $media_url  = null;
    $media_type = null;

    // Upload de mídia se houver arquivo
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $file     = $_FILES['media'];
        $allowed  = ['image/jpeg','image/png','image/webp','image/gif','video/mp4','video/webm'];
        $extMap   = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif','video/mp4'=>'mp4','video/webm'=>'webm'];
        $mime     = mime_content_type($file['tmp_name']);
        if (in_array($mime, $allowed, true) && $file['size'] <= 20 * 1024 * 1024) {
            $filename = uniqid('gm_', true) . '.' . $extMap[$mime];
            $binary   = file_get_contents($file['tmp_name']);
            $upUrl    = SUPABASE_URL . '/storage/v1/object/chat-media/' . $filename;
            $chUp = curl_init($upUrl);
            curl_setopt_array($chUp, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $binary,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER     => [
                    'apikey: '         . SUPABASE_SERVICE_KEY,
                    'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
                    'Content-Type: '   . $mime,
                    'x-upsert: true',
                ],
            ]);
            curl_exec($chUp);
            $upCode = curl_getinfo($chUp, CURLINFO_HTTP_CODE);
            curl_close($chUp);
            if ($upCode >= 200 && $upCode < 300) {
                $media_url  = SUPABASE_URL . '/storage/v1/object/public/chat-media/' . $filename;
                $media_type = $mime;
            }
        }
    }

    // Só envia se tiver texto ou mídia
    if ($content !== '' || $media_url !== null) {
        $payload = json_encode([
            'user_id'    => $current_user_id,
            'content'    => $content,
            'media_url'  => $media_url,
            'media_type' => $media_type,
            'sala'       => $sala,
        ], JSON_UNESCAPED_SLASHES);
        $sk = SUPABASE_SERVICE_KEY;
        $ch = curl_init(SUPABASE_URL . '/rest/v1/general_messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => [
                'apikey: '         . $sk,
                'Authorization: Bearer ' . $sk,
                'Content-Type: application/json',
                'Prefer: return=minimal',
            ],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
    header('Location: /features/chat/general.php?' . http_build_query(['sala' => $sala]));
    exit;
}

$messages = [];
$url = SUPABASE_URL . '/rest/v1/general_messages?select=id,user_id,content,media_url,media_type,created_at'
    . '&sala=eq.' . rawurlencode($sala)
    . '&order=created_at.desc&limit=60';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => chat_service_headers(false),
    CURLOPT_HTTPGET => true,
]);
$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($raw !== false && $code >= 200 && $code < 300) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $messages = array_reverse($decoded);
    }
}

$userIds = [];
foreach ($messages as $m) {
    $uid = isset($m['user_id']) ? (string) $m['user_id'] : '';
    if ($uid !== '') {
        $userIds[$uid] = true;
    }
}
$idList = array_keys($userIds);
$profilesById = [];
if ($idList !== []) {
    $inList = implode(',', $idList);
    $pUrl = SUPABASE_URL . '/rest/v1/profiles?select=id,display_id,username,avatar_url,last_seen&id=in.(' . $inList . ')';
    $chP = curl_init($pUrl);
    curl_setopt_array($chP, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => chat_service_headers(false),
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

function gm_date_key(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '';
    }
    try {
        $d = new DateTimeImmutable($iso);

        return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    } catch (Exception $e) {
        return '';
    }
}

function date_divider_label(string $dayKey): string
{
    $today = (new DateTimeImmutable('now'))->format('Y-m-d');
    if ($dayKey === $today) {
        return 'Hoje';
    }
    if ($dayKey === '') {
        return '';
    }
    try {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $dayKey);

        return $d ? $d->format('d/m/Y') : $dayKey;
    } catch (Exception $e) {
        return $dayKey;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= htmlspecialchars($roomMeta['nome'], ENT_QUOTES, 'UTF-8') ?> — Club61</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;height:100dvh}
body{
  background:#0A0A0A;color:#fff;font-family:'Segoe UI',system-ui,sans-serif;
  display:flex;flex-direction:column;align-items:stretch;
  max-width:480px;margin:0 auto;width:100%;min-height:100dvh;
  padding-bottom:calc(56px + env(safe-area-inset-bottom,0px));
}
.ch-main{flex:1;display:flex;flex-direction:column;min-height:0;width:100%}
.ch-top{
  flex-shrink:0;width:100%;display:flex;align-items:center;justify-content:space-between;
  padding:10px 12px;background:#0A0A0A;border-bottom:1px solid #1a1a1a;gap:8px;
}
.ch-top a{color:#aaa;text-decoration:none;font-size:1.2rem;padding:4px}
.ch-top a:hover{color:#C9A84C}
.ch-title-wrap{display:flex;align-items:center;gap:8px;flex:1;justify-content:center}
.ch-title{font-size:1rem;font-weight:700;color:#C9A84C}
.ch-msgs{flex:1;overflow-y:auto;width:100%;padding:18px 14px 14px;display:flex;flex-direction:column;gap:14px;min-height:0}
.date-div{text-align:center;font-size:0.74rem;color:#444;margin:18px 0 14px}
.msg-row{display:flex;gap:12px;max-width:100%;align-items:flex-end}
.msg-row.me{justify-content:flex-end}
.msg-row.them{justify-content:flex-start}
.msg-stack{display:flex;flex-direction:column;align-items:flex-start;max-width:85%;gap:8px}
.msg-row.me .msg-stack{align-items:flex-end}
.msg-av{width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;background:#111;border:1px solid #222}
.msg-av-ph{width:28px;height:28px;min-width:28px;flex-shrink:0;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#111;border:1px solid #222;color:#7B2EFF;text-decoration:none;font-size:0.85rem}
.avatar-wrapper{position:relative;display:inline-flex;align-items:center;justify-content:center}
.online-dot{position:absolute;bottom:2px;right:2px;width:10px;height:10px;background:#00ff88;border-radius:50%;border:2px solid #111}
.msg-col{flex:1;min-width:0;display:flex;flex-direction:column;align-items:flex-start;gap:8px}
.msg-bub{max-width:85%;padding:14px 18px;font-size:0.95rem;line-height:1.55;word-break:break-word;white-space:pre-wrap}
.msg-bub.me{background:#7B2EFF;color:#fff;border-radius:18px 4px 18px 18px}
.msg-bub.them{background:#1e1e1e;color:#ddd;border-radius:4px 18px 18px 18px}
.msg-meta{font-size:0.72rem;color:#C9A84C;margin-bottom:4px;font-weight:600}
.msg-meta a{color:inherit;text-decoration:none}
.ch-foot{
  flex-shrink:0;width:100%;padding:10px 12px;padding-bottom:calc(10px + env(safe-area-inset-bottom,0px));
  background:#0A0A0A;border-top:1px solid #1a1a1a;display:flex;gap:8px;align-items:flex-end;
}
.ch-foot textarea,.ch-foot .chat-input{
  flex:1;background:#111;border:1px solid #222;border-radius:12px;color:#fff;padding:10px 12px;font-size:0.95rem;
  resize:none;max-height:120px;min-height:44px;font-family:inherit;outline:none;
}
.ch-foot textarea:focus,.ch-foot .chat-input:focus{border-color:#7B2EFF}
.ch-send,.btn-send{
  width:44px;height:44px;border-radius:50%;border:none;background:#7B2EFF;color:#fff;font-size:1.1rem;cursor:pointer;
  flex-shrink:0;display:flex;align-items:center;justify-content:center;
}
.ch-send:hover,.btn-send:hover{filter:brightness(1.08)}
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
.bottomnav a:hover{color:#ccc}
.bottomnav a.is-active{color:#7B2EFF}
</style>
</head>
<body>

<header class="ch-top">
  <a href="/features/chat/salas.php" aria-label="Voltar às salas">←</a>
  <div class="ch-title-wrap"><span aria-hidden="true"><?= htmlspecialchars($roomMeta['emoji'], ENT_QUOTES, 'UTF-8') ?></span><span class="ch-title"><?= htmlspecialchars($roomMeta['nome'], ENT_QUOTES, 'UTF-8') ?></span></div>
  <a href="/features/chat/inbox.php" aria-label="Mensagens">✉️</a>
</header>

<div class="ch-main">
<div class="ch-msgs" id="msgScroll">
<?php
$prevDay = null;
$prevAuthor = null;
foreach ($messages as $m):
    $mid = isset($m['user_id']) ? (string) $m['user_id'] : '';
    $isMe = $mid === $current_user_id;
    $created = isset($m['created_at']) ? (string) $m['created_at'] : '';
    $dayKey = gm_date_key($created);
    if ($dayKey !== '' && $dayKey !== $prevDay):
        $prevDay = $dayKey;
        ?>
  <div class="date-div"><?= htmlspecialchars(date_divider_label($dayKey), ENT_QUOTES, 'UTF-8') ?></div>
<?php
    endif;
    $author = ($mid !== '' && isset($profilesById[$mid])) ? $profilesById[$mid] : [];
    $label = $author !== [] ? clLabel($author) : 'Membro';
    $avatar = ($author !== [] && !empty($author['avatar_url'])) ? trim((string) $author['avatar_url']) : '';
    $lastSeen = ($author !== [] && isset($author['last_seen'])) ? (string) $author['last_seen'] : null;
    $isOnline = isUserOnline($lastSeen);
    $profileUrl = '/features/profile/view.php?id=' . rawurlencode($mid);
    $showHeader = !$isMe && ($prevAuthor !== $mid);
    $prevAuthor = $mid;
    $content = isset($m['content']) ? (string) $m['content'] : '';
    ?>
  <div class="msg-row <?= $isMe ? 'me' : 'them' ?>">
    <?php if (!$isMe): ?>
      <?php if ($showHeader): ?>
        <?php if ($avatar !== ''): ?>
    <a class="avatar-wrapper" href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>">
      <img class="msg-av" src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>" alt="">
      <?php if ($isOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?>
    </a>
        <?php else: ?>
    <a class="msg-av-ph avatar-wrapper" href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Perfil">
      <span aria-hidden="true">&#128100;</span>
      <?php if ($isOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?>
    </a>
        <?php endif; ?>
    <div class="msg-col">
      <div class="msg-meta"><a href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a></div>
      <div class="msg-bub them"><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></div>
      <?php if (!empty($m['media_url'])): ?>
      <?php $mtype = (string)($m['media_type'] ?? ''); ?>
      <?php if (str_starts_with($mtype, 'image/')): ?>
    <img src="<?= htmlspecialchars($m['media_url'], ENT_QUOTES, 'UTF-8') ?>"
         style="max-width:220px;border-radius:10px;margin-top:6px;display:block;cursor:pointer"
         onclick="window.open(this.src)">
      <?php elseif (str_starts_with($mtype, 'video/')): ?>
    <video controls src="<?= htmlspecialchars($m['media_url'], ENT_QUOTES, 'UTF-8') ?>"
           style="max-width:220px;border-radius:10px;margin-top:6px;display:block"></video>
      <?php endif; ?>
      <?php endif; ?>
    </div>
      <?php else: ?>
    <a class="msg-av-ph avatar-wrapper" href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Perfil">
      <span aria-hidden="true">&#128100;</span>
      <?php if ($isOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?>
    </a>
    <div class="msg-stack">
      <div class="msg-bub them"><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></div>
      <?php if (!empty($m['media_url'])): ?>
      <?php $mtype = (string)($m['media_type'] ?? ''); ?>
      <?php if (str_starts_with($mtype, 'image/')): ?>
      <img src="<?= htmlspecialchars($m['media_url'], ENT_QUOTES, 'UTF-8') ?>"
           style="max-width:220px;border-radius:10px;margin-top:6px;display:block;cursor:pointer"
           onclick="window.open(this.src)">
      <?php elseif (str_starts_with($mtype, 'video/')): ?>
      <video controls src="<?= htmlspecialchars($m['media_url'], ENT_QUOTES, 'UTF-8') ?>"
             style="max-width:220px;border-radius:10px;margin-top:6px;display:block"></video>
      <?php endif; ?>
      <?php endif; ?>
    </div>
      <?php endif; ?>
    <?php else: ?>
    <div class="msg-stack">
      <div class="msg-bub me"><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></div>
      <?php if (!empty($m['media_url'])): ?>
      <?php $mtype = (string)($m['media_type'] ?? ''); ?>
      <?php if (str_starts_with($mtype, 'image/')): ?>
      <img src="<?= htmlspecialchars($m['media_url'], ENT_QUOTES, 'UTF-8') ?>"
           style="max-width:220px;border-radius:10px;margin-top:6px;display:block;cursor:pointer"
           onclick="window.open(this.src)">
      <?php elseif (str_starts_with($mtype, 'video/')): ?>
      <video controls src="<?= htmlspecialchars($m['media_url'], ENT_QUOTES, 'UTF-8') ?>"
             style="max-width:220px;border-radius:10px;margin-top:6px;display:block"></video>
      <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>

<div class="ch-foot">
<form method="POST" action="/features/chat/general.php?<?= htmlspecialchars(http_build_query(['sala' => $sala]), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" style="display:contents" id="msgForm">
  <input type="hidden" name="sala" value="<?= htmlspecialchars($sala, ENT_QUOTES, 'UTF-8') ?>">
  <input type="file" id="gmFile" name="media"
         accept="image/*,video/mp4,video/webm" style="display:none">
  <button type="button" id="gmFileBtn"
          style="background:none;border:none;color:#555;font-size:1.3rem;
                 cursor:pointer;padding:4px;flex-shrink:0;transition:color .15s"
          onmouseover="this.style.color='#fff'"
          onmouseout="this.style.color='#555'"
          onclick="document.getElementById('gmFile').click()">📎</button>
  <div style="flex:1;position:relative">
    <div id="gmPreview" style="display:none;align-items:center;gap:8px;
         padding:6px 10px;background:#151515;border-radius:10px;margin-bottom:6px">
      <img id="gmPreviewImg" style="max-width:60px;max-height:60px;
           border-radius:6px;display:none">
      <span id="gmPreviewName" style="font-size:0.8rem;color:#aaa"></span>
      <button type="button" onclick="clearGmFile()"
              style="background:none;border:none;color:#ff6b6b;
                     cursor:pointer;font-size:1rem;margin-left:auto">✕</button>
    </div>
    <textarea class="chat-input" name="content" id="msgInput"
              placeholder="Mensagem para o clube..."
              rows="1" maxlength="1000" autocomplete="off"></textarea>
  </div>
  <button class="btn-send" type="submit">&#10148;</button>
</form>
</div>
</div>

<nav class="bottomnav" aria-label="Navegação">
  <a href="/features/feed/index.php"><span>🏠</span>Feed</a>
  <a href="/features/profile/upload_story.php"><span>📷</span>Story</a>
  <a class="is-active" href="/features/chat/salas.php"><span>🏙️</span>Salas</a>
  <a href="/features/profile/index.php"><span>👤</span>Perfil</a>
  <a href="/features/auth/logout.php"><span>🚪</span>Sair</a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script>
(function(){
  var box = document.getElementById('msgScroll');
  if (box) box.scrollTop = box.scrollHeight;
  var ta = document.getElementById('msgInput');
  var gmFile = document.getElementById('gmFile');
  if (ta) {
    ta.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        var f = document.getElementById('msgForm');
        var hasFile = gmFile && gmFile.files && gmFile.files.length > 0;
        if (ta.value.trim() !== '' || hasFile) f.submit();
      }
    });
  }
})();

document.getElementById('gmFile').addEventListener('change', function() {
  var file = this.files[0];
  if (!file) return;
  var preview = document.getElementById('gmPreview');
  var img     = document.getElementById('gmPreviewImg');
  var name    = document.getElementById('gmPreviewName');
  preview.style.display = 'flex';
  if (file.type.startsWith('image/')) {
    var reader = new FileReader();
    reader.onload = function(e) {
      img.src = e.target.result;
      img.style.display = 'block';
    };
    reader.readAsDataURL(file);
    name.textContent = '';
  } else {
    img.style.display = 'none';
    name.textContent = '🎥 ' + file.name;
  }
});

function clearGmFile() {
  document.getElementById('gmFile').value = '';
  document.getElementById('gmPreview').style.display = 'none';
  document.getElementById('gmPreviewImg').src = '';
  document.getElementById('gmPreviewName').textContent = '';
}

(function () {
  if (typeof window.supabase === 'undefined') return;
  var _sb = window.supabase.createClient(
    <?= json_encode(SUPABASE_URL, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    <?= json_encode(SUPABASE_ANON_KEY, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
  );
  var salaSlug = <?= json_encode($sala, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var channel = _sb
    .channel('general-chat-' + salaSlug)
    .on('postgres_changes', {
      event: 'INSERT',
      schema: 'public',
      table: 'general_messages',
      filter: 'sala=eq.' + salaSlug,
    }, function () {
      window.location.reload();
    })
    .subscribe();
  window.addEventListener('beforeunload', function () {
    try { _sb.removeChannel(channel); } catch (e) {}
  });
})();
</script>
</body>
</html>
