<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/csrf.php';

$current_user_id = (string) ($_SESSION['user_id'] ?? '');
$com = isset($_GET['com']) ? trim((string) $_GET['com']) : '';
$csrf = csrf_token();
$apiLista = '/features/messages/messages_api.php?r=lista';
$apiBase = '/features/messages/messages_api.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mensagens — Club61</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{
  background:#0A0A0A;color:#fff;font-family:'Segoe UI',system-ui,sans-serif;
  min-height:100dvh;display:flex;flex-direction:column;
}
.msg-top{
  flex-shrink:0;display:flex;align-items:center;gap:12px;padding:12px 14px;
  border-bottom:1px solid #2a2a2a;background:#0A0A0A;
}
.msg-top a{color:#AAAAAA;text-decoration:none;font-size:1.2rem}
.msg-top h1{flex:1;font-size:1rem;font-weight:700;color:#C9A84C;text-align:center}
.msg-shell{
  flex:1;display:flex;min-height:0;max-width:900px;width:100%;margin:0 auto;
}
.msg-list-col{
  flex:0 0 min(100%,360px);border-right:1px solid #2a2a2a;display:flex;flex-direction:column;min-width:0;
  background:#0A0A0A;
}
.msg-list{flex:1;overflow-y:auto;min-height:0}
.msg-row{
  display:flex;align-items:center;gap:10px;padding:12px 14px;border-bottom:1px solid #1a1a1a;
  cursor:pointer;text-decoration:none;color:inherit;
}
.msg-row:hover,.msg-row.is-active{background:#151515}
.msg-row-av{width:48px;height:48px;border-radius:50%;object-fit:cover;background:#111;border:1px solid #2a2a2a;flex-shrink:0}
.msg-row-ph{width:48px;height:48px;border-radius:50%;background:#111;border:1px solid #2a2a2a;display:flex;align-items:center;justify-content:center;color:#7B2EFF;flex-shrink:0}
.msg-row-meta{flex:1;min-width:0}
.msg-row-name{font-size:0.88rem;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.msg-row-preview{font-size:0.78rem;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
.msg-row-time{font-size:0.68rem;color:#666;flex-shrink:0}
.msg-row-badge{background:#7B2EFF;color:#fff;font-size:0.65rem;font-weight:800;padding:2px 6px;border-radius:999px;min-width:18px;text-align:center}
.msg-chat-col{
  flex:1;min-width:0;display:flex;flex-direction:column;min-height:0;background:#0A0A0A;
}
.msg-chat-col.is-hidden{display:none}
.msg-chat-head{
  flex-shrink:0;padding:10px 14px;border-bottom:1px solid #2a2a2a;display:flex;align-items:center;gap:10px;
}
.msg-chat-head-back{display:none;background:none;border:none;color:#C9A84C;font-size:1rem;cursor:pointer;padding:4px}
.msg-feed{
  flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:8px;min-height:0;
}
.msg-bubble{max-width:82%;padding:10px 12px;border-radius:14px;font-size:0.9rem;line-height:1.4;word-break:break-word}
.msg-bubble.me{align-self:flex-end;background:#7B2EFF;color:#fff;border-bottom-right-radius:4px}
.msg-bubble.them{align-self:flex-start;background:#1a1a1a;border:1px solid #2a2a2a;color:#eee;border-bottom-left-radius:4px}
.msg-inputbar{
  flex-shrink:0;padding:10px 12px;border-top:1px solid #2a2a2a;display:flex;gap:8px;align-items:flex-end;
  padding-bottom:calc(10px + env(safe-area-inset-bottom,0px));
}
.msg-inputbar textarea{
  flex:1;min-height:44px;max-height:120px;background:#111;border:1px solid #2a2a2a;border-radius:12px;color:#fff;padding:10px 12px;font-size:0.9rem;resize:none;font-family:inherit;
}
.msg-inputbar textarea:focus{outline:none;border-color:#7B2EFF}
.msg-send{
  flex-shrink:0;width:44px;height:44px;border-radius:12px;border:none;background:#7B2EFF;color:#fff;cursor:pointer;font-size:1.1rem;
}
.msg-empty{padding:40px 20px;text-align:center;color:#666;font-size:0.88rem}
.msg-empty-list{padding:24px;color:#666;font-size:0.85rem;text-align:center}
@media (max-width:767px){
  .msg-shell{flex-direction:row;position:relative}
  .msg-list-col{width:100%;flex:1;border-right:none}
  .msg-list-col.is-backstage{display:none}
  .msg-chat-col.is-full{position:absolute;inset:0;z-index:20}
  .msg-chat-head-back{display:inline-block}
}
@media (min-width:768px){
  .msg-chat-col.is-hidden{display:flex}
}
</style>
</head>
<body>
<header class="msg-top">
  <a href="/features/feed/index.php" aria-label="Feed">←</a>
  <h1>Mensagens</h1>
  <span style="width:28px"></span>
</header>
<div class="msg-shell">
  <aside class="msg-list-col" id="msgListCol">
    <div class="msg-list" id="msgList"><div class="msg-empty-list">Carregando…</div></div>
  </aside>
  <section class="msg-chat-col is-hidden" id="msgChatCol">
    <div class="msg-chat-head">
      <button type="button" class="msg-chat-head-back" id="msgChatBack" aria-label="Voltar">←</button>
      <span id="msgChatTitle" style="font-weight:700;color:#C9A84C;font-size:0.95rem"></span>
    </div>
    <div class="msg-feed" id="msgFeed"></div>
    <form class="msg-inputbar" id="msgForm" autocomplete="off">
      <textarea id="msgTa" rows="1" placeholder="Mensagem…" maxlength="4000"></textarea>
      <button type="submit" class="msg-send" aria-label="Enviar">➤</button>
    </form>
  </section>
</div>
<script>
(function(){
  var CSRF = <?= club61_json_for_script($csrf) ?>;
  var ME = <?= club61_json_for_script($current_user_id) ?>;
  var COM = <?= club61_json_for_script($com) ?>;
  var API = <?= club61_json_for_script('/features/messages/messages_api.php') ?>;

  var listEl = document.getElementById('msgList');
  var feedEl = document.getElementById('msgFeed');
  var chatCol = document.getElementById('msgChatCol');
  var listCol = document.getElementById('msgListCol');
  var titleEl = document.getElementById('msgChatTitle');
  var form = document.getElementById('msgForm');
  var ta = document.getElementById('msgTa');
  var activeCom = '';

  function esc(s){
    var d = document.createElement('div'); d.textContent = s; return d.innerHTML;
  }

  function fmtTime(iso){
    if (!iso) return '';
    try {
      var d = new Date(iso);
      return d.toLocaleString('pt-BR', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
    } catch(e){ return ''; }
  }

  function loadLista(){
    fetch(API + '?r=lista', { credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d || !d.ok || !d.conversas) { listEl.innerHTML = '<div class="msg-empty-list">Nenhuma conversa.</div>'; return; }
        if (!d.conversas.length) { listEl.innerHTML = '<div class="msg-empty-list">Nenhuma conversa ainda.</div>'; return; }
        var html = '';
        d.conversas.forEach(function(c){
          var uid = c.user_id || '';
          var lab = c.label || 'Membro';
          var av = (c.avatar_url || '').trim();
          var un = c.unread > 0 ? '<span class="msg-row-badge">' + (c.unread > 9 ? '9+' : c.unread) + '</span>' : '';
          html += '<a class="msg-row" href="/features/messages/index.php?com=' + encodeURIComponent(uid) + '" data-uid="' + esc(uid) + '">';
          if (av) html += '<img class="msg-row-av" src="' + esc(av) + '" alt="">';
          else html += '<div class="msg-row-ph">&#128100;</div>';
          html += '<div class="msg-row-meta"><div class="msg-row-name">' + esc(lab) + '</div>';
          html += '<div class="msg-row-preview">' + esc((c.last_message || '').slice(0, 80)) + '</div></div>';
          html += un + '<span class="msg-row-time">' + esc(fmtTime(c.last_at)) + '</span></a>';
        });
        listEl.innerHTML = html;
        listEl.querySelectorAll('.msg-row').forEach(function(row){
          row.addEventListener('click', function(ev){
            ev.preventDefault();
            openChat(row.getAttribute('data-uid'));
          });
        });
        if (COM) openChat(COM);
      })
      .catch(function(){ listEl.innerHTML = '<div class="msg-empty-list">Erro ao carregar.</div>'; });
  }

  function openChat(uid){
    if (!uid) return;
    activeCom = uid;
    chatCol.classList.remove('is-hidden');
    var mq = window.matchMedia('(max-width:767px)');
    if (mq.matches) {
      chatCol.classList.add('is-full');
      listCol.classList.add('is-backstage');
    } else {
      chatCol.classList.remove('is-full');
      listCol.classList.remove('is-backstage');
    }
    titleEl.textContent = '…';
    feedEl.innerHTML = '';
    fetch(API + '?r=conversa&com=' + encodeURIComponent(uid), { credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d || !d.ok) return;
        fetch(API + '?r=lida', {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          credentials:'same-origin',
          body: JSON.stringify({ sender_id: uid, csrf: CSRF })
        }).catch(function(){});
        var rows = d.mensagens || [];
        rows.forEach(function(m){
          var mine = (m.sender_id || '') === ME;
          var div = document.createElement('div');
          div.className = 'msg-bubble ' + (mine ? 'me' : 'them');
          div.textContent = m.content || '';
          feedEl.appendChild(div);
        });
        feedEl.scrollTop = feedEl.scrollHeight;
        var row = listEl.querySelector('[data-uid="' + uid.replace(/"/g, '') + '"]');
        if (row) {
          var nm = row.querySelector('.msg-row-name');
          titleEl.textContent = nm ? nm.textContent : uid;
        }
      });
  }

  document.getElementById('msgChatBack').addEventListener('click', function(){
    chatCol.classList.add('is-hidden');
    chatCol.classList.remove('is-full');
    listCol.classList.remove('is-backstage');
    activeCom = '';
    history.replaceState(null, '', '/features/messages/index.php');
  });

  form.addEventListener('submit', function(ev){
    ev.preventDefault();
    if (!activeCom) return;
    var text = (ta.value || '').trim();
    if (!text) return;
    fetch(API + '?r=enviar', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify({ receiver_id: activeCom, conteudo: text, csrf: CSRF })
    })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d || !d.ok) return;
        ta.value = '';
        var div = document.createElement('div');
        div.className = 'msg-bubble me';
        div.textContent = text;
        feedEl.appendChild(div);
        feedEl.scrollTop = feedEl.scrollHeight;
        loadLista();
      });
  });

  loadLista();
  setInterval(function(){
    if (!activeCom) return;
    fetch(API + '?r=conversa&com=' + encodeURIComponent(activeCom), { credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d || !d.ok || !d.mensagens) return;
        feedEl.innerHTML = '';
        d.mensagens.forEach(function(m){
          var mine = (m.sender_id || '') === ME;
          var div = document.createElement('div');
          div.className = 'msg-bubble ' + (mine ? 'me' : 'them');
          div.textContent = m.content || '';
          feedEl.appendChild(div);
        });
        feedEl.scrollTop = feedEl.scrollHeight;
      });
  }, 3000);
  setInterval(loadLista, 15000);
})();
</script>
</body>
</html>
