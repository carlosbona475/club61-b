<?php

declare(strict_types=1);

namespace Club61\Controllers;

use Club61\Core\Request;
use Club61\Services\FeedService;
use Club61\Support\View;

final class FeedController
{
    public function __construct(
        private readonly FeedService $feedService,
    ) {
    }

    public function index(Request $request): void
    {
        echo View::make('feed.index', $this->feedService->buildIndexData($request));
    }
}

