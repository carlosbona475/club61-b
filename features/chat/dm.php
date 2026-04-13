<?php


declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';
require_once CLUB61_ROOT . '/config/online.php';

$current_user_id = trim((string) ($_SESSION['user_id'] ?? ''));
$access_token = trim((string) ($_SESSION['access_token'] ?? ''));

if ($current_user_id === '' || $access_token === '' || !defined('SUPABASE_SERVICE_KEY') || SUPABASE_SERVICE_KEY === '') {
    header('Location: /features/auth/login.php');
    exit;
}

$other_id = isset($_GET['with']) ? trim((string) $_GET['with']) : '';
if ($other_id === '' && isset($_GET['to'])) {
    $other_id = trim((string) $_GET['to']);
}
if ($other_id === '' || $other_id === $current_user_id) {
    header('Location: /features/chat/inbox.php');
    exit;
}

require_once CLUB61_ROOT . '/config/message_requests.php';
if (!mr_can_open_dm($current_user_id, $other_id)) {
    header('Location: /features/chat/inbox.php?msg=' . rawurlencode('O chat direto fica disponível após o pedido de mensagem ser aceito. Veja Pedidos de mensagem.'));
    exit;
}

function chat_dm_headers(bool $json = false): array
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

function dmClLabel(array $author): string
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

function dm_date_key(?string $iso): string
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

function dm_date_divider(string $dayKey): string
{
    $today = (new DateTimeImmutable('now'))->format('Y-m-d');
    if ($dayKey === $today) {
        return 'Hoje';
    }
    try {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $dayKey);

        return $d ? $d->format('d/m/Y') : $dayKey;
    } catch (Exception $e) {
        return $dayKey;
    }
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
            $filename = uniqid('dm_', true) . '.' . $extMap[$mime];
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
            'sender_id'    => $current_user_id,
            'receiver_id'  => $other_id,
            'content'    => $content,
            'media_url'  => $media_url,
            'media_type' => $media_type,
        ], JSON_UNESCAPED_SLASHES);
        $sk = SUPABASE_SERVICE_KEY;
        $ch = curl_init(SUPABASE_URL . '/rest/v1/direct_messages');
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
    header('Location: /features/chat/dm.php?with=' . rawurlencode($other_id));
    exit;
}

$readIso = gmdate('Y-m-d\TH:i:s\Z');
$patchUrl = SUPABASE_URL . '/rest/v1/direct_messages?sender_id=eq.' . rawurlencode($other_id)
    . '&receiver_id=eq.' . rawurlencode($current_user_id) . '&read_at=is.null';
$patchBody = json_encode(['read_at' => $readIso]);
$chP = curl_init($patchUrl);
curl_setopt_array($chP, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_POSTFIELDS => $patchBody,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => array_merge(chat_dm_headers(true), [
        'Prefer: return=minimal',
    ]),
]);
curl_exec($chP);
curl_close($chP);

$pairOr = '(and(sender_id.eq.' . $current_user_id . ',receiver_id.eq.' . $other_id . '),and(sender_id.eq.' . $other_id . ',receiver_id.eq.' . $current_user_id . '))';
$msgUrl = SUPABASE_URL . '/rest/v1/direct_messages?or=' . rawurlencode($pairOr)
    . '&select=id,sender_id,receiver_id,content,media_url,media_type,created_at&order=created_at.asc&limit=100';

$chM = curl_init($msgUrl);
curl_setopt_array($chM, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => chat_dm_headers(false),
    CURLOPT_HTTPGET => true,
]);
$rawM = curl_exec($chM);
$codeM = curl_getinfo($chM, CURLINFO_HTTP_CODE);
curl_close($chM);

$messages = [];
if ($rawM !== false && $codeM >= 200 && $codeM < 300) {
    $decoded = json_decode($rawM, true);
    if (is_array($decoded)) {
        $messages = $decoded;
    }
}

$profUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . rawurlencode($other_id) . '&select=id,display_id,username,avatar_url,last_seen';
$chPr = curl_init($profUrl);
curl_setopt_array($chPr, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => chat_dm_headers(false),
    CURLOPT_HTTPGET => true,
]);
$rawPr = curl_exec($chPr);
curl_close($chPr);
$otherProfile = null;
if ($rawPr !== false) {
    $rows = json_decode($rawPr, true);
    if (is_array($rows) && !empty($rows[0])) {
        $otherProfile = $rows[0];
    }
}
$otherName = $otherProfile !== null ? dmClLabel($otherProfile) : 'Membro';
$otherAvatar = ($otherProfile !== null && !empty($otherProfile['avatar_url'])) ? trim((string) $otherProfile['avatar_url']) : '';
$otherLastSeen = $otherProfile !== null && isset($otherProfile['last_seen']) ? (string) $otherProfile['last_seen'] : null;
$otherOnline = isUserOnline($otherLastSeen);
$profileLink = '/features/profile/view.php?id=' . rawurlencode($other_id);
$dmFormAction = '/features/chat/dm.php?with=' . rawurlencode($other_id);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>DM — Club61</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;height:100dvh}
body{
  background:#0A0A0A;color:#fff;font-family:'Segoe UI',system-ui,sans-serif;
  display:flex;flex-direction:column;max-width:480px;margin:0 auto;width:100%;
  min-height:100dvh;
}
.dm-top{
  flex-shrink:0;display:flex;align-items:center;gap:10px;padding:10px 12px;
  background:#0A0A0A;border-bottom:1px solid #1a1a1a;
}
.dm-top a.back{color:#aaa;text-decoration:none;font-size:1.2rem}
.dm-top .dm-head-av{width:40px;height:40px;border-radius:50%;object-fit:cover;border:1px solid #222}
.avatar-wrapper{position:relative;display:inline-flex;align-items:center;justify-content:center}
.online-dot{position:absolute;bottom:2px;right:2px;width:10px;height:10px;background:#00ff88;border-radius:50%;border:2px solid #111}
.dm-head-text{flex:1;min-width:0}
.dm-head-text h1{font-size:0.95rem;color:#C9A84C;font-weight:700}
.dm-head-text p{font-size:0.72rem;color:#555;margin-top:2px}
.dm-head-text a{text-decoration:none;color:inherit}
.dm-msgs{flex:1;overflow-y:auto;padding:18px 14px 14px;display:flex;flex-direction:column;gap:14px;min-height:0}
.date-div{text-align:center;font-size:0.74rem;color:#444;margin:18px 0 14px}
.msg-row{display:flex;flex-direction:column;gap:8px;align-items:flex-start;max-width:100%}
.msg-row.me{align-items:flex-end}
.msg-row.them{align-items:flex-start}
.msg-bub{max-width:85%;padding:14px 18px;font-size:0.95rem;line-height:1.55;word-break:break-word;white-space:pre-wrap}
.msg-bub.me{background:#7B2EFF;color:#fff;border-radius:18px 4px 18px 18px}
.msg-bub.them{background:#1e1e1e;color:#ddd;border-radius:4px 18px 18px 18px}
.dm-empty{text-align:center;padding:40px 20px;color:#555}
.dm-foot{
  flex-shrink:0;padding:10px 12px;padding-bottom:calc(10px + env(safe-area-inset-bottom,0px));
  background:#0A0A0A;border-top:1px solid #1a1a1a;display:flex;gap:8px;align-items:flex-end;
}
.dm-foot textarea,.dm-foot .chat-input{
  flex:1;background:#111;border:1px solid #222;border-radius:12px;color:#fff;padding:10px 12px;font-size:0.95rem;
  resize:none;max-height:120px;min-height:44px;font-family:inherit;outline:none;
}
.dm-foot textarea:focus,.dm-foot .chat-input:focus{border-color:#7B2EFF}
.dm-send,.btn-send{
  width:44px;height:44px;border-radius:50%;border:none;background:#7B2EFF;color:#fff;font-size:1.1rem;cursor:pointer;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
</style>
</head>
<body>

<header class="dm-top">
  <a class="back" href="/features/chat/inbox.php" aria-label="Voltar">←</a>
  <a class="avatar-wrapper" href="<?= htmlspecialchars($profileLink, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($otherAvatar !== ''): ?>
    <img class="dm-head-av" src="<?= htmlspecialchars($otherAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="">
    <?php else: ?>
    <div class="dm-head-av" style="display:flex;align-items:center;justify-content:center;background:#111;color:#7B2EFF">👤</div>
    <?php endif; ?>
    <?php if ($otherOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?>
  </a>
  <div class="dm-head-text">
    <h1><a href="<?= htmlspecialchars($profileLink, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($otherName, ENT_QUOTES, 'UTF-8') ?></a></h1>
    <p><?= $otherOnline ? 'online' : 'mensagem privada' ?></p>
  </div>
</header>

<div class="dm-msgs" id="dmScroll">
<?php if (empty($messages)): ?>
  <div class="dm-empty">💌 Diga olá para <?= htmlspecialchars($otherName, ENT_QUOTES, 'UTF-8') ?>!</div>
<?php else: ?>
<?php
$prevDay = null;
foreach ($messages as $m):
    $sid = isset($m['sender_id']) ? (string) $m['sender_id'] : '';
    $isMe = $sid === $current_user_id;
    $created = isset($m['created_at']) ? (string) $m['created_at'] : '';
    $dayKey = dm_date_key($created);
    if ($dayKey !== '' && $dayKey !== $prevDay):
        $prevDay = $dayKey;
        ?>
  <div class="date-div"><?= htmlspecialchars(dm_date_divider($dayKey), ENT_QUOTES, 'UTF-8') ?></div>
<?php
    endif;
    $txt = isset($m['content']) ? (string) $m['content'] : '';
    ?>
  <div class="msg-row <?= $isMe ? 'me' : 'them' ?>">
    <div class="msg-bub <?= $isMe ? 'me' : 'them' ?>"><?= htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') ?></div>
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
<?php endforeach; ?>
<?php endif; ?>
</div>

<div class="dm-foot">
<form method="POST" action="<?= htmlspecialchars($dmFormAction, ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" style="display:contents" id="dmForm">
  <input type="file" id="dmFile" name="media"
         accept="image/*,video/mp4,video/webm" style="display:none">
  <button type="button" id="dmFileBtn"
          style="background:none;border:none;color:#555;font-size:1.3rem;
                 cursor:pointer;padding:4px;flex-shrink:0;transition:color .15s"
          onmouseover="this.style.color='#fff'"
          onmouseout="this.style.color='#555'"
          onclick="document.getElementById('dmFile').click()">📎</button>
  <div style="flex:1;position:relative">
    <div id="dmPreview" style="display:none;align-items:center;gap:8px;
         padding:6px 10px;background:#151515;border-radius:10px;margin-bottom:6px">
      <img id="dmPreviewImg" style="max-width:60px;max-height:60px;
           border-radius:6px;display:none">
      <span id="dmPreviewName" style="font-size:0.8rem;color:#aaa"></span>
      <button type="button" onclick="clearDmFile()"
              style="background:none;border:none;color:#ff6b6b;
                     cursor:pointer;font-size:1rem;margin-left:auto">✕</button>
    </div>
    <textarea class="chat-input" name="content" id="dmInput"
              placeholder="Mensagem para o clube..."
              rows="1" maxlength="1000" autocomplete="off"></textarea>
  </div>
  <button class="btn-send" type="submit">&#10148;</button>
</form>
</div>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script>
(function(){
  var box = document.getElementById('dmScroll');
  if (box) box.scrollTop = box.scrollHeight;
  var ta = document.getElementById('dmInput');
  var dmFile = document.getElementById('dmFile');
  if (ta) {
    ta.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        var f = document.getElementById('dmForm');
        var hasFile = dmFile && dmFile.files && dmFile.files.length > 0;
        if (ta.value.trim() !== '' || hasFile) f.submit();
      }
    });
  }
})();

document.getElementById('dmFile').addEventListener('change', function() {
  var file = this.files[0];
  if (!file) return;
  var preview = document.getElementById('dmPreview');
  var img     = document.getElementById('dmPreviewImg');
  var name    = document.getElementById('dmPreviewName');
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

function clearDmFile() {
  document.getElementById('dmFile').value = '';
  document.getElementById('dmPreview').style.display = 'none';
  document.getElementById('dmPreviewImg').src = '';
  document.getElementById('dmPreviewName').textContent = '';
}

(function () {
  if (typeof window.supabase === 'undefined') return;
  var _sb = window.supabase.createClient(
    <?= json_encode(SUPABASE_URL, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    <?= json_encode(SUPABASE_ANON_KEY, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
  );
  var me = <?= json_encode((string) $current_user_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var other = <?= json_encode((string) $other_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var channel = _sb
    .channel('dm-live-' + me + '-' + other)
    .on('postgres_changes', { event: 'INSERT', schema: 'public', table: 'direct_messages' }, function (payload) {
      var row = payload && payload.new ? payload.new : null;
      if (!row) return;
      var s = String(row.sender_id || '');
      var r = String(row.receiver_id || '');
      var isPair = (s === me && r === other) || (s === other && r === me);
      if (isPair) window.location.reload();
    })
    .subscribe();
  window.addEventListener('beforeunload', function () {
    try { _sb.removeChannel(channel); } catch (e) {}
  });
})();
</script>
</body>
</html>
