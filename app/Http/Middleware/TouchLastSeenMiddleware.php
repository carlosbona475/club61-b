<?php

declare(strict_types=1);

namespace Club61\Http\Middleware;

use Club61\Core\Contracts\MiddlewareInterface;
use Club61\Core\Request;
use Club61\Services\LastSeenService;
use Club61\Services\SessionService;

final class TouchLastSeenMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LastSeenService $lastSeenService,
        private readonly SessionService $sessionService,
    ) {
    }

    public function handle(Request $request, callable $next): void
    {
        $this->lastSeenService->touch($this->sessionService->userId());
        $next($request);
    }
}
