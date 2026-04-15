<?php

declare(strict_types=1);



require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/city_rooms.php';

$rooms = club61_city_rooms();

/**
 * Metadata visual por sala (mantém fallback seguro se surgir nova cidade).
 *
 * @return array{initials:string,subtitle:string}
 */
function room_visual_meta(array $room): array
{
    $nome = mb_strtolower(trim((string) ($room['nome'] ?? '')), 'UTF-8');
    if (str_contains($nome, 'presidente prudente')) {
        return [
            'initials' => 'PP',
            'subtitle' => 'Bate-papo do Oeste Paulista',
        ];
    }
    if (str_contains($nome, 'maring')) {
        return [
            'initials' => 'MA',
            'subtitle' => 'Bate-papo da Capital do Noroeste',
        ];
    }
    if (str_contains($nome, 'londrina')) {
        return [
            'initials' => 'LO',
            'subtitle' => 'Bate-papo da Capital do Norte',
        ];
    }

    return [
        'initials' => 'CL',
        'subtitle' => 'Sala oficial do Club61',
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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
  display:grid;grid-template-columns:1fr;gap:24px;padding:28px;max-width:600px;margin:0 auto;
}
.card{
  background:#111;border:1px solid #222;border-radius:16px;padding:30px 20px;text-align:center;text-decoration:none;color:#fff;
  transition:transform .2s,box-shadow .2s,border-color .2s;display:block;min-height:220px;
}
.card:hover{transform:translateY(-4px);box-shadow:0 12px 30px rgba(123,46,255,.32);border-color:#333}
.icon{
  width:100px;height:100px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:3rem;margin:0 auto 16px;
  box-shadow:0 8px 22px rgba(0,0,0,.35);
  color:#fff;font-weight:800;letter-spacing:.04em;
}
.name{font-weight:800;font-size:1.3rem;margin-bottom:8px;letter-spacing:.01em}
.subtitle{color:#9a9a9a;font-size:.82rem;line-height:1.4;min-height:36px;margin-bottom:12px}
.online{
  color:#9cf7c4;font-size:.85rem;margin-bottom:14px;display:inline-flex;align-items:center;gap:7px;
  background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.22);padding:5px 10px;border-radius:999px;
}
.online-dot{
  width:9px;height:9px;border-radius:50%;background:#22c55e;display:inline-block;animation:onlinePulse 1.35s ease-in-out infinite;
  box-shadow:0 0 0 0 rgba(34,197,94,.55);
}
@keyframes onlinePulse{
  0%{transform:scale(1);box-shadow:0 0 0 0 rgba(34,197,94,.55)}
  70%{transform:scale(1.05);box-shadow:0 0 0 8px rgba(34,197,94,0)}
  100%{transform:scale(1);box-shadow:0 0 0 0 rgba(34,197,94,0)}
}
.btn{
  background:#7B2EFF;color:#fff;border:none;border-radius:12px;padding:12px 22px;font-size:1rem;cursor:pointer;width:100%;
  text-decoration:none;display:inline-block;font-family:inherit;font-weight:800;transition:box-shadow .22s,transform .15s,filter .18s;
}
.btn:hover{box-shadow:0 0 24px rgba(123,46,255,.55),0 0 40px rgba(123,46,255,.2);filter:brightness(1.06)}
.btn:active{transform:translateY(1px)}
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
  <?php $meta = room_visual_meta($room); ?>
  <a class="card" href="/features/chat/general.php?sala=<?= rawurlencode($room['slug']) ?>">
    <div class="icon" style="background:<?= htmlspecialchars($room['cor'], ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars($meta['initials'], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div class="name"><?= htmlspecialchars($room['nome'], ENT_QUOTES, 'UTF-8') ?></div>
    <div class="subtitle"><?= htmlspecialchars($meta['subtitle'], ENT_QUOTES, 'UTF-8') ?></div>
    <div class="online"><span class="online-dot" aria-hidden="true"></span>Online</div>
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
