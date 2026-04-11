<?php
require_once dirname(__DIR__, 2) . '/auth_guard.php';
require_once dirname(__DIR__, 2) . '/config/supabase.php';
require_once dirname(__DIR__, 2) . '/config/profile_helper.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Configurações — Club61</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:#0A0A0A;color:#fff;font-family:'Segoe UI',system-ui,sans-serif;
     min-height:100vh;padding-bottom:72px;-webkit-font-smoothing:antialiased}
.topnav{position:sticky;top:0;z-index:100;background:#0A0A0A;
        border-bottom:1px solid #1a1a1a;display:flex;align-items:center;
        gap:12px;padding:0 16px;height:54px}
.back{background:none;border:none;color:#aaa;font-size:1.2rem;
      cursor:pointer;text-decoration:none;display:flex;align-items:center}
.back:hover{color:#fff}
.page-title{font-size:1rem;font-weight:700;color:#fff}
.wrap{max-width:480px;margin:0 auto;padding:24px 16px}
.section{margin-bottom:32px}
.section-title{font-size:0.72rem;font-weight:700;color:#555;
               letter-spacing:.08em;text-transform:uppercase;
               margin-bottom:12px;padding:0 4px}
.item{display:flex;align-items:center;justify-content:space-between;
      padding:14px 16px;background:#111;border-radius:12px;
      margin-bottom:8px;text-decoration:none;color:#fff;
      border:1px solid #1a1a1a;transition:background .15s}
.item:hover{background:#161616}
.item-left{display:flex;align-items:center;gap:12px;font-size:0.9rem}
.item-icon{font-size:1.1rem;width:20px;text-align:center}
.item-arrow{color:#333;font-size:0.9rem}
.item-danger{color:#ff6b6b;background:#1a1010;border-color:#2c1515}
.item-danger:hover{background:#211212}
.muted{color:#666;font-size:.78rem;padding:4px}
</style>
</head>
<body>

<header class="topnav">
  <a class="back" href="/features/feed/index.php" aria-label="Voltar">←</a>
  <div class="page-title">Configurações</div>
</header>

<main class="wrap">
  <section class="section">
    <div class="section-title">Conta</div>
    <a class="item" href="/features/profile/index.php">
      <div class="item-left"><span class="item-icon">👤</span><span>Editar perfil</span></div>
      <span class="item-arrow">›</span>
    </a>
    <a class="item" href="/features/profile/generate_invite.php">
      <div class="item-left"><span class="item-icon">🎟️</span><span>Convites</span></div>
      <span class="item-arrow">›</span>
    </a>
  </section>

  <section class="section">
    <div class="section-title">Privacidade</div>
    <a class="item" href="/features/profile/follow.php">
      <div class="item-left"><span class="item-icon">🔒</span><span>Seguidores</span></div>
      <span class="item-arrow">›</span>
    </a>
    <a class="item" href="/features/chat/message_requests_inbox.php">
      <div class="item-left"><span class="item-icon">💬</span><span>Solicitações de mensagem</span></div>
      <span class="item-arrow">›</span>
    </a>
  </section>

  <section class="section">
    <div class="section-title">Sessão</div>
    <a class="item item-danger" href="/features/auth/logout.php">
      <div class="item-left"><span class="item-icon">🚪</span><span>Sair da conta</span></div>
      <span class="item-arrow">›</span>
    </a>
    <p class="muted">Club61</p>
  </section>
</main>

</body>
</html>