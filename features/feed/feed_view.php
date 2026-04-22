<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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
.topnav-profile{display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:#111;border:1px solid #222;color:#888;text-decoration:none;font-size:1.1rem;overflow:hidden;flex-shrink:0;-webkit-tap-highlight-color:transparent}
.topnav-profile:hover{color:#C9A84C;border-color:#333}
.topnav-profile img{width:100%;height:100%;object-fit:cover;display:block;border-radius:50%}
.topnav-profile.has-photo{padding:0;border-color:#2a2a2a}
.topnav-profile.has-photo:hover{border-color:#C9A84C}
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
  background:linear-gradient(#0A0A0A,#0A0A0A) padding-box,linear-gradient(135deg,#7B2EFF,#9B4DFF,#C9A84C) border-box;
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
.post-av-wrap{display:inline-flex;padding:2px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,#7B2EFF,#9B4DFF);box-sizing:border-box;align-items:center;justify-content:center}
.post-av-wrap .post-av,.post-av-wrap .post-av-fallback{border:none;width:32px;height:32px}
.post-av{width:34px;height:34px;border-radius:50%;object-fit:cover;background:#111;flex-shrink:0;border:1px solid #222}
.post-av-fallback{width:34px;height:34px;border-radius:50%;background:#111;border:1px solid #222;display:flex;align-items:center;justify-content:center;font-size:0.95rem;color:#7B2EFF}
.post-head-meta{min-width:0}
.post-head-name{font-size:0.9rem;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.post-head-time{font-size:0.72rem;color:#555;margin-top:2px}
.btn-delete-post{
  background:transparent;border:none;color:#ff4444;cursor:pointer;font-size:14px;
  opacity:0.6;transition:opacity 0.2s;flex-shrink:0;padding:4px;line-height:1;
}
.btn-delete-post:hover{opacity:1}
.post-menu-wrap{position:relative;margin-left:auto;flex-shrink:0}
.btn-post-menu{
  background:none;border:none;color:#888;cursor:pointer;
  font-size:1.3rem;padding:4px 8px;border-radius:6px;line-height:1;font-family:inherit;
}
.btn-post-menu:hover{color:#fff;background:rgba(255,255,255,0.07)}
.post-menu-dropdown{
  display:none;position:absolute;right:0;top:100%;z-index:200;
  background:#1a1a1a;border:1px solid #333;border-radius:10px;
  min-width:160px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,0.5);
}
.post-menu-dropdown.open{display:block}
.post-menu-item{
  display:block;width:100%;padding:12px 16px;background:none;border:none;
  color:#e8e8e8;font-size:0.9rem;text-align:left;cursor:pointer;font-family:inherit;
}
.post-menu-item:hover{background:rgba(255,255,255,0.07)}
.post-menu-item.danger{color:#e33}
.post-menu-item.danger:hover{background:rgba(227,51,51,0.1)}
.post-img-wrap{width:100%;background:#111}
.post-img{width:100%;display:block;max-height:520px;object-fit:cover;vertical-align:middle}
.post-video-wrap{width:100%;background:#000;position:relative;aspect-ratio:4/5;max-height:600px;overflow:hidden}
.post-video{width:100%;height:100%;object-fit:cover;display:block;cursor:pointer}
.post-video-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none}
.post-video-play{width:56px;height:56px;background:rgba(0,0,0,0.55);border-radius:50%;display:flex;align-items:center;justify-content:center;transition:opacity 0.2s}
.post-video-play svg{width:24px;height:24px;fill:#fff;margin-left:4px}
.post-video-wrap.playing .post-video-play{opacity:0}
.post-video-mute{position:absolute;bottom:10px;right:10px;width:32px;height:32px;background:rgba(0,0,0,0.55);border-radius:50%;border:none;color:#fff;font-size:0.85rem;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:2}
.post-actions-row{padding:8px 14px 4px;display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap}
.reactions-wrapper{display:inline-block;position:relative;max-width:100%}
.reactions-count{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px;min-height:0}
.reaction-badge{
  background:#1a1a1a;border:1px solid #2a2a2a;border-radius:20px;padding:2px 10px;font-size:13px;cursor:pointer;
  transition:all .2s;user-select:none;
}
.reaction-badge:hover{border-color:#7B2EFF}
.reaction-badge.minha{border-color:#7B2EFF;background:rgba(123,46,255,0.15)}
.btn-curtir{
  background:transparent;border:1px solid #2a2a2a;color:#aaa;padding:6px 14px;border-radius:20px;cursor:pointer;
  font-size:14px;transition:all .2s;font-family:inherit;display:inline-flex;align-items:center;gap:6px;user-select:none;
  -webkit-user-select:none;-webkit-touch-callout:none;
}
.btn-curtir:hover{border-color:#7B2EFF;color:#fff}
.btn-curtir.ativo{border-color:#7B2EFF;color:#7B2EFF;background:rgba(123,46,255,0.1)}
.btn-curtir.liked{border-color:#7B2EFF;color:#7B2EFF;background:rgba(123,46,255,0.1)}
.like-count{font-size:0.82rem;margin-left:2px;font-weight:600}
.btn-comment{
  background:transparent;border:1px solid #2a2a2a;color:#aaa;padding:6px 14px;border-radius:20px;cursor:pointer;
  font-size:14px;transition:all .2s;font-family:inherit;display:inline-flex;align-items:center;gap:6px;
}
.btn-comment:hover{border-color:#C9A84C;color:#fff}
.comment-count{font-size:0.82rem;font-weight:600}
.emoji-picker{
  position:absolute;bottom:44px;left:0;background:#1a1a1a;border:1px solid #2a2a2a;border-radius:30px;
  padding:8px 12px;display:flex;gap:8px;z-index:999;box-shadow:0 4px 20px rgba(0,0,0,0.5);white-space:nowrap;
}
.emoji-picker span{font-size:24px;cursor:pointer;transition:transform .15s;user-select:none;line-height:1}
.emoji-picker span:hover{transform:scale(1.4)}
.post-comments{padding:0 14px 8px;font-size:0.82rem;color:#ccc}
.post-comments .comment-line{margin-bottom:6px;line-height:1.35;word-break:break-word}
.post-comments .comment-user{font-weight:600;color:#e8e8e8;margin-right:6px}
.post-comments-more{display:inline-block;margin-top:4px;font-size:0.78rem;color:#C9A84C;text-decoration:none}
.btn-del-comment{
  background:none;border:none;color:#555;cursor:pointer;padding:0 4px;font-size:0.95rem;line-height:1;
  margin-left:6px;vertical-align:baseline;font-family:inherit;
}
.btn-del-comment:hover{color:#e33}
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
.nav-dm-inner{position:relative;display:inline-block}
.nav-dm-badge{
  position:absolute;top:-7px;right:-10px;min-width:16px;height:16px;padding:0 4px;border-radius:999px;
  background:#7B2EFF;color:#fff;font-size:0.58rem;font-weight:800;line-height:16px;text-align:center;
}
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
<?php
$feedMyAvatarUrl = isset($feedMyAvatarUrl) && is_string($feedMyAvatarUrl) ? trim($feedMyAvatarUrl) : '';
?>

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
    <a class="topnav-profile<?= $feedMyAvatarUrl !== '' ? ' has-photo' : '' ?>" href="/features/profile/index.php" title="Meu perfil" aria-label="Ir para o meu perfil">
      <?php if ($feedMyAvatarUrl !== ''): ?>
      <img src="<?= htmlspecialchars($feedMyAvatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
      <?php else: ?>
      <span aria-hidden="true">👤</span>
      <?php endif; ?>
    </a>
  </div>
</header>

<?php if ($status === 'ok' || $status === 'error'): ?>
<div id="feedToast" class="toast <?= $status === 'ok' ? 'ok' : 'error' ?>" role="status">
  <?= htmlspecialchars($message !== '' ? $message : ($status === 'ok' ? 'OK.' : 'Erro.'), ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<?php
if (!isset($feedStoryUserIds) || !is_array($feedStoryUserIds)) {
    $feedStoryUserIds = [];
}
?>
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
      $scl = FeedFormatting::buildClLabel($sdisp);
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
      $authorLabel = FeedFormatting::buildClLabel($pdisp);
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
      $authorHasStory = $authorId !== '' && !empty($feedStoryUserIds[$authorId]);
      ?>
    <article class="post-block" data-post-id="<?= (int) $pid ?>">
      <div class="post-head">
        <a class="post-head-link" href="<?= htmlspecialchars($profileViewUrl, ENT_QUOTES, 'UTF-8') ?>">
          <?php if ($pavatar !== ''): ?>
            <span class="avatar-wrapper<?= $authorHasStory ? ' post-av-wrap' : '' ?>">
              <img class="post-av" src="<?= htmlspecialchars($pavatar, ENT_QUOTES, 'UTF-8') ?>" alt="">
              <?php if ($pOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?>
            </span>
          <?php else: ?>
            <span class="post-av-fallback avatar-wrapper<?= $authorHasStory ? ' post-av-wrap' : '' ?>" aria-hidden="true">&#128100;<?php if ($pOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?></span>
          <?php endif; ?>
          <div class="post-head-meta">
            <div class="post-head-name"><?= htmlspecialchars($authorLabel, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="post-head-time"><?= htmlspecialchars($relTime, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </a>
        <?php if ($authorId !== '' && isset($current_user_id) && $authorId === (string) $current_user_id): ?>
        <div class="post-menu-wrap">
          <button type="button" class="btn-post-menu" data-post-id="<?= (int) $pid ?>" aria-label="Opções" aria-haspopup="menu">⋯</button>
          <div class="post-menu-dropdown" id="pmenu-<?= (int) $pid ?>" role="menu">
            <button type="button" class="post-menu-item btn-edit-post" data-post-id="<?= (int) $pid ?>" role="menuitem">✏️ Editar legenda</button>
            <button type="button" class="post-menu-item danger btn-delete-post" data-post-id="<?= (int) $pid ?>" role="menuitem">🗑️ Excluir post</button>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php if (!empty($post['caption'])): ?>
      <div class="post-caption-block">
        <span class="cap-user"><?= htmlspecialchars($authorLabel, ENT_QUOTES, 'UTF-8') ?></span><?= htmlspecialchars($post['caption'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif; ?>
      <?php if (!empty($post['video_url'])): ?>
      <div class="post-video-wrap" id="vwrap-<?= $pid ?>">
        <video class="post-video" src="<?= htmlspecialchars((string)$post['video_url'], ENT_QUOTES, 'UTF-8') ?>"
          playsinline muted loop preload="none"
          onclick="club61ToggleVideo(this)"></video>
        <div class="post-video-overlay">
          <div class="post-video-play">
            <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          </div>
        </div>
        <button type="button" class="post-video-mute" onclick="club61ToggleMute(this)" title="Som">🔇</button>
      </div>
      <?php elseif (!empty($post['image_url'])): ?>
      <div class="post-img-wrap">
        <img class="post-img" src="<?= htmlspecialchars($post['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="">
      </div>
      <?php endif; ?>
      <div class="post-actions-row">
        <div class="reactions-wrapper">
          <div class="reactions-count" id="reactions-<?= (int) $pid ?>"></div>
          <button type="button"
            class="btn-curtir js-like-btn<?= $isLiked ? ' liked' : '' ?>"
            data-post-id="<?= (int) $pid ?>"
            data-liked="<?= $isLiked ? '1' : '0' ?>"
            aria-pressed="<?= $isLiked ? 'true' : 'false' ?>"
            aria-label="Curtir publicação">
            <span class="like-icon" aria-hidden="true"><?= $isLiked ? '❤️' : '🤍' ?></span>
            <span class="like-count" id="lc-<?= (int) $pid ?>"><?= (int) $likeTotal ?></span>
          </button>
          <div class="emoji-picker" id="picker-<?= (int) $pid ?>" style="display:none;" aria-hidden="true">
            <span onclick="club61Reagir(<?= json_encode((string) (int) $pid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, <?= json_encode('❤️', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)" role="button" tabindex="0">❤️</span>
            <span onclick="club61Reagir(<?= json_encode((string) (int) $pid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, <?= json_encode('😂', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)" role="button" tabindex="0">😂</span>
            <span onclick="club61Reagir(<?= json_encode((string) (int) $pid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, <?= json_encode('😮', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)" role="button" tabindex="0">😮</span>
            <span onclick="club61Reagir(<?= json_encode((string) (int) $pid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, <?= json_encode('😢', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)" role="button" tabindex="0">😢</span>
            <span onclick="club61Reagir(<?= json_encode((string) (int) $pid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, <?= json_encode('🔥', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)" role="button" tabindex="0">🔥</span>
            <span onclick="club61Reagir(<?= json_encode((string) (int) $pid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, <?= json_encode('👏', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)" role="button" tabindex="0">👏</span>
          </div>
        </div>
        <button type="button" class="btn-comment js-comment-btn" data-post-id="<?= (int) $pid ?>" aria-label="Ir para comentários">
          💬 <span class="comment-count" id="cc-<?= (int) $pid ?>"><?= count($rawComments) ?></span>
        </button>
      </div>
      <div class="post-comments" data-comment-list data-post-id="<?= (int) $pid ?>">
        <?php foreach ($rawComments as $cr): ?>
          <?php
          $cuid = isset($cr['user_id']) ? (string) $cr['user_id'] : '';
            $cpr = $cuid !== '' && isset($commentProfiles[$cuid]) ? $commentProfiles[$cuid] : null;
            $cdisp = $cpr && isset($cpr['display_id']) ? trim((string) $cpr['display_id']) : '';
            $clab = FeedFormatting::buildClLabel($cdisp);
            $ctxt = isset($cr['comment_text']) ? (string) $cr['comment_text'] : '';
          ?>
        <div class="comment-line" data-comment-id="<?= htmlspecialchars(isset($cr['id']) ? (string) $cr['id'] : '', ENT_QUOTES, 'UTF-8') ?>">
          <span class="comment-user"><?= htmlspecialchars($clab, ENT_QUOTES, 'UTF-8') ?></span><?= htmlspecialchars($ctxt, ENT_QUOTES, 'UTF-8') ?><?php if ($cuid !== '' && isset($current_user_id) && $cuid === (string) $current_user_id && !empty($cr['id'])): ?><button type="button" class="btn-del-comment" data-comment-id="<?= htmlspecialchars((string) $cr['id'], ENT_QUOTES, 'UTF-8') ?>" data-post-id="<?= (int) $pid ?>" aria-label="Excluir comentário" title="Excluir">×</button><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <a class="post-comments-more" href="/features/feed/post_comments.php?post_id=<?= (int) $pid ?>">Ver todos os comentários</a>
      <form class="comment-bar" data-comment-form data-post-id="<?= (int) $pid ?>" action="#" method="post">
        <input type="text" name="comment" maxlength="2000" placeholder="Adicione um comentário..." autocomplete="off" aria-label="Comentário">
        <button type="submit">Enviar</button>
      </form>
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
  <a href="/features/feed/index.php"><span>🏠</span>Feed</a>
  <a href="/features/profile/upload_story.php"><span>📷</span>Story</a>
  <a class="bnav-btn" href="/features/chat/salas.php"><span>💬</span><span>Chat</span></a>
  <a class="bnav-btn" href="/features/messages/index.php"><span class="nav-dm-inner">✉️<?php if (!empty($dmUnread)): ?><span class="nav-dm-badge" aria-label="Mensagens não lidas"><?= ((int) $dmUnread > 99) ? '99+' : (int) $dmUnread ?></span><?php endif; ?></span><span>Mensagens</span></a>
  <button type="button" class="nav-fab-wrap" id="openPostModal" aria-label="Nova publicação"><span class="nav-fab" aria-hidden="true">＋</span><span style="font-size:0.6rem;opacity:0">.</span></button>
  <a href="/features/profile/index.php"><span>👤</span>Perfil</a>
  <?php if ($is_admin): ?>
  <a class="bnav-btn" href="/admin">
    <span>⚙️</span>
    <span>Admin</span>
  </a>
  <?php endif; ?>
  <a href="/features/auth/logout.php"><span>🚪</span>Sair</a>
</nav>

<div id="postModalBackdrop" class="modal-backdrop" aria-hidden="true">
  <div class="modal-sheet" role="dialog" aria-labelledby="modalPostTitle" onclick="event.stopPropagation()">
    <div class="modal-handle" aria-hidden="true"></div>
    <h2 id="modalPostTitle" class="modal-title">Nova publicação</h2>
    <form id="modalPostForm" action="/features/feed/create_post.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <input type="file" id="postFile" name="image" accept="image/jpeg,image/png,image/webp" style="display:none">
      <input type="file" id="postVideoFile" name="video" accept="video/mp4,video/quicktime,video/webm" style="display:none">
      <div class="upload-zone" id="chooseArea">
        <div style="font-size:0.92rem;color:#666;margin-bottom:10px">Toque para escolher</div>
        <div style="display:flex;gap:10px;justify-content:center">
          <button type="button" onclick="document.getElementById('postFile').click()" style="background:#1a1a1a;border:1px solid #333;color:#ccc;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:0.85rem">📷 Foto</button>
          <button type="button" onclick="document.getElementById('postVideoFile').click()" style="background:#1a1a1a;border:1px solid #333;color:#ccc;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:0.85rem">🎬 Vídeo</button>
        </div>
      </div>
      <img id="postPreview" src="" alt="Prévia" style="display:none;width:100%;max-height:280px;object-fit:cover;border-radius:10px;margin-bottom:12px;border:1px solid #2a2a2a;background:#0d0d0d">
      <video id="postVideoPreview" style="display:none;width:100%;max-height:280px;object-fit:cover;border-radius:10px;margin-bottom:12px;border:1px solid #2a2a2a;background:#000" muted playsinline controls></video>
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
  var CURRENT_USER_ID = <?= json_encode(isset($current_user_id) ? (string) $current_user_id : '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var toast = document.getElementById('feedToast');
  if (toast) {
    toast.style.display = 'block';
    setTimeout(function(){ toast.style.display = 'none'; }, 3000);
  }
  window.club61ToggleVideo = function(video) {
    var wrap = video.closest('.post-video-wrap');
    if (video.paused) {
      document.querySelectorAll('.post-video').forEach(function(v){ if(v !== video){ v.pause(); v.closest('.post-video-wrap').classList.remove('playing'); } });
      video.play();
      wrap.classList.add('playing');
    } else {
      video.pause();
      wrap.classList.remove('playing');
    }
  };
  window.club61ToggleMute = function(btn) {
    var video = btn.closest('.post-video-wrap').querySelector('.post-video');
    if (!video) return;
    video.muted = !video.muted;
    btn.textContent = video.muted ? '🔇' : '🔊';
  };
  // Autoplay vídeo visível (IntersectionObserver)
  if ('IntersectionObserver' in window) {
    var vobs = new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        var video = e.target.querySelector('.post-video');
        if (!video) return;
        var wrap = e.target;
        if (e.isIntersecting) {
          video.play().then(function(){ wrap.classList.add('playing'); }).catch(function(){});
        } else {
          video.pause();
          wrap.classList.remove('playing');
        }
      });
    }, {threshold: 0.6});
    document.querySelectorAll('.post-video-wrap').forEach(function(w){ vobs.observe(w); });
  }
  window.club61ToggleEmojiPicker = function (postId) {
    document.querySelectorAll('.emoji-picker').forEach(function (p) {
      if (p.id !== 'picker-' + postId) { p.style.display = 'none'; p.setAttribute('aria-hidden', 'true'); }
    });
    var picker = document.getElementById('picker-' + postId);
    if (!picker) return;
    var show = picker.style.display === 'none' || picker.style.display === '';
    picker.style.display = show ? 'flex' : 'none';
    picker.setAttribute('aria-hidden', show ? 'false' : 'true');
  };
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (t && t.closest && t.closest('.reactions-wrapper')) return;
    document.querySelectorAll('.emoji-picker').forEach(function (p) {
      p.style.display = 'none';
      p.setAttribute('aria-hidden', 'true');
    });
  });
  function club61SyncCurtirAtivo(postId) {
    var el = document.getElementById('reactions-' + postId);
    var wrap = el && el.closest('.reactions-wrapper');
    var btn = wrap && wrap.querySelector('.btn-curtir');
    if (!btn) return;
    var has = !!(wrap && wrap.querySelector('.reaction-badge.minha'));
    btn.classList.toggle('ativo', has);
  }
  window.club61CarregarReacoes = function (postId) {
    fetch('/post/reacoes?post_id=' + encodeURIComponent(postId), { credentials: 'same-origin' })
      .then(parseJsonSafe)
      .then(function (data) {
        var container = document.getElementById('reactions-' + postId);
        if (!container || !data) return;
        var reacoes = data.reacoes;
        if (!reacoes || reacoes.length === 0) {
          container.innerHTML = '';
          club61SyncCurtirAtivo(postId);
          return;
        }
        var grupos = {};
        reacoes.forEach(function (r) {
          var em = r.emoji;
          if (!grupos[em]) grupos[em] = { count: 0, minha: false };
          grupos[em].count++;
          if (r.is_minha) grupos[em].minha = true;
        });
        container.innerHTML = Object.keys(grupos).map(function (emoji) {
          var info = grupos[emoji];
          var cls = 'reaction-badge' + (info.minha ? ' minha' : '');
          return '<span class="' + cls + '" onclick="club61Reagir(' + JSON.stringify(String(postId)) + ',' + JSON.stringify(emoji) + ')" title="Clique para alternar">' +
            emoji + ' ' + info.count + '</span>';
        }).join('');
        club61SyncCurtirAtivo(postId);
      })
      .catch(function () {});
  };
  window.club61Reagir = function (postId, emoji) {
    var picker = document.getElementById('picker-' + postId);
    if (picker) {
      picker.style.display = 'none';
      picker.setAttribute('aria-hidden', 'true');
    }
    fetch('/post/reagir', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ post_id: postId, emoji: emoji, csrf: FEED_CSRF })
    })
      .then(parseJsonSafe)
      .then(function (data) {
        if (!data) return;
        if (data.csrf) FEED_CSRF = data.csrf;
        if (data.success) {
          club61CarregarReacoes(postId);
          return;
        }
        if (data.message && data.message.indexOf('Sessão') !== -1) {
          feedToast('Sessão expirada. Atualizando página...', 'error');
          setTimeout(function () { window.location.reload(); }, 600);
          return;
        }
        feedToast(data.message || 'Não foi possível reagir.', 'error');
      })
      .catch(function () { feedToast('Erro de rede ao reagir.', 'error'); });
  };
  document.querySelectorAll('[id^="reactions-"]').forEach(function (el) {
    club61CarregarReacoes(el.id.replace('reactions-', ''));
  });
  document.addEventListener('submit', function(ev){
    var form = ev.target && ev.target.matches && ev.target.matches('[data-comment-form]') ? ev.target : null;
    if (!form) return;
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
    fetch('/features/feed/add_comment.php', { method: 'POST', body: fd, credentials: 'same-origin' })
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
            feedToast('Comentários indisponíveis no momento.', 'error');
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
            if (list.firstElementChild) list.removeChild(list.firstElementChild);
            else break;
          }
        }
        try {
          document.dispatchEvent(new CustomEvent('club61:comment:added', { detail: { post_id: pid } }));
        } catch (e) { /* fallback silencioso */ }
      })
      .catch(function(){
        feedToast('Erro de rede ao comentar.', 'error');
      })
      .finally(function(){ if (btn) btn.disabled = false; });
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
        document.getElementById('postVideoPreview').style.display = 'none';
        chooseArea.style.display = 'none';
      };
      reader.readAsDataURL(this.files[0]);
    }
  });
  var videoFileInput = document.getElementById('postVideoFile');
  var videoPreview = document.getElementById('postVideoPreview');
  if (videoFileInput) videoFileInput.addEventListener('change', function(){
    if (this.files && this.files[0]) {
      var url = URL.createObjectURL(this.files[0]);
      videoPreview.src = url;
      videoPreview.style.display = 'block';
      preview.style.display = 'none';
      chooseArea.style.display = 'none';
    }
  });
  if (form) form.addEventListener('submit', function(){
    if (btnPub) { btnPub.disabled = true; btnPub.textContent = 'Publicando...'; }
  });

  function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  // ==== Curtir simples (post_likes) ====
  function applyLikeState(btn, liked, count) {
    if (!btn) return;
    btn.classList.toggle('liked', !!liked);
    btn.setAttribute('data-liked', liked ? '1' : '0');
    btn.setAttribute('aria-pressed', liked ? 'true' : 'false');
    var icon = btn.querySelector('.like-icon');
    if (icon) icon.textContent = liked ? '❤️' : '🤍';
    var pid = btn.getAttribute('data-post-id');
    var cEl = pid ? document.getElementById('lc-' + pid) : null;
    if (cEl && typeof count === 'number') cEl.textContent = String(count);
  }

  window.club61ToggleLike = function (postId) {
    if (!postId) return;
    var btn = document.querySelector('.js-like-btn[data-post-id="' + postId + '"]');
    if (!btn) return;
    if (btn.disabled) return;
    btn.disabled = true;
    var fd = new FormData();
    fd.append('post_id', String(postId));
    fd.append('csrf', FEED_CSRF);
    fetch('/features/feed/toggle_like.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    })
      .then(parseJsonSafe)
      .then(function (d) {
        if (!d) { feedToast('Resposta inválida.', 'error'); return; }
        if (d.csrf) FEED_CSRF = d.csrf;
        if (!d.ok) {
          if (d.error === 'csrf') {
            feedToast('Sessão expirada. Atualizando...', 'error');
            setTimeout(function () { window.location.reload(); }, 600);
            return;
          }
          feedToast('Não foi possível curtir.', 'error');
          return;
        }
        applyLikeState(btn, !!d.liked, (typeof d.likes_count === 'number' ? d.likes_count : 0));
      })
      .catch(function () { feedToast('Erro de rede ao curtir.', 'error'); })
      .finally(function () { btn.disabled = false; });
  };

  // Click simples = like; pressionar/segurar (>=400ms) = abrir emoji picker
  (function bindLikeInteractions() {
    var LONG_MS = 400;
    document.addEventListener('click', function (ev) {
      var btn = ev.target && ev.target.closest ? ev.target.closest('.js-like-btn') : null;
      if (!btn) return;
      if (btn.dataset.suppressClick === '1') {
        btn.dataset.suppressClick = '0';
        ev.preventDefault();
        return;
      }
      ev.preventDefault();
      var pid = btn.getAttribute('data-post-id');
      if (pid) club61ToggleLike(pid);
    });

    function startPress(btn) {
      if (!btn) return;
      clearTimeout(btn._lpT);
      btn._lpT = setTimeout(function () {
        btn.dataset.suppressClick = '1';
        var pid = btn.getAttribute('data-post-id');
        if (pid && typeof window.club61ToggleEmojiPicker === 'function') {
          window.club61ToggleEmojiPicker(pid);
        }
      }, LONG_MS);
    }
    function cancelPress(btn) {
      if (!btn) return;
      clearTimeout(btn._lpT);
    }

    document.addEventListener('mousedown', function (ev) {
      var btn = ev.target && ev.target.closest ? ev.target.closest('.js-like-btn') : null;
      if (btn) startPress(btn);
    });
    document.addEventListener('mouseup', function (ev) {
      var btn = ev.target && ev.target.closest ? ev.target.closest('.js-like-btn') : null;
      if (btn) cancelPress(btn);
    });
    document.addEventListener('mouseleave', function (ev) {
      var btn = ev.target && ev.target.closest ? ev.target.closest('.js-like-btn') : null;
      if (btn) cancelPress(btn);
    }, true);
    document.addEventListener('touchstart', function (ev) {
      var btn = ev.target && ev.target.closest ? ev.target.closest('.js-like-btn') : null;
      if (btn) startPress(btn);
    }, { passive: true });
    document.addEventListener('touchend', function (ev) {
      var btn = ev.target && ev.target.closest ? ev.target.closest('.js-like-btn') : null;
      if (btn) cancelPress(btn);
    });
    document.addEventListener('touchmove', function (ev) {
      var btn = ev.target && ev.target.closest ? ev.target.closest('.js-like-btn') : null;
      if (btn) cancelPress(btn);
    }, { passive: true });
  })();

  // ==== Botão comentários (contador + foco no input) ====
  document.addEventListener('click', function (ev) {
    var btn = ev.target && ev.target.closest ? ev.target.closest('.js-comment-btn') : null;
    if (!btn) return;
    var pid = btn.getAttribute('data-post-id');
    if (!pid) return;
    var card = btn.closest('.post-block');
    if (!card) return;
    var form = card.querySelector('form[data-comment-form]');
    var input = form ? form.querySelector('input[name="comment"]') : null;
    if (input) {
      input.focus({ preventScroll: false });
      input.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  });

  // Após comentar: incrementar contador
  function incCommentCount(postId, delta) {
    if (!postId) return;
    var el = document.getElementById('cc-' + postId);
    if (!el) return;
    var n = parseInt(el.textContent || '0', 10) || 0;
    n += (typeof delta === 'number' ? delta : 1);
    if (n < 0) n = 0;
    el.textContent = String(n);
  }

  document.addEventListener('club61:comment:added', function (ev) {
    if (ev && ev.detail && ev.detail.post_id) {
      incCommentCount(String(ev.detail.post_id), 1);
    }
  });

  // ==== Excluir comentário próprio ====
  document.addEventListener('click', function (ev) {
    var btn = ev.target && ev.target.closest ? ev.target.closest('.btn-del-comment') : null;
    if (!btn) return;
    ev.preventDefault();
    var cid = btn.getAttribute('data-comment-id');
    var pid = btn.getAttribute('data-post-id');
    if (!cid) return;
    if (!confirm('Excluir comentário?')) return;
    btn.disabled = true;
    var fd = new FormData();
    fd.append('comment_id', cid);
    fd.append('csrf', FEED_CSRF);
    fetch('/features/feed/delete_comment.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    })
      .then(parseJsonSafe)
      .then(function (d) {
        if (!d) { feedToast('Resposta inválida.', 'error'); btn.disabled = false; return; }
        if (d.csrf) FEED_CSRF = d.csrf;
        if (!d.ok) {
          feedToast(d.message || 'Não foi possível excluir.', 'error');
          btn.disabled = false;
          return;
        }
        var line = btn.closest('.comment-line');
        if (line) line.remove();
        if (pid) incCommentCount(pid, -1);
      })
      .catch(function () {
        feedToast('Erro de rede.', 'error');
        btn.disabled = false;
      });
  });

  // ==== Menu ⋯ nos posts ====
  document.addEventListener('click', function (ev) {
    var menuBtn = ev.target && ev.target.closest ? ev.target.closest('.btn-post-menu') : null;
    if (menuBtn) {
      ev.stopPropagation();
      var pid = menuBtn.getAttribute('data-post-id');
      if (!pid) return;
      var dropdown = document.getElementById('pmenu-' + pid);
      if (!dropdown) return;
      var wasOpen = dropdown.classList.contains('open');
      document.querySelectorAll('.post-menu-dropdown.open').forEach(function (d) {
        if (d !== dropdown) d.classList.remove('open');
      });
      dropdown.classList.toggle('open', !wasOpen);
      return;
    }
    if (!(ev.target && ev.target.closest && ev.target.closest('.post-menu-dropdown'))) {
      document.querySelectorAll('.post-menu-dropdown.open').forEach(function (d) { d.classList.remove('open'); });
    }
  });

  // ==== Editar legenda (inline prompt) ====
  document.addEventListener('click', function (ev) {
    var btn = ev.target && ev.target.closest ? ev.target.closest('.btn-edit-post') : null;
    if (!btn) return;
    ev.preventDefault();
    var pid = btn.getAttribute('data-post-id');
    if (!pid) return;
    var card = btn.closest('.post-block');
    if (!card) return;
    document.querySelectorAll('.post-menu-dropdown.open').forEach(function (d) { d.classList.remove('open'); });

    var capBlock = card.querySelector('.post-caption-block');
    var currentText = '';
    if (capBlock) {
      var clone = capBlock.cloneNode(true);
      var cu = clone.querySelector('.cap-user');
      if (cu) cu.remove();
      currentText = (clone.textContent || '').trim();
    }
    var newText = prompt('Editar legenda:', currentText);
    if (newText === null) return;
    newText = newText.trim();

    fetch('/features/feed/edit_post.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ post_id: pid, caption: newText, csrf: FEED_CSRF })
    })
      .then(parseJsonSafe)
      .then(function (d) {
        if (!d) { feedToast('Resposta inválida.', 'error'); return; }
        if (d.csrf) FEED_CSRF = d.csrf;
        if (!d.success) {
          feedToast(d.message || 'Erro ao editar.', 'error');
          return;
        }
        var newCaption = (typeof d.caption === 'string') ? d.caption : newText;
        if (newCaption === '') {
          if (capBlock) capBlock.remove();
        } else {
          var authorHtml = '';
          if (capBlock) {
            var authorSpan = capBlock.querySelector('.cap-user');
            authorHtml = authorSpan ? authorSpan.outerHTML : '';
            capBlock.innerHTML = authorHtml + escHtml(newCaption);
          } else {
            var head = card.querySelector('.post-head .post-head-name');
            var authorLabel = head ? head.textContent : '';
            var nb = document.createElement('div');
            nb.className = 'post-caption-block';
            nb.innerHTML = '<span class="cap-user">' + escHtml(authorLabel) + '</span>' + escHtml(newCaption);
            var actions = card.querySelector('.post-actions-row');
            if (actions) actions.parentNode.insertBefore(nb, actions);
            else card.appendChild(nb);
          }
        }
        feedToast('Legenda atualizada.', 'ok');
      })
      .catch(function () { feedToast('Erro de rede ao editar.', 'error'); });
  });

  var DELETE_POST_URL = <?= json_encode('/features/feed/delete_post.php', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  document.addEventListener('click', function (ev) {
    var btn = ev.target && ev.target.closest ? ev.target.closest('.btn-delete-post') : null;
    if (!btn) return;
    ev.preventDefault();
    var postId = btn.getAttribute('data-post-id');
    if (!postId) return;
    if (!confirm('Tem certeza que deseja excluir este post?')) return;
    btn.disabled = true;
    fetch(DELETE_POST_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ post_id: postId, csrf: FEED_CSRF })
    })
      .then(parseJsonSafe)
      .then(function (data) {
        if (!data) {
          feedToast('Resposta inválida ao excluir.', 'error');
          return;
        }
        if (data.csrf) FEED_CSRF = data.csrf;
        if (data.success) {
          var card = btn.closest('.post-block');
          if (card) card.remove();
          else feedToast('Post excluído.', 'ok');
        } else {
          if (data.message && data.message.indexOf('Sessão expirada') !== -1) {
            feedToast('Sessão expirada. Atualizando página...', 'error');
            setTimeout(function () { window.location.reload(); }, 600);
            return;
          }
          alert('Erro ao excluir: ' + (data.message || 'tente novamente.'));
        }
      })
      .catch(function () {
        feedToast('Erro de rede ao excluir.', 'error');
      })
      .finally(function () { btn.disabled = false; });
  });
})();
</script>
</body>
</html>
