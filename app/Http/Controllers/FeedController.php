<?php

declare(strict_types=1);

namespace Club61\Http\Controllers;

use Club61\Core\Request;
use Club61\Services\FeedService;

final class FeedController extends Controller
{
    public function __construct(
        private readonly FeedService $feedService,
    ) {
    }

    public function index(Request $request): void
    {
        $this->view('feed.index', $this->feedService->buildIndexData($request));
    }
}
