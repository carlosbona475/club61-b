<?php

declare(strict_types=1);

namespace Club61\Core\Contracts;

use Club61\Core\Request;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): void;
}
