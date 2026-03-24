<?php

declare(strict_types=1);

namespace Club61\Services;

use Club61\Core\Request;
use Club61\Infrastructure\Http\SupabaseRestClient;
use Club61\Repositories\PostRepository;
use Club61\Repositories\ProfileRepository;
use Club61\Repositories\StoryRepository;

final class FeedService
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly ProfileRepository $profiles,
        private readonly StoryRepository $stories,
        private readonly SupabaseRestClient $http,
    ) {
    }

    /**
     * Dados do feed (equivalente ao antigo features/feed/index.php).
     *
     * @return array<string, mixed>
     */
    public function buildIndexData(Request $request): array
    {
        require_once \CLUB61_BASE_PATH . '/config/supabase.php';
        require_once \CLUB61_BASE_PATH . '/config/profile_helper.php';
        require_once \CLUB61_BASE_PATH . '/config/feed_interactions.php';
        require_once \CLUB61_BASE_PATH . '/config/online.php';
        require_once \CLUB61_BASE_PATH . '/config/csrf.php';

        $status = (string) $request->query('status', '');
        $message = (string) $request->query('message', '');
        $posts = [];
        $storyProfiles = [];
        $membros_ativos = 0;
        $postAuthorById = [];
        $likedPostIds = [];
        $likesCountMap = [];
        $commentsByPost = [];

        $access_token = (string) ($_SESSION['access_token'] ?? '');
        $current_user_id = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
        $feedPerPage = 15;
        $feedPage = max(1, (int) $request->query('page', 1));
        $feedOffset = ($feedPage - 1) * $feedPerPage;

        if (\supabase_service_role_available()) {
            $membros_ativos = $this->profiles->countMembers($access_token);
        } elseif ($access_token !== '') {
            $membros_ativos = $this->profiles->countMembers($access_token);
        }

        if ($access_token !== '' && \supabase_service_role_available()) {
            $storyProfiles = $this->stories->activeStoryProfiles();
        }

        $posts = $this->posts->listFeedPage($feedPerPage, $feedOffset, $access_token);

        $postAuthorIds = [];
        foreach ($posts as $p) {
            $uid = isset($p['user_id']) ? (string) $p['user_id'] : '';
            if ($uid !== '') {
                $postAuthorIds[$uid] = true;
            }
        }
        $postAuthorIdList = array_keys($postAuthorIds);
        if ($postAuthorIdList !== [] && $access_token !== '') {
            $postAuthorById = $this->profiles->fetchByIds($postAuthorIdList, $access_token);
        }

        $postIdsForFeed = [];
        foreach ($posts as $p) {
            if (isset($p['id'])) {
                $postIdsForFeed[] = (int) $p['id'];
            }
        }

        if ($postIdsForFeed !== [] && \feed_sk_available()) {
            $likesCountMap = \feed_get_likes_count_map($postIdsForFeed);
            $commentsByPost = \feed_get_recent_comments_map($postIdsForFeed, 3);
            if ($current_user_id !== '') {
                $likedPostIds = \feed_get_user_liked_post_ids($current_user_id, $postIdsForFeed);
            }
        } elseif ($current_user_id !== '' && $access_token !== '') {
            $likedPostIds = $this->fetchLegacyUserLikedPostIds($current_user_id, $access_token);
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
        $commentProfiles = $commentUserIds !== [] ? \feed_fetch_profiles_by_ids(array_values(array_unique($commentUserIds))) : [];

        $feedCsrf = \feed_csrf_token();
        $feedHasOlder = count($posts) >= $feedPerPage;
        $feedOlderUrl = '/features/feed/index.php?page=' . ($feedPage + 1);
        $is_admin = \isCurrentUserAdmin();

        return [
            'status' => $status,
            'message' => $message,
            'posts' => $posts,
            'storyProfiles' => $storyProfiles,
            'membros_ativos' => $membros_ativos,
            'postAuthorById' => $postAuthorById,
            'likedPostIds' => $likedPostIds,
            'likesCountMap' => $likesCountMap,
            'commentsByPost' => $commentsByPost,
            'access_token' => $access_token,
            'current_user_id' => $current_user_id,
            'feedPerPage' => $feedPerPage,
            'feedPage' => $feedPage,
            'commentProfiles' => $commentProfiles,
            'feedCsrf' => $feedCsrf,
            'feedHasOlder' => $feedHasOlder,
            'feedOlderUrl' => $feedOlderUrl,
            'is_admin' => $is_admin,
        ];
    }

    /**
     * @return array<int, true>
     */
    private function fetchLegacyUserLikedPostIds(string $current_user_id, string $access_token): array
    {
        $likedPostIds = [];
        $lkUrl = \SUPABASE_URL . '/rest/v1/likes?user_id=eq.' . urlencode($current_user_id) . '&select=post_id';
        if (\supabase_service_role_available()) {
            $headers = array_merge(\supabase_service_rest_headers(false), ['Accept: application/json']);
        } else {
            $headers = [
                'apikey: ' . \SUPABASE_ANON_KEY,
                'Authorization: Bearer ' . $access_token,
            ];
        }
        $res = $this->http->jsonGet($lkUrl, $headers);
        if ($res === null) {
            return $likedPostIds;
        }
        [, $likesRows] = $res;
        if (!is_array($likesRows)) {
            return $likedPostIds;
        }
        foreach ($likesRows as $lr) {
            if (isset($lr['post_id'])) {
                $likedPostIds[(int) $lr['post_id']] = true;
            }
        }

        return $likedPostIds;
    }
}
