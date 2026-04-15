<?php
require_once __DIR__ . '/config/session.php';
club61_session_start_safe();
$show_admin_nav = false;
if (is_file(__DIR__ . '/config/profile_helper.php')) {
    require_once __DIR__ . '/config/profile_helper.php';
    $show_admin_nav = function_exists('isCurrentUserAdmin') && isCurrentUserAdmin();
}
?>
<nav style="background:#111;border-bottom:1px solid #222;padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:60px;position:sticky;top:0;z-index:100">
  <a href="/features/feed/index.php" style="font-size:1.3rem;font-weight:800;color:#C9A84C;letter-spacing:2px;text-decoration:none">Club61</a>
  <ul style="display:flex;gap:24px;list-style:none;margin:0;padding:0;align-items:center">
    <li><a href="/features/feed/index.php" style="color:#888;text-decoration:none;font-size:0.9rem;font-weight:500">Feed</a></li>
    <li><a href="/features/profile/index.php" style="color:#888;text-decoration:none;font-size:0.9rem;font-weight:500">Perfil</a></li>
    <?php if ($show_admin_nav): ?>
    <li><a class="dark-btn" href="/admin" style="display:inline-block;padding:8px 14px;border-radius:8px;border:1px solid #333;background:#1a1a1a;color:#C9A84C;text-decoration:none;font-size:0.85rem;font-weight:600">⚙ Admin</a></li>
    <?php endif; ?>
    <li><a href="/features/auth/logout.php" style="color:#888;text-decoration:none;font-size:0.9rem;font-weight:500">Sair</a></li>
  </ul>
</nav>
