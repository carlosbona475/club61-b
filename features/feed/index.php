<?php
require_once __DIR__ . '/../../auth_guard.php';
require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../config/profile_helper.php';

$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';
$posts = [];
$storyProfiles = [];
$membros_ativos = 0;

$access_token = $_SESSION['access_token'] ?? '';
if (supabase_service_role_available()) {
    $membros_ativos = countProfilesTotalUsingServiceRole();
} elseif ($access_token !== '') {
    $membros_ativos = countProfilesTotal($access_token);
}
if ($access_token !== '' && supabase_service_role_available()) {
    $nowIso = gmdate('Y-m-d\TH:i:s\Z');
    $stUrl = SUPABASE_URL . '/rest/v1/stories?select=user_id,expires_at,created_at'
        . '&expires_at=gt.' . rawurlencode($nowIso)
        . '&order=created_at.desc'
        . '&limit=100';
    $chSt = curl_init($stUrl);
    curl_setopt($chSt, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chSt, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($chSt, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($chSt, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Accept: application/json',
    ]);
    $rawStories = curl_exec($chSt);
    $codeStories = curl_getinfo($chSt, CURLINFO_HTTP_CODE);
    curl_close($chSt);

    $orderedUserIds = [];
    $seen = [];
    if ($rawStories !== false && $codeStories >= 200 && $codeStories < 300) {
        $storyRows = json_decode($rawStories, true);
        if (is_array($storyRows)) {
            foreach ($storyRows as $sr) {
                $uid = isset($sr['user_id']) ? (string) $sr['user_id'] : '';
                if ($uid !== '' && !isset($seen[$uid])) {
                    $seen[$uid] = true;
                    $orderedUserIds[] = $uid;
                    if (count($orderedUserIds) >= 20) {
                        break;
                    }
                }
            }
        }
    }

    if ($orderedUserIds !== []) {
        $inList = implode(',', $orderedUserIds);
        $spUrl = SUPABASE_URL . '/rest/v1/profiles?select=id,username,display_id,avatar_url&id=in.(' . $inList . ')';
        $chSp = curl_init($spUrl);
        curl_setopt($chSp, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chSp, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($chSp, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($chSp, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Accept: application/json',
        ]);
        $rawProfiles = curl_exec($chSp);
        $codeProfiles = curl_getinfo($chSp, CURLINFO_HTTP_CODE);
        curl_close($chSp);
        if ($rawProfiles !== false && $codeProfiles >= 200 && $codeProfiles < 300) {
            $decodedP = json_decode($rawProfiles, true);
            $byId = [];
            if (is_array($decodedP)) {
                foreach ($decodedP as $p) {
                    if (isset($p['id'])) {
                        $byId[(string) $p['id']] = $p;
                    }
                }
            }
            foreach ($orderedUserIds as $oid) {
                if (isset($byId[$oid])) {
                    $storyProfiles[] = $byId[$oid];
                }
            }
        }
    }
}

$ch = curl_init(SUPABASE_URL . '/rest/v1/posts?select=id,user_id,image_url,caption,created_at&order=created_at.desc');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_ANON_KEY,
    'Authorization: Bearer ' . $access_token,
]);
$rawPosts = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($rawPosts !== false && $statusCode >= 200 && $statusCode < 300) {
    $decoded = json_decode($rawPosts, true);
    if (is_array($decoded)) { $posts = $decoded; }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feed — Club61</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:#0A0A0A;color:#fff;font-family:'Segoe UI',sans-serif;min-height:100vh}
.container{max-width:640px;margin:0 auto;padding:32px 16px}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:24px;font-size:0.9rem}
.alert.ok{background:rgba(47,158,68,0.1);border:1px solid rgba(47,158,68,0.3);color:#69db7c}
.alert.error{background:rgba(255,107,107,0.1);border:1px solid rgba(255,107,107,0.3);color:#ff6b6b}
.post-form{background:#111;border:1px solid #222;border-radius:12px;padding:24px;margin-bottom:32px}
.post-form h2{color:#C9A84C;font-size:1rem;font-weight:600;letter-spacing:1px;text-transform:uppercase;margin-bottom:16px}
#postFile{display:none}
#chooseArea{display:block;background:#1a1a1a;border:1px dashed #333;border-radius:8px;padding:20px;text-align:center;color:#666;cursor:pointer;margin-bottom:12px;transition:border-color .2s;font-size:0.95rem}
#chooseArea:hover{border-color:#7B2EFF;color:#aaa}
#postPreview{display:none;width:100%;max-height:280px;object-fit:cover;border-radius:8px;margin-bottom:12px;border:1px solid #2a2a2a;background:#0d0d0d}
.caption-input{width:100%;background:#1a1a1a;border:1px solid #2a2a2a;border-radius:8px;color:#fff;padding:12px 14px;font-size:0.95rem;margin-bottom:12px;outline:none;transition:border-color .2s}
.caption-input:focus{border-color:#7B2EFF}
.caption-input::placeholder{color:#444}
.btn-post{width:100%;padding:13px;background:#7B2EFF;color:#fff;border:none;border-radius:8px;font-size:0.95rem;font-weight:700;cursor:pointer;transition:box-shadow .3s}
.btn-post:hover{box-shadow:0 0 20px rgba(123,46,255,0.5)}
.posts-title{color:#555;font-size:0.75rem;font-weight:600;letter-spacing:2px;text-transform:uppercase;margin-bottom:16px}
.post-card{background:#111;border:1px solid #1e1e1e;border-radius:12px;margin-bottom:20px;overflow:hidden}
.post-card img{width:100%;display:block;max-height:480px;object-fit:cover}
.post-body{padding:16px}
.post-user{font-size:0.78rem;color:#555;margin-bottom:8px}
.post-caption{color:#ccc;font-size:0.95rem;line-height:1.5;margin-bottom:14px}
.post-actions{display:flex;gap:10px}
.btn-like,.btn-unlike{padding:8px 18px;border-radius:6px;border:none;font-size:0.85rem;font-weight:600;cursor:pointer;transition:all .2s}
.btn-like{background:rgba(123,46,255,0.15);color:#a78bfa;border:1px solid rgba(123,46,255,0.3)}
.btn-like:hover{background:rgba(123,46,255,0.3)}
.btn-unlike{background:rgba(255,107,107,0.1);color:#ff6b6b;border:1px solid rgba(255,107,107,0.2)}
.btn-unlike:hover{background:rgba(255,107,107,0.2)}
.empty{text-align:center;color:#444;padding:48px 0;font-size:0.95rem}
.stories-bar{width:100%;background:#111;border-bottom:1px solid #222}
.stories-scroll{display:flex;gap:16px;overflow-x:auto;overflow-y:hidden;padding:12px 16px;-webkit-overflow-scrolling:touch;scrollbar-color:#333 #111}
.stories-scroll::-webkit-scrollbar{height:6px}
.stories-scroll::-webkit-scrollbar-track{background:#111}
.stories-scroll::-webkit-scrollbar-thumb{background:#333;border-radius:3px}
.story-item{flex:0 0 auto;display:flex;flex-direction:column;align-items:center;gap:6px;min-width:64px}
.story-avatar-link{text-decoration:none;display:flex;flex-direction:column;align-items:center;color:inherit}
.story-cl--story{text-decoration:none;color:#fff;font-size:0.68rem;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:center;opacity:0.95}
.story-cl--story:hover{opacity:1;color:#C9A84C}
.story-avatar-ring{box-sizing:border-box;width:64px;height:64px;border-radius:50%;border:3px solid transparent;background:linear-gradient(#111,#111) padding-box,linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888) border-box;background-clip:padding-box,border-box;overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.story-avatar-img{width:100%;height:100%;object-fit:cover;border-radius:50%;display:block}
.story-avatar-fallback{width:100%;height:100%;background:#111;display:flex;align-items:center;justify-content:center;font-size:1.35rem;color:#7B2EFF;border-radius:50%}
.story-cl{font-size:0.68rem;color:#fff;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:center;opacity:0.95}
.story-item--add{cursor:pointer}
.story-item--add .story-add-circle{box-sizing:border-box;width:64px;height:64px;border-radius:50%;border:2px dashed #7B2EFF;background:#111;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:2.25rem;font-weight:300;line-height:1;color:#fff;text-decoration:none}
.story-item--add:hover .story-add-circle{border-color:#9b5cff;color:#fff}
.story-cl--meu{color:#fff;font-size:0.68rem;max-width:80px;text-align:center}
</style>
</head>
<body>
<?php if ($access_token !== ''): ?>
<div class="stories-bar">
  <div class="stories-scroll" role="list" aria-label="Stories">
    <a class="story-item story-item--add" href="/features/profile/upload_story.php" role="listitem">
      <div class="story-add-circle" aria-hidden="true">+</div>
      <span class="story-cl story-cl--meu">Meu story</span>
    </a>
    <?php foreach ($storyProfiles as $sp): ?>
      <?php
      $sid = isset($sp['id']) ? (string) $sp['id'] : '';
      $savatar = isset($sp['avatar_url']) ? trim((string) $sp['avatar_url']) : '';
      $sdisp = isset($sp['display_id']) ? trim((string) $sp['display_id']) : '';
      $scl = 'CL00';
      if ($sdisp !== '') {
          $num = null;
          if (preg_match('/^CL\s*0*(\d+)$/i', $sdisp, $m)) {
              $num = (int) $m[1];
          } else {
              $digits = preg_replace('/\D/', '', $sdisp);
              if ($digits !== '') {
                  $num = (int) $digits;
              }
          }
          if ($num !== null && $num > 0) {
              $scl = 'CL' . str_pad((string) min(999, $num), 2, '0', STR_PAD_LEFT);
          }
      }
      if ($sid === '') {
          continue;
      }
      ?>
    <div class="story-item" role="listitem">
      <a class="story-avatar-link" href="/features/profile/view.php?user_id=<?= urlencode($sid) ?>" aria-label="Ver perfil">
        <div class="story-avatar-ring">
          <?php if ($savatar !== ''): ?>
            <img class="story-avatar-img" src="<?= htmlspecialchars($savatar, ENT_QUOTES, 'UTF-8') ?>" alt="">
          <?php else: ?>
            <div class="story-avatar-fallback" aria-hidden="true">&#128100;</div>
          <?php endif; ?>
        </div>
      </a>
      <a class="story-cl story-cl--story" href="/features/stories/view.php?user_id=<?= urlencode($sid) ?>"><?= htmlspecialchars($scl, ENT_QUOTES, 'UTF-8') ?></a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<nav style="background:#111;border-bottom:1px solid #222;padding:0 24px;display:flex;align-items:center;justify-content:space-between;min-height:60px;position:sticky;top:0;z-index:100">
  <div style="display:flex;flex-direction:column;gap:2px;line-height:1.2;padding:10px 0">
    <a href="/features/feed/index.php" style="font-size:1.3rem;font-weight:800;color:#C9A84C;letter-spacing:2px;text-decoration:none">Club61</a>
    <span style="font-size:0.7rem;color:#888;font-weight:500;letter-spacing:0.02em"><?= (int) $membros_ativos ?> membros ativos</span>
  </div>
  <ul style="display:flex;gap:24px;list-style:none;margin:0;padding:0">
    <li><a href="/features/feed/index.php" style="color:#888;text-decoration:none;font-size:0.9rem;font-weight:500">Feed</a></li>
    <li><a href="/features/profile/index.php" style="color:#888;text-decoration:none;font-size:0.9rem;font-weight:500">Perfil</a></li>
    <li><a href="/features/auth/logout.php" style="color:#888;text-decoration:none;font-size:0.9rem;font-weight:500">Sair</a></li>
  </ul>
</nav>
<div class="container">
  <?php if ($status === 'ok'): ?>
    <div class="alert ok"><?= htmlspecialchars($message ?: 'Post criado com sucesso.', ENT_QUOTES, 'UTF-8') ?></div>
  <?php elseif ($status === 'error'): ?>
    <div class="alert error"><?= htmlspecialchars($message ?: 'Erro ao criar post.', ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="post-form">
    <h2>Novo Post</h2>
    <form action="create_post.php" method="POST" enctype="multipart/form-data">
      <input type="file" id="postFile" name="image" accept="image/jpeg,image/png,image/webp" required>
      <div id="chooseArea" onclick="document.getElementById('postFile').click()">📷 Escolher imagem</div>
      <img id="postPreview" src="" alt="Prévia do post">
      <input class="caption-input" type="text" name="caption" placeholder="Escreva uma legenda...">
      <button class="btn-post" type="submit">Publicar</button>
    </form>
  </div>

  <div class="posts-title">Publicações</div>

  <?php if (empty($posts)): ?>
    <div class="empty">Nenhum post ainda. Seja o primeiro a publicar!</div>
  <?php else: ?>
    <?php foreach ($posts as $post): ?>
    <div class="post-card">
      <?php if (!empty($post['image_url'])): ?>
        <img src="<?= htmlspecialchars($post['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Post">
      <?php endif; ?>
      <div class="post-body">
        <div class="post-user">@<?= htmlspecialchars((string)($post['user_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <?php if (!empty($post['caption'])): ?>
          <div class="post-caption"><?= htmlspecialchars($post['caption'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div class="post-actions">
          <form action="like.php" method="POST">
            <input type="hidden" name="post_id" value="<?= (int)($post['id'] ?? 0) ?>">
            <button class="btn-like" type="submit">♥ Curtir</button>
          </form>
          <form action="unlike_post.php" method="POST">
            <input type="hidden" name="post_id" value="<?= (int)($post['id'] ?? 0) ?>">
            <button class="btn-unlike" type="submit">✕ Descurtir</button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<script>
document.getElementById('postFile').addEventListener('change', function() {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('postPreview').src = e.target.result;
            document.getElementById('postPreview').style.display = 'block';
            document.getElementById('chooseArea').style.display = 'none';
        };
        reader.readAsDataURL(this.files[0]);
    }
});
</script>
</body>
</html>
