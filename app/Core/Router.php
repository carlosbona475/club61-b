<?php

declare(strict_types=1);

namespace Club61\Core;

final class Router
{
    /**
     * @param array{
     *   middleware_groups: array<string, list<class-string>>,
     *   routes: array<string, array{middleware: string, action: array{class-string, string}}>
     * } $config
     */
    public function __construct(
        private readonly array $config,
    ) {
    }

    /**
     * @return array{middleware: list<class-string>, action: array{class-string, string}}|null
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        $path = '/' . ltrim((string) parse_url($path, PHP_URL_PATH), '/');
        $key = $method . ' ' . $path;
        $map = $this->config['routes'] ?? [];

        return $map[$key] ?? null;
    }

    /**
     * @return list<class-string>
     */
    public function middlewareGroup(string $name): array
    {
        $groups = $this->config['middleware_groups'] ?? [];

        return $groups[$name] ?? [];
    }
}
