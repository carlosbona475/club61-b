<?php

declare(strict_types=1);

namespace Club61\Core;

final class Router
{
    /**
     * @param array{
     *   middleware_groups: array<string, list<class-string>>,
     *   legacy_files: array<string, array{middleware: string, action: array{class-string, string}}>
     * } $config
     */
    public function __construct(
        private readonly array $config,
    ) {
    }

    /**
     * @return array{middleware: list<class-string>, action: array{class-string, string}}|null
     */
    public function matchLegacyScript(string $relativePath): ?array
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $map = $this->config['legacy_files'] ?? [];

        return $map[$relativePath] ?? null;
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
