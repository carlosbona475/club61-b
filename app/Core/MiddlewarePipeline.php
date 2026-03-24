<?php

declare(strict_types=1);

namespace Club61\Core;

use Club61\Core\Contracts\MiddlewareInterface;

final class MiddlewarePipeline
{
    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * @param list<class-string<MiddlewareInterface>> $middleware
     */
    public function dispatch(Request $request, array $middleware, callable $destination): void
    {
        $next = $destination;
        foreach (array_reverse($middleware) as $class) {
            $instance = $this->container->get($class);
            if (!$instance instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException($class . ' must implement MiddlewareInterface');
            }
            $next = static function (Request $req) use ($instance, $next): void {
                $instance->handle($req, $next);
            };
        }
        $next($request);
    }
}
