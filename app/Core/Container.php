<?php

declare(strict_types=1);

namespace Club61\Core;

use Closure;
use RuntimeException;

final class Container
{
    /** @var array<string, Closure(self): object> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $singletons = [];

    /**
     * @param Closure(self): object $factory
     */
    public function singleton(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): object
    {
        if (isset($this->singletons[$id])) {
            return $this->singletons[$id];
        }
        if (!isset($this->factories[$id])) {
            throw new RuntimeException('Container: binding not found: ' . $id);
        }
        $obj = ($this->factories[$id])($this);
        $this->singletons[$id] = $obj;

        return $obj;
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}
