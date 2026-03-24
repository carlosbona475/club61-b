<?php

declare(strict_types=1);

namespace Club61\Http\Middleware;

use Club61\Core\Contracts\MiddlewareInterface;
use Club61\Core\Request;

final class SessionMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): void
    {
        require_once \CLUB61_BASE_PATH . '/config/session.php';
        club61_session_start_safe();
        $next($request);
    }
}
