<?php

declare(strict_types=1);



require_once dirname(__DIR__, 2) . '/config/bootstrap_path.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/city_rooms.php';

$rooms = club61_city_rooms();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Salas — Club61</title>
<style>
*,*::before,*::after{box-sizing:border-box}
body{
  margin:0;background:#0A0A0A;color:#fff;font-family:'Segoe UI',sans-serif;
  padding-bottom:calc(56px + env(safe-area-inset-bottom,0px));
}
.header{
  background:#111;border-bottom:1px solid #222;padding:14px 20px;display:flex;align-items:center;gap:12px;
}
.header a{color:#aaa;text-decoration:none;font-size:1.2rem}
.header a:hover{color:#C9A84C}
.header h1{margin:0;font-size:1rem;font-weight:700;color:#C9A84C}
.grid{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;padding:24px;max-width:960px;margin:0 auto;
}
.card{
  background:#111;border:1px solid #222;border-radius:16px;padding:24px 16px;text-align:center;text-decoration:none;color:#fff;
  transition:transform .2s,box-shadow .2s;display:block;
}
.card:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(123,46,255,.3)}
.icon{
  width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 12px;
}
.name{font-weight:700;font-size:1rem;margin-bottom:6px}
.online{color:#00ff88;font-size:0.8rem;margin-bottom:12px}
.btn{
  background:#7B2EFF;color:#fff;border:none;border-radius:10px;padding:8px 20px;font-size:0.9rem;cursor:pointer;width:100%;
  text-decoration:none;display:inline-block;font-family:inherit;font-weight:700;
}
.bottomnav{
  position:fixed;bottom:0;left:0;right:0;background:#111;border-top:1px solid #222;display:flex;justify-content:space-around;
  padding:8px 0;padding-bottom:calc(8px + env(safe-area-inset-bottom,0px));z-index:300;max-width:960px;margin:0 auto;
}
.bottomnav a{color:#888;text-decoration:none;font-size:0.6rem;display:flex;flex-direction:column;align-items:center;gap:2px}
.bottomnav a.active{color:#7B2EFF}
.bottomnav a span:first-child{font-size:1.2rem}
</style>
</head>
<body>
<div class="header">
  <a href="/features/feed/index.php">&#8592;</a>
  <h1>Salas de Chat</h1>
</div>
<div class="grid">
<?php foreach ($rooms as $room): ?>
  <a class="card" href="/features/chat/general.php?sala=<?= rawurlencode($room['slug']) ?>">
    <div class="icon" style="background:<?= htmlspecialchars($room['cor'], ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars($room['emoji'], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div class="name"><?= htmlspecialchars($room['nome'], ENT_QUOTES, 'UTF-8') ?></div>
    <div class="online">online</div>
    <span class="btn">Entrar</span>
  </a>
<?php endforeach; ?>
</div>
<nav class="bottomnav" aria-label="Navegação">
  <a href="/features/feed/index.php"><span>🏠</span>Feed</a>
  <a href="/features/profile/upload_story.php"><span>📷</span>Story</a>
  <a class="active" href="/features/chat/salas.php"><span>🏙️</span>Salas</a>
  <a href="/features/profile/index.php"><span>👤</span>Perfil</a>
  <a href="/features/auth/logout.php"><span>🚪</span>Sair</a>
</nav>
</body>
</html>
