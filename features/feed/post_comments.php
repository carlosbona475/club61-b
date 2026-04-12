<?php

/**
 * Lista todos os comentários de um post (escape XSS).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap_path.php';
require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/feed_interactions.php';

$postId = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
if ($postId <= 0 || !feed_post_exists($postId)) {
    http_response_code(404);
    echo 'Post não encontrado.';

    exit;
}

$comments = feed_get_all_comments_for_post($postId, 200);
$userIds = [];
foreach ($comments as $c) {
    if (!empty($c['user_id'])) {
        $userIds[] = (string) $c['user_id'];
    }
}
$profiles = feed_fetch_profiles_by_ids($userIds);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Comentários — Club61</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:#0A0A0A;color:#e0e0e0;font-family:'Segoe UI',system-ui,sans-serif;padding:16px;max-width:480px;margin:0 auto;padding-bottom:48px}
a{color:#C9A84C}
h1{font-size:1rem;color:#C9A84C;margin-bottom:16px}
.comment{
  padding:12px 0;border-bottom:1px solid #1a1a1a;font-size:0.92rem;line-height:1.45;
}
.comment strong{color:#fff;font-weight:600}
.comment time{display:block;font-size:0.72rem;color:#555;margin-top:6px}
.back{display:inline-block;margin-bottom:14px;font-size:0.88rem}
</style>
</head>
<body>
<a class="back" href="/features/feed/index.php">← Feed</a>
<h1>Comentários</h1>
<?php if ($comments === []): ?>
  <p style="color:#666;font-size:0.9rem">Nenhum comentário ainda.</p>
<?php else: ?>
  <?php foreach ($comments as $c): ?>
    <?php

    $uid = isset($c['user_id']) ? (string) $c['user_id'] : '';
    $pr = $profiles[$uid] ?? [];
    $disp = isset($pr['display_id']) ? trim((string) $pr['display_id']) : '';
    $uname = isset($pr['username']) ? trim((string) $pr['username']) : '';
    $who = $disp !== '' ? $disp : ($uname !== '' ? '@' . $uname : 'Membro');
    $text = isset($c['comment_text']) ? (string) $c['comment_text'] : '';
    $ts = isset($c['created_at']) ? (string) $c['created_at'] : '';
    ?>
    <div class="comment">
      <strong><?= htmlspecialchars($who, ENT_QUOTES, 'UTF-8') ?></strong>
      <?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>
      <?php if ($ts !== ''): ?>
      <time datetime="<?= htmlspecialchars($ts, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ts, ENT_QUOTES, 'UTF-8') ?></time>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
