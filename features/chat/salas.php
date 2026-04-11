<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/auth_guard.php';
require_once dirname(__DIR__, 2) . '/config/city_rooms.php';

$rooms = club61_city_rooms();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Salas — Club61</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%;min-height:100dvh}
body{
  background:#0A0A0A;color:#fff;font-family:'Segoe UI',system-ui,sans-serif;
  padding:0 14px 72px;padding-bottom:calc(72px + env(safe-area-inset-bottom,0px));
  max-width:960px;margin:0 auto;width:100%;
}
.ch-top{
  flex-shrink:0;width:100%;display:flex;align-items:center;justify-content:space-between;
  padding:10px 0;background:#0A0A0A;border-bottom:1px solid #1a1a1a;gap:8px;margin:0 -14px 20px;padding-left:14px;padding-right:14px;
}
.ch-top a{color:#aaa;text-decoration:none;font-size:1.2rem;padding:4px}
.ch-top a:hover{color:#C9A84C}
.ch-title-wrap{display:flex;align-items:center;gap:8px;flex:1;justify-content:center}
.ch-title{font-size:1rem;font-weight:700;color:#C9A84C}
.salas-grid{
  display:grid;grid-template-columns:1fr;gap:16px;
}
@media (min-width:768px){
  .salas-grid{grid-template-columns:repeat(3,1fr);gap:18px}
}
.sala-card{
  background:#111;border:1px solid #222;border-radius:14px;padding:22px 18px 18px;
  display:flex;flex-direction:column;align-items:center;text-align:center;
  transition:box-shadow .2s ease, transform .2s ease;
}
.sala-card:hover{
  box-shadow:0 6px 28px rgba(123,46,255,.22);
  transform:translateY(-2px);
}
.sala-icon{
  width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-size:2.25rem;line-height:1;margin-bottom:14px;
}
.sala-nome{font-size:1rem;font-weight:700;color:#fff;margin-bottom:10px}
.sala-badge{
  font-size:0.78rem;font-weight:700;color:#00ff88;margin-bottom:16px;
  letter-spacing:0.02em;
}
.sala-btn{
  display:inline-block;width:100%;max-width:220px;padding:11px 16px;border:none;border-radius:10px;
  background:#7B2EFF;color:#fff;font-size:0.92rem;font-weight:700;cursor:pointer;text-decoration:none;
  font-family:inherit;text-align:center;
}
.sala-btn:hover{filter:brightness(1.08);box-shadow:0 4px 16px rgba(123,46,255,.35)}
.bottomnav{
  position:fixed;left:0;right:0;bottom:0;z-index:300;
  display:flex;align-items:center;justify-content:space-around;
  height:56px;padding:0 4px;padding-bottom:env(safe-area-inset-bottom,0px);
  background:#0A0A0A;border-top:1px solid #1a1a1a;max-width:960px;margin:0 auto;
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
  <a href="/features/feed/index.php" aria-label="Voltar">←</a>
  <div class="ch-title-wrap"><span aria-hidden="true">🏙️</span><span class="ch-title">Salas</span></div>
  <a href="/features/chat/inbox.php" aria-label="Mensagens">✉️</a>
</header>

<div class="salas-grid" role="list">
<?php foreach ($rooms as $room): ?>
  <article class="sala-card" role="listitem">
    <div class="sala-icon" style="background:<?= htmlspecialchars($room['cor'], ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars($room['emoji'], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div class="sala-nome"><?= htmlspecialchars($room['nome'], ENT_QUOTES, 'UTF-8') ?></div>
    <div class="sala-badge">online</div>
    <a class="sala-btn" href="/features/chat/general.php?sala=<?= rawurlencode($room['slug']) ?>">Entrar</a>
  </article>
<?php endforeach; ?>
</div>

<nav class="bottomnav" aria-label="Navegação">
  <a href="/features/feed/index.php"><span>🏠</span>Feed</a>
  <a href="/features/profile/upload_story.php"><span>📷</span>Story</a>
  <a class="is-active" href="/features/chat/salas.php"><span>🏙️</span>Salas</a>
  <a href="/features/profile/index.php"><span>👤</span>Perfil</a>
  <a href="/features/auth/logout.php"><span>🚪</span>Sair</a>
</nav>
</body>
</html>
