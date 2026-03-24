<?php

declare(strict_types=1);

namespace Club61\Http\Middleware;

use Club61\Core\Contracts\MiddlewareInterface;
use Club61\Core\Request;

final class TouchLastSeenMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): void
    {
        require_once \CLUB61_BASE_PATH . '/config/supabase.php';
        require_once \CLUB61_BASE_PATH . '/config/last_seen.php';
        $uid = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
        if ($uid !== '') {
            club61_touch_last_seen($uid);
        }
        $next($request);
    }
}
