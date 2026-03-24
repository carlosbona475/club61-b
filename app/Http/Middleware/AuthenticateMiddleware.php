<?php

declare(strict_types=1);

namespace Club61\Http\Middleware;

use Club61\Core\Contracts\MiddlewareInterface;
use Club61\Core\Request;

final class AuthenticateMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): void
    {
        if (empty($_SESSION['access_token']) || empty($_SESSION['user_id'])) {
            header('Location: /features/auth/login.php');
            exit;
        }
        $next($request);
    }
}
