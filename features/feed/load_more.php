<?php
declare(strict_types=1);



require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';
require_once CLUB61_ROOT . '/config/feed_interactions.php';
require_once CLUB61_ROOT . '/config/online.php';
require_once CLUB61_ROOT . '/config/csrf.php';

if (!class_exists('FeedFormatting')) {
    final class FeedFormatting
    {
        public static function buildClLabel(string $disp, string $unused = ''): string
        {
            return club61_display_id_label($disp);
        }

        public static function relativeTime(?string $iso): string
        {
            if ($iso === null || $iso === '') {
                return '';
            }
            try {
                $t = new DateTimeImmutable($iso);
                $now = new DateTimeImmutable('now');
                $diff = $now->getTimestamp() - $t->getTimestamp();
                if ($diff < 45) {
                    return 'agora';
                }
                if ($diff < 3600) {
                    return max(1, (int) floor($diff / 60)) . 'min';
                }
                if ($diff < 86400) {
                    return max(1, (int) floor($diff / 3600)) . 'h';
                }
                return max(1, (int) floor($diff / 86400)) . 'd';
            } catch (Exception $e) {
                return '';
            }
        }
    }
}

$access_token = (string) ($_SESSION['access_token'] ?? '');
$current_user_id = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
$feedPerPage = 10;
$feedPage = max(2, (int) ($_GET['page'] ?? 2));
$feedOffset = ($feedPage - 1) * $feedPerPage;

$posts = [];
$postAuthorById = [];
$likedPostIds = [];
$likesCountMap = [];
$commentsByPost = [];
$feedStoryUserIds = [];

if ($access_token !== '' && supabase_service_role_available()) {
    $nowIso = gmdate('Y-m-d\TH:i:s\Z');
    $stUrl = SUPABASE_URL . '/rest/v1/stories?select=user_id'
        . '&expires_at=gt.' . rawurlencode($nowIso)
        . '&order=created_at.desc&limit=200';
    $chSt = curl_init($stUrl);
    curl_setopt_array($chSt, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Accept: application/json',
        ],
    ]);
    $rawSt = curl_exec($chSt);
    $cSt = (int) curl_getinfo($chSt, CURLINFO_HTTP_CODE);
    curl_close($chSt);
    if ($rawSt !== false && $cSt >= 200 && $cSt < 300) {
        $srj = json_decode($rawSt, true);
        if (is_array($srj)) {
            foreach ($srj as $sr) {
                $u = isset($sr['user_id']) ? (string) $sr['user_id'] : '';
                if ($u !== '') {
                    $feedStoryUserIds[$u] = true;
                }
            }
        }
    }
}

$ch = curl_init(SUPABASE_URL . '/rest/v1/posts?select=id,user_id,image_url,caption,created_at&order=created_at.desc&limit=' . $feedPerPage . '&offset=' . $feedOffset);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
if (supabase_service_role_available()) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(supabase_service_rest_headers(false), ['Accept: application/json']));
} else {
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . SUPABASE_ANON_KEY, 'Authorization: Bearer ' . $access_token]);
}
$rawPosts = curl_exec($ch);
$statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($rawPosts !== false && $statusCode >= 200 && $statusCode < 300) {
    $decoded = json_decode($rawPosts, true);
    if (is_array($decoded)) {
        $posts = $decoded;
    }
}

$postAuthorIds = [];
foreach ($posts as $p) {
    $uid = isset($p['user_id']) ? (string) $p['user_id'] : '';
    if ($uid !== '') {
        $postAuthorIds[$uid] = true;
    }
}
$postAuthorIdList = array_keys($postAuthorIds);
if ($postAuthorIdList !== [] && $access_token !== '') {
    $inList = implode(',', $postAuthorIdList);
    $apUrl = SUPABASE_URL . '/rest/v1/profiles?select=id,display_id,avatar_url,last_seen&id=in.(' . $inList . ')';
    $chAp = curl_init($apUrl);
    curl_setopt_array($chAp, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => supabase_service_role_available()
            ? array_merge(supabase_service_rest_headers(false), ['Accept: application/json'])
            : ['apikey: ' . SUPABASE_ANON_KEY, 'Authorization: Bearer ' . $access_token],
        CURLOPT_HTTPGET => true,
    ]);
    $rawAuthors = curl_exec($chAp);
    $codeAuthors = (int) curl_getinfo($chAp, CURLINFO_HTTP_CODE);
    curl_close($chAp);
    if ($rawAuthors !== false && $codeAuthors >= 200 && $codeAuthors < 300) {
        $decodedA = json_decode($rawAuthors, true);
        if (is_array($decodedA)) {
            foreach ($decodedA as $row) {
                if (isset($row['id'])) {
                    $postAuthorById[(string) $row['id']] = $row;
                }
            }
        }
    }
}

$postIdsForFeed = [];
foreach ($posts as $p) {
    if (isset($p['id'])) {
        $postIdsForFeed[] = trim((string) $p['id']);
    }
}
if ($postIdsForFeed !== [] && feed_sk_available()) {
    $likesCountMap = feed_get_likes_count_map($postIdsForFeed);
    $commentsByPost = feed_get_recent_comments_map($postIdsForFeed, 3);
    if ($current_user_id !== '') {
        $likedPostIds = feed_get_user_liked_post_ids($current_user_id, $postIdsForFeed);
    }
}

$commentUserIds = [];
foreach ($commentsByPost as $list) {
    if (!is_array($list)) {
        continue;
    }
    foreach ($list as $cr) {
        if (!empty($cr['user_id'])) {
            $commentUserIds[] = (string) $cr['user_id'];
        }
    }
}
$commentProfiles = $commentUserIds !== [] ? feed_fetch_profiles_by_ids(array_values(array_unique($commentUserIds))) : [];
$feedHasOlder = count($posts) >= $feedPerPage;
$feedOlderUrl = '/features/feed/index.php?page=' . ($feedPage + 1);
?>
<?php foreach ($posts as $post): ?>
<?php

$pid = trim((string) ($post['id'] ?? ''));
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
$likeTotal = (int) ($likesCountMap[$pid] ?? 0);
$rawComments = isset($commentsByPost[$pid]) && is_array($commentsByPost[$pid]) ? $commentsByPost[$pid] : [];
$profileViewUrl = '/features/profile/view.php?id=' . rawurlencode($authorId);
$authorHasStory = $authorId !== '' && !empty($feedStoryUserIds[$authorId]);
?>
<article class="post-block" data-post-id="<?= $pid ?>">
  <div class="post-head">
    <a class="post-head-link" href="<?= htmlspecialchars($profileViewUrl, ENT_QUOTES, 'UTF-8') ?>">
      <?php if ($pavatar !== ''): ?>
      <span class="avatar-wrapper<?= $authorHasStory ? ' post-av-wrap' : '' ?>"><img class="post-av" src="<?= htmlspecialchars($pavatar, ENT_QUOTES, 'UTF-8') ?>" alt=""><?php if ($pOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?></span>
      <?php else: ?>
      <span class="post-av-fallback avatar-wrapper<?= $authorHasStory ? ' post-av-wrap' : '' ?>" aria-hidden="true">&#128100;<?php if ($pOnline): ?><span class="online-dot" aria-hidden="true"></span><?php endif; ?></span>
      <?php endif; ?>
      <div class="post-head-meta"><div class="post-head-name"><?= htmlspecialchars($authorLabel, ENT_QUOTES, 'UTF-8') ?></div><div class="post-head-time"><?= htmlspecialchars($relTime, ENT_QUOTES, 'UTF-8') ?></div></div>
    </a>
    <?php if ($authorId !== '' && isset($current_user_id) && $authorId === (string) $current_user_id): ?>
    <button type="button" class="btn-delete-post" data-post-id="<?= $pid ?>" title="Excluir post" aria-label="Excluir post">🗑️</button>
    <?php endif; ?>
  </div>
  <?php if (!empty($post['caption'])): ?><div class="post-caption-block"><span class="cap-user"><?= htmlspecialchars($authorLabel, ENT_QUOTES, 'UTF-8') ?></span><?= htmlspecialchars((string) $post['caption'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php if (!empty($post['image_url'])): ?><div class="post-img-wrap"><img class="post-img" src="<?= htmlspecialchars((string) $post['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt=""></div><?php endif; ?>
  <div class="post-actions-row">
    <div class="reactions-wrapper">
      <div class="reactions-count" id="reactions-<?= $pid ?>"></div>
      <button type="button" class="btn-curtir<?= $isLiked ? ' ativo' : '' ?>" onclick="club61ToggleEmojiPicker(<?= json_encode($pid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)">🤍 Curtir</button>
      <div class="emoji-picker" id="picker-<?= $pid ?>" style="display:none;" aria-hidden="true">
        <span onclick="club61Reagir(<?= json_encode($pid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, <?= json_encode('❤️', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)" role="button" tabindex="0">❤️</span>
        <span onclick="club61Reagir(<?= json_encode($pid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, <?= json_encode('😂', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)" role="button" tabindex="0">😂</span>
        <span onclick="club61Reagir(<?= json_encode($pid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, <?= json_encode('😮', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)" role="button" tabindex="0">😮</span>
        <span onclick="club61Reagir(<?= json_encode($pid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, <?= json_encode('😢', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)" role="button" tabindex="0">😢</span>
        <span onclick="club61Reagir(<?= json_encode($pid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, <?= json_encode('🔥', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)" role="button" tabindex="0">🔥</span>
        <span onclick="club61Reagir(<?= json_encode($pid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, <?= json_encode('👏', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)" role="button" tabindex="0">👏</span>
      </div>
    </div>
  </div>
  <div class="post-comments" data-comment-list data-post-id="<?= $pid ?>">
    <?php foreach ($rawComments as $cr): ?>
    <?php $cuid = isset($cr['user_id']) ? (string) $cr['user_id'] : ''; $cpr = $cuid !== '' && isset($commentProfiles[$cuid]) ? $commentProfiles[$cuid] : null; $cdisp = $cpr && isset($cpr['display_id']) ? trim((string) $cpr['display_id']) : ''; $clab = FeedFormatting::buildClLabel($cdisp); $ctxt = isset($cr['comment_text']) ? (string) $cr['comment_text'] : ''; ?>
    <div class="comment-line"><span class="comment-user"><?= htmlspecialchars($clab, ENT_QUOTES, 'UTF-8') ?></span><?= htmlspecialchars($ctxt, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>
  </div>
  <a class="post-comments-more" href="/features/feed/post_comments.php?post_id=<?= rawurlencode($pid) ?>">Ver todos os comentários</a>
  <form class="comment-bar" data-comment-form data-post-id="<?= $pid ?>" action="#" method="post">
    <input type="text" name="comment" maxlength="2000" placeholder="Adicione um comentário..." autocomplete="off" aria-label="Comentário"><button type="submit">Enviar</button>
  </form>
</article>
<?php endforeach; ?>
<?php if ($posts !== [] && $feedHasOlder): ?><div class="feed-pager"><a href="<?= htmlspecialchars($feedOlderUrl, ENT_QUOTES, 'UTF-8') ?>">Carregar publicações mais antigas</a></div><?php endif; ?>
