<?php

declare(strict_types=1);

namespace Club61\Controllers;

use Club61\Core\Request;

final class LegacyController
{
    public function feedLoadMore(Request $request): void
    {
        require \CLUB61_BASE_PATH . '/features/feed/load_more.php';
    }

    public function feedCreatePost(Request $request): void
    {
        require \CLUB61_BASE_PATH . '/features/feed/create_post.php';
    }

    public function feedToggleLike(Request $request): void
    {
        require \CLUB61_BASE_PATH . '/features/feed/toggle_like.php';
    }

    public function feedAddComment(Request $request): void
    {
        require \CLUB61_BASE_PATH . '/features/feed/add_comment.php';
    }

    public function profileIndex(Request $request): void
    {
        require \CLUB61_BASE_PATH . '/features/profile/index.php';
    }

    public function chatGeneral(Request $request): void
    {
        require \CLUB61_BASE_PATH . '/features/chat/general.php';
    }
}

