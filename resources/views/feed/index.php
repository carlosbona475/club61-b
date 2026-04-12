<?php
declare(strict_types=1);

use Club61\Support\FeedFormatting;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Feed — Club61</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{height:100%}
body{
  background:#0A0A0A;color:#fff;font-family:'Segoe UI',system-ui,sans-serif;
  min-height:100%;padding-bottom:72px;padding-bottom:calc(72px + env(safe-area-inset-bottom,0px));
}
a{color:inherit}
.topnav{
  position:sticky;top:0;z-index:200;
  display:flex;align-items:center;justify-content:space-between;
  height:54px;padding:0 14px 0 16px;
  background:#0A0A0A;border-bottom:1px solid #1a1a1a;
}
.topnav-brand{font-size:1.15rem;font-weight:800;color:#C9A84C;letter-spacing:0.12em;text-decoration:none}
.topnav-right{display:flex;align-items:center;gap:10px}
.topnav-count{font-size:0.72rem;color:#555;font-weight:500}
.topnav-profile{display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:#111;border:1px solid #222;color:#888;text-decoration:none;font-size:1.1rem}
.topnav-profile:hover{color:#C9A84C;border-color:#333}
/* Localização: header (desktop) */
.feed-loc-nav-btn{
  display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;
  background:#111;border:1px solid #2a2a2a;color:#C9A84C;text-decoration:none;
  transition:box-shadow .25s ease,border-color .2s ease,color .2s ease;
}
.feed-loc-nav-btn:hover,.feed-loc-nav-btn:focus-visible{
  border-color:#444;color:#e8d5a3;
  box-shadow:0 0 14px rgba(201,168,76,0.35),0 0 22px rgba(123,46,255,0.15);
  outline:none;
}
.feed-loc-nav-btn svg{display:block;width:18px;height:18px}
/* FAB localização (mobile) — acima da bottomnav */
.feed-loc-fab{
  position:fixed;right:14px;z-index:310;
  bottom:calc(56px + env(safe-area-inset-bottom,0px) + 12px);
  width:44px;height:44px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  background:#111;border:1px solid #2a2a2a;color:#C9A84C;
  text-decoration:none;cursor:pointer;
  box-shadow:0 4px 16px rgba(0,0,0,0.45);
  transition:box-shadow .25s ease,border-color .2s ease,transform .15s ease,color .2s ease;
  -webkit-tap-highlight-color:transparent;
}
.feed-loc-fab:hover,.feed-loc-fab:focus-visible{
  border-color:#555;color:#e8d5a3;
  box-shadow:0 0 18px rgba(201,168,76,0.4),0 0 28px rgba(123,46,255,0.2),0 4px 16px rgba(0,0,0,0.5);
  outline:none;
}
.feed-loc-fab:active{transform:scale(0.96)}
.feed-loc-fab svg{width:20px;height:20px;display:block}
@keyframes feedLocPulse{
  0%{box-shadow:0 0 0 0 rgba(201,168,76,0.45),0 4px 16px rgba(0,0,0,0.45)}
  70%{box-shadow:0 0 0 10px rgba(201,168,76,0),0 4px 16px rgba(0,0,0,0.45)}
  100%{box-shadow:0 0 0 0 rgba(201,168,76,0),0 4px 16px rgba(0,0,0,0.45)}
}
.feed-loc--pulse{
  animation:feedLocPulse 2s ease-out infinite;
}
@media (max-width:640px){
  .feed-loc-nav-btn{display:none!important}
}
@media (min-width:641px){
  .feed-loc-fab{display:none!important}
}
.toast{
  display:none;position:fixed;left:12px;right:12px;top:58px;z-index:250;
  padding:11px 14px;border-radius:10px;font-size:0.88rem;font-weight:500;
  animation:toastIn .25s ease;
}
@keyframes toastIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.toast.ok{background:#1a3a1f;color:#69db7c;border:1px solid rgba(105,219,124,0.25)}
.toast.error{background:#3a1a1a;color:#ff6b6b;border:1px solid rgba(255,107,107,0.25)}
.stories-wrap{width:100%;background:#0A0A0A;border-bottom:1px solid #141414}
.stories-scroll{
  display:flex;gap:14px;overflow-x:auto;overflow-y:hidden;padding:12px 14px 14px;
  -webkit-overflow-scrolling:touch;scrollbar-width:none;-ms-overflow-style:none;
}
.stories-scroll::-webkit-scrollbar{display:none;height:0}
.story-item{flex:0 0 auto;display:flex;flex-direction:column;align-items:center;gap:6px;min-width:64px}
.story-item--add{cursor:pointer}
.story-add-circle{
  box-sizing:border-box;width:64px;height:64px;border-radius:50%;
  border:2px dashed #333;background:#111;display:flex;align-items:center;justify-content:center;
  font-size:2rem;font-weight:300;color:#fff;text-decoration:none;transition:border-color .2s;
}
.story-item--add:hover .story-add-circle{border-color:#555}
.story-avatar-ring{
  box-sizing:border-box;width:64px;height:64px;border-radius:50%;
  border:3px solid transparent;
  background:linear-gradient(#0A0A0A,#0A0A0A) padding-box,linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888) border-box;
  background-clip:padding-box,border-box;overflow:hidden;display:flex;align-items:center;justify-content:center;
}
.story-avatar-img{width:100%;height:100%;object-fit:cover;border-radius:50%;display:block}
.story-avatar-fallback{width:100%;height:100%;background:#111;display:flex;align-items:center;justify-content:center;font-size:1.35rem;color:#7B2EFF;border-radius:50%}
.story-avatar-link{text-decoration:none;display:flex;flex-direction:column;align-items:center}
.avatar-wrapper{position:relative;display:inline-flex;align-items:center;justify-content:center}
.online-dot{position:absolute;bottom:2px;right:2px;width:10px;height:10px;background:#00ff88;border-radius:50%;border:2px solid #111}
.story-cl{font-size:0.65rem;color:#fff;max-width:76px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:center;opacity:0.92}
.story-cl--meu{color:#fff}
.feed-main{max-width:480px;margin:0 auto;width:100%}
.post-block{border-bottom:1px solid #141414}
.post-head{
  display:flex;align-items:center;gap:10px;padding:12px 14px 8px;
}
.post-head a.post-head-link{display:flex;align-items:center;gap:10px;text-decoration:none;flex:1;min-width:0}
.post-av{width:34px;height:34px;border-radius:50%;object-fit:cover;background:#111;flex-shrink:0;border:1px solid #222}
.post-av-fallback{width:34px;height:34px;border-radius:50%;background:#111;border:1px solid #222;display:flex;align-items:center;justify-content:center;font-size:0.95rem;color:#7B2EFF}
.post-head-meta{min-width:0}
.post-head-name{font-size:0.9rem;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.post-head-time{font-size:0.72rem;color:#555;margin-top:2px}
.post-img-wrap{width:100%;background:#111}
.post-img{width:100%;display:block;max-height:520px;object-fit:cover;vertical-align:middle}
.post-actions-row{padding:8px 14px 4px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.like-btn{
  background:none;border:none;cursor:pointer;font-size:1.35rem;line-height:1;padding:4px;
  color:#aaa;transition:transform .15s;
}
.like-btn:hover{transform:scale(1.08)}
.like-btn.is-liked{color:#ff4b6e}
.like-count{font-size:0.82rem;color:#bbb;font-weight:600;min-width:1.2em}
.post-comments{padding:0 14px 8px;font-size:0.82rem;color:#ccc}
.post-comments .comment-line{margin-bottom:6px;line-height:1.35}
.post-comments .comment-user{font-weight:600;color:#e8e8e8;margin-right:6px}
.post-comments-more{display:inline-block;margin-top:4px;font-size:0.78rem;color:#C9A84C;text-decoration:none}
.comment-bar{display:flex;gap:8px;padding:0 14px 12px;align-items:center}
.comment-bar input{
  flex:1;min-width:0;background:#141414;border:1px solid #2a2a2a;border-radius:20px;color:#fff;
  padding:8px 12px;font-size:0.85rem;outline:none;font-family:inherit
}
.comment-bar input:focus{border-color:#444}
.comment-bar button{
  flex-shrink:0;background:#1f1f1f;border:1px solid #333;color:#ddd;border-radius:20px;
  padding:8px 14px;font-size:0.8rem;font-weight:600;cursor:pointer;font-family:inherit
}
.comment-bar button:disabled{opacity:0.5;cursor:not-allowed}
.feed-pager{padding:16px 14px 24px;text-align:center}
.feed-pager a{color:#C9A84C;font-size:0.88rem;text-decoration:none}
.post-caption-block{padding:0 14px 12px;font-size:0.92rem;line-height:1.45;color:#ddd}
.post-caption-block .cap-user{font-weight:700;color:#fff;margin-right:6px}
.empty-feed{text-align:center;color:#555;padding:40px 20px;font-size:0.9rem}
.bottomnav{
  position:fixed;left:0;right:0;bottom:0;z-index:300;
  display:flex;align-items:center;justify-content:space-around;
  height:56px;padding:0 6px;padding-bottom:env(safe-area-inset-bottom,0px);
  background:#0A0A0A;border-top:1px solid #1a1a1a;
}
.bottomnav a,.bottomnav button.nav-fab-wrap{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  text-decoration:none;color:#888;font-size:0.62rem;gap:2px;background:none;border:none;cursor:pointer;padding:6px 8px;font-family:inherit;
}
.bottomnav a span,.bottomnav .nav-fab-wrap span{font-size:1.15rem;line-height:1}
.bottomnav a:hover,.bottomnav .nav-fab-wrap:hover{color:#ccc}
.bottomnav a.bnav-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;color:#888;font-size:0.62rem;text-decoration:none;padding:6px 8px}
.bottomnav a.bnav-btn span:first-child{font-size:1.15rem;line-height:1}
.nav-fab{
  width:44px;height:44px;border-radius:14px;background:#7B2EFF;color:#fff;border:none;
  font-size:1.5rem;font-weight:300;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;
  box-shadow:0 4px 16px rgba(123,46,255,0.35);
}
.nav-fab:hover{filter:brightness(1.08)}
.modal-backdrop{
  display:none;position:fixed;inset:0;z-index:400;background:rgba(0,0,0,0.65);
  backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);
  align-items:flex-end;justify-content:center;
}
.modal-backdrop.is-open{display:flex}
.modal-sheet{
  width:100%;max-width:480px;max-height:88vh;overflow:auto;
  background:#111;border:1px solid #222;border-bottom:none;border-radius:18px 18px 0 0;
  padding:12px 18px 24px;padding-bottom:calc(24px + env(safe-area-inset-bottom,0px));
  transform:translateY(100%);transition:transform .32s cubic-bezier(0.22,1,0.36,1);
}
.modal-backdrop.is-open .modal-sheet{transform:translateY(0)}
.modal-handle{width:40px;height:4px;background:#444;border-radius:4px;margin:4px auto 14px}
.modal-title{color:#C9A84C;font-size:1.05rem;font-weight:700;text-align:center;margin-bottom:16px;letter-spacing:0.06em}
#postFile{display:none}
.upload-zone{
  display:block;background:#1a1a1a;border:1px dashed #333;border-radius:10px;padding:28px 16px;text-align:center;color:#666;cursor:pointer;margin-bottom:12px;font-size:0.92rem;transition:border-color .2s;
}
.upload-zone:hover{border-color:#7B2EFF;color:#999}
#postPreview{display:none;width:100%;max-height:280px;object-fit:cover;border-radius:10px;margin-bottom:12px;border:1px solid #2a2a2a;background:#0d0d0d}
.caption-input{
  width:100%;background:#1a1a1a;border:1px solid #2a2a2a;border-radius:8px;color:#fff;padding:12px 14px;font-size:0.95rem;margin-bottom:12px;outline:none;resize:vertical;min-height:72px;font-family:inherit;
}
.caption-input:focus{border-color:#7B2EFF}
.caption-input::placeholder{color:#444}
.btn-post{width:100%;padding:13px;background:#7B2EFF;color:#fff;border:none;border-radius:8px;font-size:0.95rem;font-weight:700;cursor:pointer;margin-bottom:10px}
.btn-post:disabled{opacity:0.65;cursor:not-allowed}
.btn-cancel{width:100%;padding:11px;background:transparent;color:#888;border:1px solid #333;border-radius:8px;font-size:0.9rem;cursor:pointer}
.btn-cancel:hover{border-color:#555;color:#ccc}
</style>
</head>
<body>

<header class="topnav">
  <a class="topnav-brand" href="/features/feed/index.php">Club61</a>
  <div class="topnav-right">
    <span class="topnav-count"><?= (int) $membros_ativos ?> membros</span>
    <a class="feed-loc-nav-btn js-feed-loc" href="/features/location/index.php" title="Locais" aria-label="Descobrir locais próximos">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="2.5"/>
        <circle cx="12" cy="12" r="7" stroke-dasharray="3.5 3"/>
        <circle cx="12" cy="12" r="10" opacity="0.45"/>
        <path d="M12 2v2M12 20v2M2 12h2M20 12h2" opacity="0.6"/>
      </svg>
    </a>
    <a class="topnav-profile" href="/features/profile/index.php" title="Perfil" aria-label="Perfil">👤</a>
  </div>
</header>

<?php if ($status === 'ok' || $status === 'error'): ?>
<div id="feedToast" class="toast <?= $status === 'ok' ? 'ok' : 'error' ?>" role="status">
  <?= htmlspecialchars($message !== '' ? $message : ($status === 'ok' ? 'OK.' : 'Erro.'), ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<?php if ($access_token !== ''): ?>
<div class="stories-wrap">
  <div class="stories-scroll" role="list" aria-label="Stories">
    <a class="story-item story-item--add" href="/features/profile/upload_story.php" role="listitem">
      <div class="story-add-circle" aria-hidden="true">+</div>
      <span class="story-cl story-cl--meu">Story</span>
    </a>
    <?php foreach ($storyProfiles as $sp): ?>
      <?php
      $sid = isset($sp['id']) ? (string) $sp['id'] : '';
      $savatar = isset($sp['avatar_url']) ? trim((string) $sp['avatar_url']) : '';
      $sLastSeen = isset($sp['last_seen']) ? (string) $sp['last_seen'] : null;
      $sOnline = isUserOnline($sLastSeen);
      $sdisp = isset($sp['display_id']) ? trim((string) $sp['display_id']) : '';
      $suname = isset($sp['username']) ? trim((string) $sp['username']) : '';
      $scl = FeedFormatting::buildClLabel($sdisp, $suname !== '' ? '@' . $suname : '');
      if ($sid === '') {
          continue;
      }
      ?>
    <div class="story-item" role="listitem">
      <a class="story-avatar-link" href="/features/stories/view.php?user_id=<?= urlencode($sid) ?>" aria-label="Ver story">
        <div class="story-avatar-ring avatar-wrapper">
          <?php if ($savatar !== ''): ?>
            <img class="story-avatar-img" src="<?= htmlspecialchars($savatar, ENT_QUOTES, 'UTF-8') ?>" alt="">
          <?php else: ?>
            <div class="story-avatar-fallback" aria-hidden="true">&#128100;</div>
          <?php endif; ?>
          <?php if ($sOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?>
        </div>
        <span class="story-cl"><?= htmlspecialchars($scl, ENT_QUOTES, 'UTF-8') ?></span>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="feed-main">
  <?php if (empty($posts)): ?>
    <div class="empty-feed">Nenhum post ainda. Seja o primeiro a publicar!</div>
  <?php else: ?>
    <?php foreach ($posts as $post): ?>
      <?php
      $pid = (int) ($post['id'] ?? 0);
      $authorId = isset($post['user_id']) ? (string) $post['user_id'] : '';
      $prof = $authorId !== '' && isset($postAuthorById[$authorId]) ? $postAuthorById[$authorId] : null;
      $pdisp = $prof && isset($prof['display_id']) ? trim((string) $prof['display_id']) : '';
      $puname = $prof && isset($prof['username']) ? trim((string) $prof['username']) : '';
      $authorLabel = FeedFormatting::buildClLabel($pdisp, $puname !== '' ? '@' . $puname : 'Membro');
      $pavatar = $prof && !empty($prof['avatar_url']) ? trim((string) $prof['avatar_url']) : '';
      $pLastSeen = $prof && isset($prof['last_seen']) ? (string) $prof['last_seen'] : null;
      $pOnline = isUserOnline($pLastSeen);
      $createdRaw = isset($post['created_at']) ? (string) $post['created_at'] : '';
      $relTime = FeedFormatting::relativeTime($createdRaw);
      $isLiked = isset($likedPostIds[$pid]);
      $likeTotal = (int) ($likesCountMap[(string) $pid] ?? 0);
      $rawComments = isset($commentsByPost[$pid]) && is_array($commentsByPost[$pid]) ? $commentsByPost[$pid] : [];
      usort($rawComments, static function ($a, $b) {
          $ta = isset($a['created_at']) ? (string) $a['created_at'] : '';
          $tb = isset($b['created_at']) ? (string) $b['created_at'] : '';

          return strcmp($ta, $tb);
      });
      $profileViewUrl = '/features/profile/view.php?id=' . rawurlencode($authorId);
      ?>
    <article class="post-block" data-post-id="<?= (int) $pid ?>">
      <div class="post-head">
        <a class="post-head-link" href="<?= htmlspecialchars($profileViewUrl, ENT_QUOTES, 'UTF-8') ?>">
          <?php if ($pavatar !== ''): ?>
            <span class="avatar-wrapper">
              <img class="post-av" src="<?= htmlspecialchars($pavatar, ENT_QUOTES, 'UTF-8') ?>" alt="">
              <?php if ($pOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?>
            </span>
          <?php else: ?>
            <span class="post-av-fallback avatar-wrapper" aria-hidden="true">&#128100;<?php if ($pOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?></span>
          <?php endif; ?>
          <div class="post-head-meta">
            <div class="post-head-name"><?= htmlspecialchars($authorLabel, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="post-head-time"><?= htmlspecialchars($relTime, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </a>
      </div>
      <?php if (!empty($post['image_url'])): ?>
      <div class="post-img-wrap">
        <img class="post-img" src="<?= htmlspecialchars($post['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="">
      </div>
      <?php endif; ?>
      <div class="post-actions-row">
        <button type="button" class="like-btn<?= $isLiked ? ' is-liked' : '' ?>" data-like-btn data-post-id="<?= (int) $pid ?>"
          aria-pressed="<?= $isLiked ? 'true' : 'false' ?>" aria-label="<?= $isLiked ? 'Descurtir' : 'Curtir' ?>"><?= $isLiked ? '♥' : '♡' ?></button>
        <span class="like-count" data-like-count><?= (int) $likeTotal ?></span>
      </div>
      <div class="post-comments" data-comment-list data-post-id="<?= (int) $pid ?>">
        <?php foreach ($rawComments as $cr): ?>
          <?php
          $cuid = isset($cr['user_id']) ? (string) $cr['user_id'] : '';
            $cpr = $cuid !== '' && isset($commentProfiles[$cuid]) ? $commentProfiles[$cuid] : null;
            $cdisp = $cpr && isset($cpr['display_id']) ? trim((string) $cpr['display_id']) : '';
            $cuname = $cpr && isset($cpr['username']) ? trim((string) $cpr['username']) : '';
            $clab = $cdisp !== '' ? $cdisp : ($cuname !== '' ? '@' . $cuname : 'Membro');
            $ctxt = isset($cr['comment_text']) ? (string) $cr['comment_text'] : '';
          ?>
        <div class="comment-line" data-comment-id="<?= htmlspecialchars(isset($cr['id']) ? (string) $cr['id'] : '', ENT_QUOTES, 'UTF-8') ?>">
          <span class="comment-user"><?= htmlspecialchars($clab, ENT_QUOTES, 'UTF-8') ?></span><?= htmlspecialchars($ctxt, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endforeach; ?>
      </div>
      <a class="post-comments-more" href="/features/feed/post_comments.php?post_id=<?= (int) $pid ?>">Ver todos os comentários</a>
      <form class="comment-bar" data-comment-form data-post-id="<?= (int) $pid ?>" action="#" method="post" onsubmit="return false;">
        <input type="text" name="comment" maxlength="2000" placeholder="Adicione um comentário..." autocomplete="off" aria-label="Comentário">
        <button type="submit">Enviar</button>
      </form>
      <?php if (!empty($post['caption'])): ?>
      <div class="post-caption-block">
        <span class="cap-user"><?= htmlspecialchars($authorLabel, ENT_QUOTES, 'UTF-8') ?></span><?= htmlspecialchars($post['caption'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php if (!empty($posts) && $feedHasOlder): ?>
  <div class="feed-pager">
    <a href="<?= htmlspecialchars($feedOlderUrl, ENT_QUOTES, 'UTF-8') ?>">Carregar publicações mais antigas</a>
  </div>
  <?php endif; ?>
</div>

<a class="feed-loc-fab js-feed-loc" href="/features/location/index.php" title="Locais" aria-label="Descobrir locais próximos">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <circle cx="12" cy="12" r="2.5"/>
    <circle cx="12" cy="12" r="7" stroke-dasharray="3.5 3"/>
    <circle cx="12" cy="12" r="10" opacity="0.45"/>
    <path d="M12 2v2M12 20v2M2 12h2M20 12h2" opacity="0.6"/>
  </svg>
</a>

<nav class="bottomnav" aria-label="Navegação principal">
  <a href="/feed"><span>🏠</span>Feed</a>
  <a href="/features/profile/upload_story.php"><span>📷</span>Story</a>
  <a class="bnav-btn" href="/chat/general"><span>💬</span><span>Chat</span></a>
  <button type="button" class="nav-fab-wrap" id="openPostModal" aria-label="Nova publicação"><span class="nav-fab" aria-hidden="true">＋</span><span style="font-size:0.6rem;opacity:0">.</span></button>
  <a href="/profile"><span>👤</span>Perfil</a>
  <?php if ($is_admin): ?>
  <a class="bnav-btn" href="/features/admin/index.php">
    <span>⚙️</span>
    <span>Admin</span>
  </a>
  <?php endif; ?>
  <a href="/logout"><span>🚪</span>Sair</a>
</nav>

<div id="postModalBackdrop" class="modal-backdrop" aria-hidden="true">
  <div class="modal-sheet" role="dialog" aria-labelledby="modalPostTitle" onclick="event.stopPropagation()">
    <div class="modal-handle" aria-hidden="true"></div>
    <h2 id="modalPostTitle" class="modal-title">Nova publicação</h2>
    <form id="modalPostForm" action="/feed/create-post" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <input type="file" id="postFile" name="image" accept="image/jpeg,image/png,image/webp" required>
      <div class="upload-zone" id="chooseArea" onclick="document.getElementById('postFile').click()">Toque para escolher imagem</div>
      <img id="postPreview" src="" alt="Prévia">
      <textarea class="caption-input" name="caption" rows="3" placeholder="Escreva uma legenda..."></textarea>
      <button class="btn-post" type="submit" id="btnPublish">Publicar</button>
      <button class="btn-cancel" type="button" id="btnCancelModal">Cancelar</button>
    </form>
  </div>
</div>

<script>
(function(){
  function feedToast(msg, type) {
    var el = document.getElementById('feedToast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'feedToast';
      el.className = 'toast';
      document.body.appendChild(el);
    }
    el.classList.remove('ok', 'error');
    el.classList.add(type === 'ok' ? 'ok' : 'error');
    el.textContent = msg;
    el.style.display = 'block';
    clearTimeout(feedToast._t);
    feedToast._t = setTimeout(function(){ el.style.display = 'none'; }, 3500);
  }
  function parseJsonSafe(resp) {
    return resp.text().then(function (txt) {
      try { return JSON.parse(txt); } catch (e) { return null; }
    });
  }
  function likeFallback(postId, liked) {
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = liked ? '/features/feed/unlike_post.php' : '/features/feed/like_post.php';
    var p = document.createElement('input');
    p.type = 'hidden';
    p.name = 'post_id';
    p.value = String(postId);
    var r = document.createElement('input');
    r.type = 'hidden';
    r.name = 'return_to';
    r.value = window.location.pathname + window.location.search;
    var c = document.createElement('input');
    c.type = 'hidden';
    c.name = 'csrf';
    c.value = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    f.appendChild(p);
    f.appendChild(r);
    f.appendChild(c);
    document.body.appendChild(f);
    f.submit();
  }
  try {
    var locKey = 'club61_location_updated_at';
    var raw = localStorage.getItem(locKey);
    var stale = true;
    if (raw) {
      var t = Date.parse(raw);
      if (!isNaN(t) && (Date.now() - t) < 10 * 60 * 1000) stale = false;
    }
    if (stale) {
      document.querySelectorAll('.js-feed-loc').forEach(function (el) {
        el.classList.add('feed-loc--pulse');
      });
    }
  } catch (e) {}
  var FEED_CSRF = <?= json_encode($feedCsrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var toast = document.getElementById('feedToast');
  if (toast) {
    toast.style.display = 'block';
    setTimeout(function(){ toast.style.display = 'none'; }, 3000);
  }
  document.querySelectorAll('[data-like-btn]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var pid = btn.getAttribute('data-post-id');
      if (!pid) return;
      var fd = new FormData();
      fd.append('post_id', pid);
      fd.append('csrf', FEED_CSRF);
      btn.disabled = true;
      fetch('/feed/toggle-like', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(parseJsonSafe)
        .then(function(d){
          if (!d) {
            likeFallback(pid, btn.classList.contains('is-liked'));
            return;
          }
          if (d.csrf) FEED_CSRF = d.csrf;
          if (!d.ok || (d.status !== 'liked' && d.status !== 'unliked')) {
            if (d.error === 'csrf') {
              feedToast('Sessão expirada. Atualizando página...', 'error');
              setTimeout(function(){ window.location.reload(); }, 600);
              return;
            }
            feedToast('Falha ao curtir. Tentando modo compatível...', 'error');
            likeFallback(pid, btn.classList.contains('is-liked'));
            return;
          }
          var card = btn.closest('.post-block');
          var cnt = card ? card.querySelector('[data-like-count]') : null;
          if (cnt && typeof d.likes_count === 'number') cnt.textContent = d.likes_count;
          btn.classList.toggle('is-liked', !!d.liked);
          btn.setAttribute('aria-pressed', d.liked ? 'true' : 'false');
          btn.setAttribute('aria-label', d.liked ? 'Descurtir' : 'Curtir');
          btn.innerHTML = d.liked ? '♥' : '♡';
        })
        .catch(function(){
          feedToast('Falha de rede ao curtir. Tentando modo compatível...', 'error');
          likeFallback(pid, btn.classList.contains('is-liked'));
        })
        .finally(function(){ btn.disabled = false; });
    });
  });
  document.querySelectorAll('[data-comment-form]').forEach(function(form){
    form.addEventListener('submit', function(ev){
      ev.preventDefault();
      var pid = form.getAttribute('data-post-id');
      var inp = form.querySelector('input[name="comment"]');
      if (!pid || !inp) return;
      var text = (inp.value || '').trim();
      if (!text) return;
      var btn = form.querySelector('button[type="submit"]');
      var fd = new FormData();
      fd.append('post_id', pid);
      fd.append('comment', text);
      fd.append('csrf', FEED_CSRF);
      if (btn) btn.disabled = true;
      fetch('/feed/add-comment', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(parseJsonSafe)
        .then(function(d){
          if (!d) {
            feedToast('Não foi possível comentar agora.', 'error');
            return;
          }
          if (d.csrf) FEED_CSRF = d.csrf;
          if (!d.ok || !d.html) {
            if (d.error === 'csrf') {
              feedToast('Sessão expirada. Atualizando página...', 'error');
              setTimeout(function(){ window.location.reload(); }, 600);
              return;
            }
            if (d.error === 'comments_unavailable') {
              feedToast('Comentários indisponíveis: execute o SQL de upgrade do feed.', 'error');
            } else if (d.error === 'rate_limit') {
              feedToast('Aguarde alguns segundos para comentar novamente.', 'error');
            } else {
              feedToast('Falha ao enviar comentário.', 'error');
            }
            return;
          }
          inp.value = '';
          var card = form.closest('.post-block');
          var list = card ? card.querySelector('[data-comment-list]') : null;
          if (list) {
            list.style.display = 'block';
            list.insertAdjacentHTML('beforeend', d.html);
            while (list.querySelectorAll('.comment-line').length > 3) {
              list.removeChild(list.firstChild);
            }
          }
        })
        .catch(function(){
          feedToast('Erro de rede ao comentar.', 'error');
        })
        .finally(function(){ if (btn) btn.disabled = false; });
    });
  });
  var backdrop = document.getElementById('postModalBackdrop');
  var openBtn = document.getElementById('openPostModal');
  var cancelBtn = document.getElementById('btnCancelModal');
  var form = document.getElementById('modalPostForm');
  var fileInput = document.getElementById('postFile');
  var chooseArea = document.getElementById('chooseArea');
  var preview = document.getElementById('postPreview');
  var btnPub = document.getElementById('btnPublish');
  function openM(){ backdrop.classList.add('is-open'); backdrop.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; }
  function closeM(){ backdrop.classList.remove('is-open'); backdrop.setAttribute('aria-hidden','true'); document.body.style.overflow=''; }
  if (openBtn) openBtn.addEventListener('click', openM);
  if (cancelBtn) cancelBtn.addEventListener('click', closeM);
  if (backdrop) backdrop.addEventListener('click', function(e){ if (e.target === backdrop) closeM(); });
  if (fileInput) fileInput.addEventListener('change', function(){
    if (this.files && this.files[0]) {
      var reader = new FileReader();
      reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
        chooseArea.style.display = 'none';
      };
      reader.readAsDataURL(this.files[0]);
    }
  });
  if (form) form.addEventListener('submit', function(){
    if (btnPub) { btnPub.disabled = true; btnPub.textContent = 'Publicando...'; }
  });
})();
</script>
</body>
</html>
