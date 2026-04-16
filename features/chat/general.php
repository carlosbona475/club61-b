<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/city_rooms.php';
require_once __DIR__ . '/chat_backend.php';

$current_user_id = trim((string) ($_SESSION['user_id'] ?? ''));
$access_token = trim((string) ($_SESSION['access_token'] ?? ''));

if ($current_user_id === '' || $access_token === '') {
    header('Location: /features/auth/login.php');
    exit;
}

$chatServiceOk = defined('SUPABASE_SERVICE_KEY') && SUPABASE_SERVICE_KEY !== '';
if (!$chatServiceOk) {
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Chat indisponível</title></head><body style="background:#0A0A0A;color:#fff;font-family:system-ui;padding:24px">';
    echo '<p>Chat geral indisponível: configure <strong>SUPABASE_SERVICE_KEY</strong> no servidor.</p>';
    echo '<p><a href="/features/chat/salas.php" style="color:#C9A84C">Voltar às salas</a></p></body></html>';
    exit;
}

$sala = trim((string) ($_GET['sala'] ?? ''));
$roomMeta = club61_city_room_by_slug($sala);
if ($roomMeta === null) {
    header('Location: /features/chat/salas.php');
    exit;
}

function club61_chat_format_hhmm(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '';
    }
    try {
        $d = new DateTimeImmutable($iso);
        $d = $d->setTimezone(new DateTimeZone('America/Sao_Paulo'));

        return $d->format('H:i');
    } catch (Exception $e) {
        return '';
    }
}

function gm_date_key(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '';
    }
    try {
        $d = new DateTimeImmutable($iso);

        return $d->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
    } catch (Exception $e) {
        return '';
    }
}

function date_divider_label(string $dayKey): string
{
    $today = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
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

$initialRows = club61_chat_fetch_messages_for_sala($sala, null, 100);
$initialMessages = club61_chat_enrich_messages($initialRows);
$initialOnline = club61_chat_online_in_sala($sala, 120);

$meProf = club61_chat_fetch_profiles_by_ids([$current_user_id]);
$myMemberLine = isset($meProf[$current_user_id]) ? club61_chat_member_line($meProf[$current_user_id]) : '';

$lastIso = '';
$lastMsgId = '';
if ($initialMessages !== []) {
    $last = $initialMessages[count($initialMessages) - 1];
    $lastIso = isset($last['created_at']) ? (string) $last['created_at'] : '';
    $lastMsgId = isset($last['id']) ? (string) $last['id'] : '';
}

$chatApi = '/features/chat/chat_actions.php';
/** Caminhos absolutos na raiz do site; /chat/* exige mod_rewrite (veja .htaccess). Fallback sempre funciona. */
$chatEp = [
    'mensagens' => '/chat/mensagens',
    'messages' => $chatApi . '?r=messages',
    'messagesShort' => '/chat/messages',
    'send' => '/chat/enviar',
    'sendFallback' => $chatApi . '?r=send',
    'react' => $chatApi . '?r=react',
    'online' => $chatApi . '?r=online',
    'presence' => $chatApi . '?r=presence',
    'typing' => $chatApi . '?r=typing',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($roomMeta['nome'], ENT_QUOTES, 'UTF-8') ?> — Club61</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;height:100dvh}
body.gm-body{
  background:#0A0A0A;color:#fff;font-family:'Segoe UI',system-ui,sans-serif;
  display:flex;flex-direction:column;align-items:stretch;
  max-width:none;margin:0 auto;width:100%;min-height:100dvh;
  padding-bottom:calc(56px + env(safe-area-inset-bottom,0px));
}
.uol-wrap{
  flex:1;display:flex;flex-direction:row;min-height:0;width:100%;
  max-width:1200px;margin:0 auto;align-items:stretch;
}
.uol-sidebar{
  flex:0 0 220px;width:220px;min-width:0;
  background:#111111;border-right:1px solid #2a2a2a;
  display:flex;flex-direction:column;min-height:0;
}
.uol-sidebar-inner{padding:14px 12px 16px;display:flex;flex-direction:column;gap:10px;min-height:0;flex:1}
.uol-side-title{
  font-size:0.82rem;font-weight:800;color:#C9A84C;text-transform:uppercase;letter-spacing:0.06em;
  flex-shrink:0;padding-bottom:4px;border-bottom:1px solid #2a2a2a;margin-bottom:4px
}
.uol-side-list{flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:6px;min-height:0}
.uol-side-item{
  display:flex;align-items:center;gap:8px;padding:6px 4px;border-radius:8px;text-decoration:none;
  color:#ddd;font-size:0.8rem;min-height:40px;
}
.uol-side-item:hover{background:rgba(123,46,255,0.08);color:#fff}
.uol-side-av{width:32px;height:32px;border-radius:50%;object-fit:cover;background:#0d0d0d;flex-shrink:0;border:1px solid #2a2a2a}
.uol-side-ph{width:32px;height:32px;border-radius:50%;background:#0d0d0d;border:1px solid #2a2a2a;display:flex;align-items:center;justify-content:center;font-size:0.9rem;color:#7B2EFF;flex-shrink:0}
.uol-side-dot{width:8px;height:8px;border-radius:50%;background:#22c55e;flex-shrink:0;box-shadow:0 0 0 2px #111}
.uol-side-name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#FFFFFF;font-weight:600;font-size:0.8rem}
.uol-main{
  flex:1;min-width:0;display:flex;flex-direction:column;min-height:0;background:#0A0A0A;
}
.uol-feed{
  flex:1;overflow-y:auto;min-height:0;padding:16px 14px 12px;display:flex;flex-direction:column;gap:4px;
}
.uol-date{text-align:center;font-size:0.72rem;color:#AAAAAA;margin:14px 0 10px}
.uol-msg{display:flex;gap:10px;align-items:flex-start;padding:8px 4px;border-radius:10px}
.uol-msg.is-me{background:rgba(123,46,255,0.06)}
.uol-msg-av{width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;background:#111;border:1px solid #2a2a2a}
.uol-msg-av-ph{width:36px;height:36px;border-radius:50%;background:#111;border:1px solid #2a2a2a;display:flex;align-items:center;justify-content:center;color:#7B2EFF;font-size:1rem;flex-shrink:0;text-decoration:none}
.uol-msg-core{flex:1;min-width:0;display:flex;flex-direction:column;gap:4px}
.uol-msg-head{display:flex;align-items:baseline;justify-content:space-between;gap:10px;flex-wrap:wrap}
.uol-msg-name{font-size:0.82rem;font-weight:700;color:#C9A84C;text-decoration:none}
.uol-msg-name:hover{text-decoration:underline}
.uol-msg-time{font-size:0.72rem;color:#AAAAAA;flex-shrink:0;margin-left:auto}
.uol-msg-text{font-size:0.92rem;color:#FFFFFF;line-height:1.45;white-space:pre-wrap;word-break:break-word}
.uol-msg-media{margin-top:4px;max-width:min(100%,320px)}
.uol-msg-media img,.uol-msg-media video{display:block;width:100%;height:auto;border-radius:10px;border:1px solid #2a2a2a}
.uol-react-row{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;align-items:center}
.uol-react-btn{
  display:inline-flex;align-items:center;gap:4px;padding:4px 8px;border-radius:999px;
  border:1px solid #2a2a2a;background:#151515;color:#fff;font-size:0.85rem;cursor:pointer;
  line-height:1;
}
.uol-react-btn:hover{border-color:#7B2EFF;background:#1a1225}
.uol-react-btn.is-on{border-color:#C9A84C;background:rgba(201,168,76,0.12)}
.uol-react-count{font-size:0.75rem;color:#AAAAAA}
.uol-inputbar{
  flex-shrink:0;border-top:1px solid #2a2a2a;background:#0A0A0A;
  padding:10px 12px;padding-bottom:calc(10px + env(safe-area-inset-bottom,0px));
}
.chat-typing{
  min-height:18px;
  font-size:.78rem;
  color:#9aa0a6;
  padding:0 4px 6px;
}
.uol-chat-form{width:100%}
#preview-midia{padding:8px}
#preview-midia img,#preview-midia video{vertical-align:middle}
.input-row{
  display:flex;align-items:center;gap:8px;padding:10px;background:#111;border-top:1px solid #2a2a2a;
}
.btn-anexo{
  font-size:22px;cursor:pointer;flex-shrink:0;padding:4px;opacity:0.8;transition:opacity 0.2s;line-height:1;
}
.btn-anexo:hover{opacity:1}
#input-mensagem{
  flex:1;background:#1a1a1a;border:1px solid #2a2a2a;border-radius:20px;padding:10px 16px;color:#fff;
  font-size:14px;outline:none;min-width:0;font-family:inherit;
}
#input-mensagem:focus{border-color:#7B2EFF}
.btn-enviar{
  background:#7B2EFF;border:none;border-radius:50%;width:42px;height:42px;font-size:18px;cursor:pointer;
  flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#fff;line-height:1;
}
.btn-enviar:hover{filter:brightness(1.08)}
.btn-enviar:disabled{opacity:0.6;cursor:not-allowed}
.ch-top{
  flex-shrink:0;width:100%;display:flex;align-items:center;justify-content:space-between;
  padding:10px 12px;background:#0A0A0A;border-bottom:1px solid #2a2a2a;gap:8px;
}
.ch-top a{color:#AAAAAA;text-decoration:none;font-size:1.2rem;padding:4px}
.ch-top a:hover{color:#C9A84C}
.ch-title-wrap{display:flex;align-items:center;gap:8px;flex:1;justify-content:center}
.ch-title{font-size:1rem;font-weight:700;color:#C9A84C}
.btn-participantes-mobile{
  display:none;align-items:center;gap:4px;border:1px solid #2a2a2a;background:#111;color:#C9A84C;border-radius:999px;
  padding:6px 10px;font-size:.75rem;font-weight:700;cursor:pointer;line-height:1;font-family:inherit;
}
.btn-participantes-mobile #count-online{font-variant-numeric:tabular-nums}
.bottomnav{
  position:fixed;left:0;right:0;bottom:0;z-index:300;
  display:flex;align-items:center;justify-content:space-around;
  height:56px;padding:0 4px;padding-bottom:env(safe-area-inset-bottom,0px);
  background:#0A0A0A;border-top:1px solid #2a2a2a;max-width:480px;margin:0 auto;
}
.bottomnav a{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  text-decoration:none;color:#888;font-size:0.58rem;gap:2px;padding:6px 4px;
}
.bottomnav a span:first-child{font-size:1.05rem;line-height:1}
.bottomnav a:hover{color:#ccc}
.bottomnav a.is-active{color:#7B2EFF}
.uol-sidebar-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:399}
.uol-sidebar-backdrop.is-on{display:block}
@media (max-width:767px){
  .uol-sidebar{
    position:fixed;top:0;left:0;bottom:0;width:min(260px,88vw);z-index:400;
    transform:translateX(-100%);transition:transform .28s ease;box-shadow:8px 0 24px rgba(0,0,0,0.5);
  }
  .uol-sidebar.is-open{transform:translateX(0)}
  .btn-participantes-mobile{display:inline-flex}
}
@media (min-width:768px){
  .btn-participantes-mobile{display:none!important}
  .uol-sidebar{position:relative;transform:none!important;box-shadow:none}
}
</style>
</head>
<body class="gm-body" data-my-line="<?= htmlspecialchars($myMemberLine, ENT_QUOTES, 'UTF-8') ?>">

<header class="ch-top">
  <a href="/features/chat/salas.php" aria-label="Voltar às salas">←</a>
  <div class="ch-title-wrap"><span aria-hidden="true"><?= htmlspecialchars($roomMeta['emoji'], ENT_QUOTES, 'UTF-8') ?></span><span class="ch-title"><?= htmlspecialchars($roomMeta['nome'], ENT_QUOTES, 'UTF-8') ?></span></div>
  <button type="button" class="btn-participantes-mobile" id="btnParticipantesMobile" onclick="toggleParticipantes()" aria-controls="uolSidebar" aria-expanded="false" title="Ver participantes online">
    👥 <span id="count-online"><?= (int) count($initialOnline) ?></span> online
  </button>
  <a href="/features/chat/inbox.php" aria-label="Mensagens">✉️</a>
</header>

<div class="uol-wrap">
  <aside class="uol-sidebar" id="uolSidebar" aria-label="Participantes online">
    <div class="uol-sidebar-inner">
      <div class="uol-side-title" id="uolSideTitle">Participantes (<?= (int) count($initialOnline) ?>)</div>
      <div class="uol-side-list" id="uolSideList">
<?php if ($initialOnline === []): ?>
        <p style="font-size:0.78rem;color:#AAAAAA;line-height:1.4;padding:4px">Ninguém na sala nos últimos 2 min. Abra o chat para registrar presença.</p>
<?php else: ?>
<?php foreach ($initialOnline as $ou):
    $oid = (string) ($ou['user_id'] ?? '');
    $href = '/features/profile/view.php?id=' . rawurlencode($oid);
    $av = trim((string) ($ou['avatar_url'] ?? ''));
    $line = htmlspecialchars((string) ($ou['member_line'] ?? ''), ENT_QUOTES, 'UTF-8');
    ?>
        <a class="uol-side-item" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
          <span class="uol-side-dot" aria-hidden="true"></span>
          <?php if ($av !== ''): ?>
          <img class="uol-side-av" src="<?= htmlspecialchars($av, ENT_QUOTES, 'UTF-8') ?>" alt="">
          <?php else: ?>
          <span class="uol-side-ph" aria-hidden="true">&#128100;</span>
          <?php endif; ?>
          <span class="uol-side-name"><?= $line ?></span>
        </a>
<?php endforeach; ?>
<?php endif; ?>
      </div>
    </div>
  </aside>

  <section class="uol-main" aria-label="Conversa">
    <div class="uol-feed" id="chat-feed" data-sala="<?= htmlspecialchars($sala, ENT_QUOTES, 'UTF-8') ?>">
<?php
$prevDay = null;
foreach ($initialMessages as $m):
    $mid = isset($m['user_id']) ? (string) $m['user_id'] : '';
    $created = isset($m['created_at']) ? (string) $m['created_at'] : '';
    $dayKey = gm_date_key($created);
    if ($dayKey !== '' && $dayKey !== $prevDay):
        $prevDay = $dayKey;
        ?>
      <div class="uol-date"><?= htmlspecialchars(date_divider_label($dayKey), ENT_QUOTES, 'UTF-8') ?></div>
<?php
    endif;
    $isMe = $mid === $current_user_id;
    $author = isset($m['author']) && is_array($m['author']) ? $m['author'] : [];
    $memberLine = htmlspecialchars((string) ($author['member_line'] ?? 'Membro'), ENT_QUOTES, 'UTF-8');
    $profUrl = '/features/profile/view.php?id=' . rawurlencode($mid);
    $av = isset($author['avatar_url']) ? trim((string) $author['avatar_url']) : '';
    $tipo = isset($m['tipo']) ? (string) $m['tipo'] : 'texto';
    $isMedia = ($tipo === 'imagem' || $tipo === 'video');
    $msgId = isset($m['id']) ? (string) $m['id'] : '';
    $conteudo = isset($m['conteudo']) ? (string) $m['conteudo'] : '';
    $mediaUrl = isset($m['media_url']) ? trim((string) $m['media_url']) : '';
    $reactions = isset($m['reactions']) && is_array($m['reactions']) ? $m['reactions'] : [];
    $allowedReact = CLUB61_CHAT_ALLOWED_REACTION_EMOJIS;
    ?>
      <article class="uol-msg<?= $isMe ? ' is-me' : '' ?>" data-msg-id="<?= htmlspecialchars($msgId, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($av !== ''): ?>
        <a href="<?= htmlspecialchars($profUrl, ENT_QUOTES, 'UTF-8') ?>"><img class="uol-msg-av" src="<?= htmlspecialchars($av, ENT_QUOTES, 'UTF-8') ?>" alt=""></a>
        <?php else: ?>
        <a class="uol-msg-av-ph" href="<?= htmlspecialchars($profUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Perfil">&#128100;</a>
        <?php endif; ?>
        <div class="uol-msg-core">
          <div class="uol-msg-head">
            <a class="uol-msg-name" href="<?= htmlspecialchars($profUrl, ENT_QUOTES, 'UTF-8') ?>"><?= $memberLine ?></a>
            <span class="uol-msg-time"><?= htmlspecialchars(club61_chat_format_hhmm($created), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <?php if ($conteudo !== ''): ?>
          <div class="uol-msg-text"><?= htmlspecialchars($conteudo, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>
          <?php if ($isMedia && $mediaUrl !== ''): ?>
          <div class="uol-msg-media">
            <?php if ($tipo === 'imagem'): ?>
            <img src="<?= htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8') ?>" alt=""
                 style="max-width:250px;max-height:250px;border-radius:12px;margin-top:6px;display:block;cursor:pointer;"
                 onclick="abrirImagem(this.src)">
            <?php else: ?>
            <video controls preload="metadata" src="<?= htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8') ?>"
                    style="max-width:250px;border-radius:12px;margin-top:6px;display:block;"></video>
            <?php endif; ?>
          </div>
          <div class="uol-react-row" data-react-for="<?= htmlspecialchars($msgId, ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach ($allowedReact as $em):
                $info = $reactions[$em] ?? null;
                $cnt = is_array($info) && isset($info['count']) ? (int) $info['count'] : 0;
                $users = is_array($info) && isset($info['users']) && is_array($info['users']) ? $info['users'] : [];
                $tip = htmlspecialchars(implode(', ', $users), ENT_QUOTES, 'UTF-8');
                $userReacted = $myMemberLine !== '' && in_array($myMemberLine, $users, true);
                ?>
            <button type="button" class="uol-react-btn<?= $userReacted ? ' is-on' : '' ?>" data-emoji="<?= htmlspecialchars($em, ENT_QUOTES, 'UTF-8') ?>" title="<?= $tip !== '' ? $tip : 'Reagir' ?>">
              <span aria-hidden="true"><?= htmlspecialchars($em, ENT_QUOTES, 'UTF-8') ?></span>
              <span class="uol-react-count"><?= $cnt > 0 ? (int) $cnt : '' ?></span>
            </button>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </article>
<?php endforeach; ?>
    </div>

    <div class="uol-inputbar">
      <div id="chatTypingIndicator" class="chat-typing" aria-live="polite"></div>
      <form id="form-chat" class="uol-chat-form" action="#" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="sala_id" value="<?= htmlspecialchars($sala, ENT_QUOTES, 'UTF-8') ?>">
        <div id="preview-midia" style="display:none;padding:8px;">
          <img id="preview-img" alt="" style="max-height:100px;border-radius:8px;display:none;">
          <video id="preview-video" style="max-height:100px;border-radius:8px;display:none;" controls></video>
          <button type="button" onclick="cancelarMidia()" style="background:none;border:none;color:#f44;cursor:pointer;margin-left:8px;">✕ Cancelar</button>
        </div>
        <div class="input-row">
          <label for="input-arquivo" class="btn-anexo" title="Enviar foto ou vídeo">📎</label>
          <input type="file" id="input-arquivo" name="arquivo" accept="image/*,video/*" style="display:none" onchange="previewArquivo(this)">
          <input type="text" id="input-mensagem" name="mensagem" maxlength="1000" placeholder="Digite sua mensagem..." autocomplete="off">
          <button type="submit" class="btn-enviar" id="btn-enviar" title="Enviar" aria-label="Enviar">🚀</button>
        </div>
      </form>
    </div>
  </section>
</div>
<div class="uol-sidebar-backdrop" id="uolSidebarBackdrop" aria-hidden="true"></div>

<nav class="bottomnav" aria-label="Navegação">
  <a href="/features/feed/index.php"><span>🏠</span>Feed</a>
  <a href="/features/profile/upload_story.php"><span>📷</span>Story</a>
  <a class="is-active" href="/features/chat/salas.php"><span>🏙️</span>Salas</a>
  <a href="/features/profile/index.php"><span>👤</span>Perfil</a>
  <a href="/features/auth/logout.php"><span>🚪</span>Sair</a>
</nav>

<script>
var __chatPreviewUrl = null;
function abrirImagem(src) {
  var overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.9);display:flex;align-items:center;justify-content:center;z-index:9999;cursor:zoom-out';
  var im = document.createElement('img');
  im.src = src;
  im.style.cssText = 'max-width:90vw;max-height:90vh;border-radius:8px;';
  overlay.appendChild(im);
  overlay.onclick = function () { overlay.remove(); };
  document.body.appendChild(overlay);
}
function previewArquivo(input) {
  var file = input.files && input.files[0];
  if (!file) return;
  var previewDiv = document.getElementById('preview-midia');
  var previewImg = document.getElementById('preview-img');
  var previewVideo = document.getElementById('preview-video');
  if (!previewDiv || !previewImg || !previewVideo) return;
  if (__chatPreviewUrl) {
    try { URL.revokeObjectURL(__chatPreviewUrl); } catch (e) {}
    __chatPreviewUrl = null;
  }
  previewDiv.style.display = 'block';
  if (file.type.indexOf('image/') === 0) {
    __chatPreviewUrl = URL.createObjectURL(file);
    previewImg.src = __chatPreviewUrl;
    previewImg.style.display = 'block';
    previewVideo.style.display = 'none';
    previewVideo.removeAttribute('src');
  } else if (file.type.indexOf('video/') === 0) {
    __chatPreviewUrl = URL.createObjectURL(file);
    previewVideo.src = __chatPreviewUrl;
    previewVideo.style.display = 'block';
    previewImg.style.display = 'none';
    previewImg.removeAttribute('src');
  }
}
function cancelarMidia() {
  if (__chatPreviewUrl) {
    try { URL.revokeObjectURL(__chatPreviewUrl); } catch (e) {}
    __chatPreviewUrl = null;
  }
  var fi = document.getElementById('input-arquivo');
  var previewDiv = document.getElementById('preview-midia');
  var previewImg = document.getElementById('preview-img');
  var previewVideo = document.getElementById('preview-video');
  if (fi) fi.value = '';
  if (previewDiv) previewDiv.style.display = 'none';
  if (previewImg) { previewImg.src = ''; previewImg.style.display = 'none'; }
  if (previewVideo) { previewVideo.src = ''; previewVideo.style.display = 'none'; }
}
(function () {
  var sala = <?= json_encode($sala, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var currentUserId = <?= json_encode($current_user_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var EP = <?= json_encode($chatEp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var lastIso = <?= json_encode($lastIso, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var ultimoId = <?= json_encode($lastMsgId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var allowedReact = <?= json_encode(CLUB61_CHAT_ALLOWED_REACTION_EMOJIS, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var __chatSendInFlight = false;
  var myLineAttr = document.body.getAttribute('data-my-line') || '';

  function $(sel) { return document.querySelector(sel); }
  function feedEl() { return document.getElementById('chat-feed'); }

  function scrollFeedBottom() {
    var box = feedEl();
    if (box) box.scrollTop = box.scrollHeight;
  }

  function esc(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function fmtTime(iso) {
    if (!iso) return '';
    try {
      var d = new Date(iso);
      return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', hour12: false });
    } catch (e) { return ''; }
  }

  function buildMsgRow(m) {
    var author = m.author || {};
    var uid = m.user_id || '';
    var isMe = uid === currentUserId;
    var prof = '/features/profile/view.php?id=' + encodeURIComponent(uid);
    var av = (author.avatar_url || '').trim();
    var line = author.member_line || 'Membro';
    var tipo = m.tipo || 'texto';
    var isMedia = tipo === 'imagem' || tipo === 'video';
    var msgId = m.id || '';
    var html = '';
    html += '<article class="uol-msg' + (isMe ? ' is-me' : '') + '" data-msg-id="' + esc(msgId) + '">';
    if (av) {
      html += '<a href="' + esc(prof) + '"><img class="uol-msg-av" src="' + esc(av) + '" alt=""></a>';
    } else {
      html += '<a class="uol-msg-av-ph" href="' + esc(prof) + '" aria-label="Perfil">&#128100;</a>';
    }
    html += '<div class="uol-msg-core">';
    html += '<div class="uol-msg-head">';
    html += '<a class="uol-msg-name" href="' + esc(prof) + '">' + esc(line) + '</a>';
    html += '<span class="uol-msg-time">' + esc(fmtTime(m.created_at)) + '</span>';
    html += '</div>';
    if (m.conteudo) {
      html += '<div class="uol-msg-text">' + esc(m.conteudo) + '</div>';
    }
    if (isMedia && m.media_url) {
      html += '<div class="uol-msg-media">';
      if (tipo === 'imagem') {
        html += '<img src="' + esc(m.media_url) + '" alt="" style="max-width:250px;max-height:250px;border-radius:12px;margin-top:6px;display:block;cursor:pointer;" onclick="abrirImagem(this.src)">';
      } else {
        html += '<video controls preload="metadata" src="' + esc(m.media_url) + '" style="max-width:250px;border-radius:12px;margin-top:6px;display:block;"></video>';
      }
      html += '</div>';
      html += renderReactions(msgId, m.reactions || {});
    }
    html += '</div></article>';
    return html;
  }

  function renderReactions(msgId, reactMap) {
    var html = '<div class="uol-react-row" data-react-for="' + esc(msgId) + '">';
    for (var i = 0; i < allowedReact.length; i++) {
      var em = allowedReact[i];
      var info = reactMap[em] || {};
      var cnt = info.count || 0;
      var users = info.users || [];
      var tip = users.join(', ');
      var userReacted = users.indexOf(getMyMemberLine()) >= 0;
      html += '<button type="button" class="uol-react-btn' + (userReacted ? ' is-on' : '') + '" data-emoji="' + esc(em) + '" title="' + esc(tip || 'Reagir') + '">';
      html += '<span aria-hidden="true">' + esc(em) + '</span>';
      html += '<span class="uol-react-count">' + (cnt > 0 ? String(cnt) : '') + '</span>';
      html += '</button>';
    }
    html += '</div>';
    return html;
  }

  var cachedMemberLine = null;
  function getMyMemberLine() {
    if (myLineAttr) return myLineAttr;
    if (cachedMemberLine) return cachedMemberLine;
    var nodes = feedEl().querySelectorAll('.uol-msg.is-me .uol-msg-name');
    if (nodes.length) {
      cachedMemberLine = nodes[0].textContent.trim();
      return cachedMemberLine;
    }
    return '';
  }

  function appendMessages(list, opts) {
    opts = opts || {};
    if (!list || !list.length) return;
    var box = feedEl();
    if (!box) return;
    var estavaNoBaixo = box.scrollHeight - box.scrollTop <= box.clientHeight + 50;
    var html = '';
    for (var i = 0; i < list.length; i++) {
      var mid = list[i].id || '';
      if (mid && box.querySelector('[data-msg-id="' + mid + '"]')) continue;
      html += buildMsgRow(list[i]);
      lastIso = list[i].created_at || lastIso;
      if (mid) ultimoId = String(mid);
    }
    if (html) {
      box.insertAdjacentHTML('beforeend', html);
      bindReactButtons(box);
      if (opts.forceScroll || estavaNoBaixo) scrollFeedBottom();
    }
  }

  function bindReactButtons(root) {
    root.querySelectorAll('.uol-react-btn').forEach(function (btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () {
        var row = btn.closest('.uol-react-row');
        if (!row) return;
        var mid = row.getAttribute('data-react-for');
        var emoji = btn.getAttribute('data-emoji');
        fetch(EP.react, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ message_id: mid, emoji: emoji })
        }).then(function (r) { return r.json(); }).then(function (data) {
          if (!data || !data.ok || !data.reactions) return;
          row.querySelectorAll('.uol-react-btn').forEach(function (b) {
            var em = b.getAttribute('data-emoji');
            var inf = data.reactions[em];
            var cnt = inf && inf.count ? inf.count : 0;
            var users = inf && inf.users ? inf.users : [];
            b.querySelector('.uol-react-count').textContent = cnt > 0 ? String(cnt) : '';
            b.title = users.join(', ') || 'Reagir';
            var reacted = users.indexOf(getMyMemberLine()) !== -1;
            b.classList.toggle('is-on', reacted);
          });
        }).catch(function () {});
      });
    });
  }

  async function carregarMensagens() {
    if (__chatSendInFlight) return;
    var base = EP.mensagens || EP.messages;
    var url = base + '?sala_id=' + encodeURIComponent(sala);
    if (ultimoId) {
      url += '&after=' + encodeURIComponent(ultimoId);
    } else if (lastIso) {
      url += '&after=' + encodeURIComponent(lastIso);
    }
    try {
      var res = await fetch(url, { credentials: 'same-origin' });
      if (!res.ok) return;
      var data = await res.json();
      if (!data || data.ok === false) return;
      var rows = data.mensagens || data.messages;
      if (!rows || !rows.length) return;
      appendMessages(rows);
    } catch (err) {
      console.error('Erro ao carregar mensagens:', err);
    }
  }

  function typingIndicatorText(list) {
    if (!list || !list.length) return '';
    var names = [];
    for (var i = 0; i < list.length; i++) {
      if (list[i] && list[i].member_line) names.push(String(list[i].member_line).split(' — ')[0]);
    }
    if (!names.length) return '';
    if (names.length === 1) return names[0] + ' está digitando...';
    if (names.length === 2) return names[0] + ' e ' + names[1] + ' estão digitando...';
    return names.length + ' pessoas estão digitando...';
  }

  function atualizarTypingStatus() {
    fetch(EP.typing + '?sala_id=' + encodeURIComponent(sala), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var el = document.getElementById('chatTypingIndicator');
        if (!el) return;
        if (!data || !data.ok || !data.typing) {
          el.textContent = '';
          return;
        }
        el.textContent = typingIndicatorText(data.typing);
      })
      .catch(function () {});
  }

  var lastTypingPingAt = 0;
  function enviarTypingSeNecessario() {
    var ta = document.getElementById('input-mensagem');
    if (!ta || !ta.value || !ta.value.trim()) return;
    var now = Date.now();
    if (now - lastTypingPingAt < 1800) return;
    lastTypingPingAt = now;
    fetch(EP.typing, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sala_id: sala })
    }).catch(function () {});
  }

  function renderSideList(users) {
    var title = document.getElementById('uolSideTitle');
    var list = document.getElementById('uolSideList');
    var n = users ? users.length : 0;
    var countOnline = document.getElementById('count-online');
    if (countOnline) countOnline.textContent = String(n);
    if (!title || !list) return;
    title.textContent = 'Participantes (' + n + ')';
    if (!users || !users.length) {
      list.innerHTML = '<p style="font-size:0.78rem;color:#AAAAAA;line-height:1.4;padding:4px">Ninguém na sala nos últimos 2 min.</p>';
      return;
    }
    var html = '';
    for (var i = 0; i < users.length; i++) {
      var u = users[i];
      var oid = u.user_id || '';
      var href = '/features/profile/view.php?id=' + encodeURIComponent(oid);
      var av = (u.avatar_url || '').trim();
      var line = u.member_line || 'Membro';
      html += '<a class="uol-side-item" href="' + esc(href) + '">';
      html += '<span class="uol-side-dot" aria-hidden="true"></span>';
      if (av) {
        html += '<img class="uol-side-av" src="' + esc(av) + '" alt="">';
      } else {
        html += '<span class="uol-side-ph" aria-hidden="true">&#128100;</span>';
      }
      html += '<span class="uol-side-name">' + esc(line) + '</span></a>';
    }
    list.innerHTML = html;
  }

  function atualizarOnline() {
    fetch(EP.online + '?sala_id=' + encodeURIComponent(sala), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.ok && data.users) renderSideList(data.users);
      })
      .catch(function () {});
  }

  function atualizarPresenca() {
    fetch(EP.presence, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sala_id: sala })
    }).catch(function () {});
  }

  function buildChatFormData() {
    var ta = document.getElementById('input-mensagem');
    var fileEl = document.getElementById('input-arquivo');
    var text = (ta && ta.value) ? ta.value.trim() : '';
    var hasFile = fileEl && fileEl.files && fileEl.files.length > 0;
    var fd = new FormData();
    var salaInp = document.querySelector('#form-chat input[name="sala_id"]');
    var salaVal = (salaInp && salaInp.value) ? salaInp.value.trim() : sala;
    fd.append('sala_id', salaVal);
    fd.append('mensagem', text);
    if (hasFile)     fd.append('arquivo', fileEl.files[0]);
    return fd;
  }

  var formChat = document.getElementById('form-chat');
  if (formChat) {
    formChat.addEventListener('submit', async function (e) {
      e.preventDefault();
      var ta = document.getElementById('input-mensagem');
      var fileEl = document.getElementById('input-arquivo');
      var text = (ta && ta.value) ? ta.value.trim() : '';
      var hasFile = fileEl && fileEl.files && fileEl.files.length > 0;
      if (!text && !hasFile) return;
      if (__chatSendInFlight) return;
      var salaInp = document.querySelector('#form-chat input[name="sala_id"]');
      var salaVal = (salaInp && salaInp.value) ? salaInp.value.trim() : sala;
      var urls = [EP.send];
      if (EP.sendFallback && EP.sendFallback !== EP.send) urls.push(EP.sendFallback);
      var btnEnviar = document.getElementById('btn-enviar');
      var savedText = text;
      var fdMultipart = hasFile ? buildChatFormData() : null;
      __chatSendInFlight = true;
      if (ta) {
        ta.disabled = true;
        ta.value = '';
      }
      if (btnEnviar) {
        btnEnviar.disabled = true;
        btnEnviar.textContent = '⏳';
      }
      var sentOk = false;
      try {
        if (!hasFile) {
          for (var u = 0; u < urls.length; u++) {
            var res = await fetch(urls[u], {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ sala_id: salaVal, mensagem: savedText })
            });
            var j = null;
            try {
              j = await res.json();
            } catch (ex) {
              j = null;
            }
            var ok = j && (j.ok === true || j.success === true);
            if (res.ok && ok) {
              sentOk = true;
              if (j && j.message) {
                appendMessages([j.message], { forceScroll: true });
              } else {
                await carregarMensagens();
                scrollFeedBottom();
              }
              break;
            }
            if (res.status === 404 && u + 1 < urls.length) continue;
            var errMsg = (j && (j.message || j.detail)) ? String(j.message || j.detail) : ('HTTP ' + res.status);
            alert('Erro ao enviar: ' + errMsg);
            break;
          }
        } else {
          var fd = fdMultipart;
          for (var u2 = 0; u2 < urls.length; u2++) {
            try {
              var res2 = await fetch(urls[u2], { method: 'POST', credentials: 'same-origin', body: fd });
              var txt2 = await res2.text();
              var j2 = null;
              try { j2 = txt2 ? JSON.parse(txt2) : null; } catch (ex2) { j2 = null; }
              var ok2 = j2 && (j2.ok === true || j2.success === true);
              if (res2.ok && ok2) {
                sentOk = true;
                if (j2 && j2.message) {
                  appendMessages([j2.message], { forceScroll: true });
                } else {
                  await carregarMensagens();
                  scrollFeedBottom();
                }
                cancelarMidia();
                break;
              }
              if (res2.status === 404 && u2 + 1 < urls.length) continue;
              var msg2 = (j2 && (j2.message || j2.detail)) ? String(j2.message || j2.detail) : (txt2 || ('HTTP ' + res2.status));
              console.error('chat send', res2.status, j2 || txt2);
              alert('Erro ao enviar: ' + msg2);
              break;
            } catch (inner2) {
              if (u2 + 1 < urls.length) continue;
              throw inner2;
            }
          }
        }
        if (sentOk && !hasFile) {
          cancelarMidia();
        }
      } catch (err) {
        console.error('chat send', err);
        alert('Erro de conexão. Verifique sua internet.');
      } finally {
        __chatSendInFlight = false;
        if (ta) {
          ta.disabled = false;
          if (!sentOk) ta.value = savedText;
          ta.focus();
        }
        if (btnEnviar) {
          btnEnviar.disabled = false;
          btnEnviar.textContent = '🚀';
        }
      }
    });
  }
  var inputMsg = document.getElementById('input-mensagem');
  if (inputMsg) {
    inputMsg.addEventListener('input', enviarTypingSeNecessario);
  }

  async function chatPageBoot() {
    bindReactButtons(document);
    scrollFeedBottom();
    await carregarMensagens();
    scrollFeedBottom();
    setInterval(function () {
      carregarMensagens().catch(function () {});
    }, 3000);
    setInterval(atualizarTypingStatus, 2000);
    setInterval(atualizarOnline, 15000);
    setInterval(atualizarPresenca, 30000);
    atualizarPresenca();
    atualizarOnline();
    atualizarTypingStatus();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { chatPageBoot(); });
  } else {
    chatPageBoot();
  }

  var side = document.getElementById('uolSidebar');
  var btnPart = document.getElementById('btnParticipantesMobile');
  var back = document.getElementById('uolSidebarBackdrop');
  function closeSide() {
    if (side) side.classList.remove('is-open');
    if (back) back.classList.remove('is-on');
    if (btnPart) btnPart.setAttribute('aria-expanded', 'false');
  }
  function openSide() {
    if (side) side.classList.add('is-open');
    if (back) back.classList.add('is-on');
    if (btnPart) btnPart.setAttribute('aria-expanded', 'true');
  }
  window.toggleParticipantes = function () {
    if (!side) return;
    if (side.classList.contains('is-open')) closeSide(); else openSide();
  };
  if (back) back.addEventListener('click', closeSide);
})();
</script>
</body>
</html>
