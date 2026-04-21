<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/paths.php';

require_once CLUB61_ROOT . '/auth_guard.php';
require_once CLUB61_ROOT . '/config/supabase.php';
require_once CLUB61_ROOT . '/config/profile_helper.php';
require_once CLUB61_ROOT . '/config/feed_interactions.php';
require_once CLUB61_ROOT . '/config/online.php';
require_once CLUB61_ROOT . '/config/csrf.php';
require_once CLUB61_ROOT . '/config/direct_messages_helper.php';

$onlineUsers = [];

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

$status = (string) ($_GET['status'] ?? '');
$message = (string) ($_GET['message'] ?? '');
$posts = [];
$storyProfiles = [];
$feedStoryUserIds = [];
$membros_ativos = 0;
$postAuthorById = [];
$likedPostIds = [];
$likesCountMap = [];
$commentsByPost = [];

$access_token = (string) ($_SESSION['access_token'] ?? '');
$current_user_id = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
$dmUnread = ($current_user_id !== '') ? club61_dm_unread_count($current_user_id) : 0;
$feedPerPage = 10;
$feedPage = max(1, (int) ($_GET['page'] ?? 1));
$feedOffset = ($feedPage - 1) * $feedPerPage;

if (supabase_service_role_available()) {
    $membros_ativos = countProfilesTotalUsingServiceRole();
} elseif ($access_token !== '') {
    $membros_ativos = countProfilesTotal($access_token);
}

if ($access_token !== '' && supabase_service_role_available()) {
    $nowIso = gmdate('Y-m-d\TH:i:s\Z');
    $stUrl = SUPABASE_URL . '/rest/v1/stories?select=user_id,expires_at,created_at'
        . '&expires_at=gt.' . rawurlencode($nowIso)
        . '&order=created_at.desc&limit=100';
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
    $rawStories = curl_exec($chSt);
    $codeStories = (int) curl_getinfo($chSt, CURLINFO_HTTP_CODE);
    curl_close($chSt);
    $orderedUserIds = [];
    $seen = [];
    if ($rawStories !== false && $codeStories >= 200 && $codeStories < 300) {
        $storyRows = json_decode($rawStories, true);
        if (is_array($storyRows)) {
            foreach ($storyRows as $sr) {
                $uid = isset($sr['user_id']) ? (string) $sr['user_id'] : '';
                if ($uid !== '') {
                    $feedStoryUserIds[$uid] = true;
                }
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
        $spUrl = SUPABASE_URL . '/rest/v1/profiles?select=id,display_id,avatar_url,last_seen&id=in.(' . $inList . ')';
        $chSp = curl_init($spUrl);
        curl_setopt_array($chSp, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_SERVICE_KEY,
                'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
                'Accept: application/json',
            ],
        ]);
        $rawProfiles = curl_exec($chSp);
        $codeProfiles = (int) curl_getinfo($chSp, CURLINFO_HTTP_CODE);
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

$ch = curl_init(SUPABASE_URL . '/rest/v1/posts?select=id,user_id,image_url,video_url,caption,created_at&order=created_at.desc&limit=' . $feedPerPage . '&offset=' . $feedOffset);
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
    $postAuthorById = [];
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
$feedCsrf = feed_csrf_token();
$feedHasOlder = count($posts) >= $feedPerPage;
$feedOlderUrl = '/features/feed/index.php?page=' . ($feedPage + 1);
$is_admin = isCurrentUserAdmin();

$feedMyAvatarUrl = '';
if ($current_user_id !== '' && $access_token !== '') {
    $meProfUrl = SUPABASE_URL . '/rest/v1/profiles?id=eq.' . rawurlencode($current_user_id) . '&select=avatar_url';
    $chMe = curl_init($meProfUrl);
    curl_setopt_array($chMe, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => supabase_service_role_available()
            ? array_merge(supabase_service_rest_headers(false), ['Accept: application/json'])
            : ['apikey: ' . SUPABASE_ANON_KEY, 'Authorization: Bearer ' . $access_token],
        CURLOPT_HTTPGET => true,
    ]);
    $meRaw = curl_exec($chMe);
    $meCode = (int) curl_getinfo($chMe, CURLINFO_HTTP_CODE);
    curl_close($chMe);
    if ($meRaw !== false && $meCode >= 200 && $meCode < 300) {
        $meRows = json_decode($meRaw, true);
        if (is_array($meRows) && isset($meRows[0]['avatar_url'])) {
            $u = trim((string) $meRows[0]['avatar_url']);
            if ($u !== '') {
                $feedMyAvatarUrl = $u;
            }
        }
    }
}

ob_start();
require __DIR__ . '/feed_view.php';
$feedHtml = (string) ob_get_clean();
$infiniteJs = <<<HTML
<script>
(function(){
  var pager = document.querySelector('.feed-pager a');
  if (!pager) return;
  var loading = false;
  var done = false;
  function loadMore() {
    if (loading || done || !pager) return;
    loading = true;
    fetch(pager.href.replace('/features/feed/index.php', '/features/feed/load_more.php'), {credentials:'same-origin'})
      .then(function(r){ return r.text(); })
      .then(function(html){
        var t = (html || '').trim();
        if (!t) { done = true; pager.parentElement.style.display='none'; return; }
        var box = document.createElement('div');
        box.innerHTML = t;
        var cards = box.querySelectorAll('.post-block');
        var main = document.querySelector('.feed-main');
        if (!main || cards.length === 0) { done = true; pager.parentElement.style.display='none'; return; }
        cards.forEach(function(c){ main.insertBefore(c, main.querySelector('.feed-pager')); });
        cards.forEach(function (c) {
          var rid = c.querySelector('[id^="reactions-"]');
          if (rid && rid.id && window.club61CarregarReacoes) {
            window.club61CarregarReacoes(rid.id.replace('reactions-', ''));
          }
          if ('IntersectionObserver' in window && window._vobs) {
            var vw = c.querySelector('.post-video-wrap');
            if (vw) window._vobs.observe(vw);
          }
        });
        var next = box.querySelector('.feed-pager a');
        if (next) pager.href = next.href; else { done = true; pager.parentElement.style.display='none'; }
      })
      .catch(function(){})
      .finally(function(){ loading = false; });
  }
  window.addEventListener('scroll', function(){
    if (done || loading) return;
    if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 380) loadMore();
  }, {passive:true});
})();
</script>
HTML;
$feedHtml = str_replace('</body>', $infiniteJs . '</body>', $feedHtml);
echo $feedHtml;