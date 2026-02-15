<?php declare(strict_types=1);

namespace Cognesy\Utils\Container;

use Closure;
use Cognesy\Utils\Container\Exceptions\ContainerException;
use Cognesy\Utils\Container\Exceptions\NotFoundException;

final class SimpleContainer implements Container
{
    /** @var array<string, Closure> Transient factories — invoked on every get() */
    private array $factories = [];

    /** @var array<string, Closure> Singleton factories — invoked once, then cached in $instances */
    private array $singletons = [];

    /** @var array<string, object> Resolved singletons + directly registered instances */
    private array $instances = [];

    /** @var array<string, true> IDs currently being resolved (circular-dependency guard) */
    private array $resolving = [];

    #[\Override]
    public function get(string $id): mixed {
        // 1. Already-resolved singleton or direct instance
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // 2. Singleton factory (resolve once, cache)
        if (isset($this->singletons[$id])) {
            $this->guardCircular($id);
            $this->resolving[$id] = true;
            try {
                $this->instances[$id] = ($this->singletons[$id])($this);
            } finally {
                unset($this->resolving[$id]);
            }
            unset($this->singletons[$id]);
            return $this->instances[$id];
        }

        // 3. Transient factory (new instance each time)
        if (isset($this->factories[$id])) {
            $this->guardCircular($id);
            $this->resolving[$id] = true;
            try {
                return ($this->factories[$id])($this);
            } finally {
                unset($this->resolving[$id]);
            }
        }

        throw new NotFoundException("Service not found: {$id}");
    }

    #[\Override]
    public function has(string $id): bool {
        return isset($this->instances[$id])
            || isset($this->singletons[$id])
            || isset($this->factories[$id]);
    }

    #[\Override]
    public function set(string $id, Closure $factory): void {
        unset($this->singletons[$id], $this->instances[$id]);
        $this->factories[$id] = $factory;
    }

    #[\Override]
    public function singleton(string $id, Closure $factory): void {
        unset($this->factories[$id], $this->instances[$id]);
        $this->singletons[$id] = $factory;
    }

    #[\Override]
    public function instance(string $id, object $service): void {
        unset($this->factories[$id], $this->singletons[$id]);
        $this->instances[$id] = $service;
    }

    private function guardCircular(string $id): void {
        if (isset($this->resolving[$id])) {
            $chain = implode(' -> ', [...array_keys($this->resolving), $id]);
            throw new ContainerException("Circular dependency detected: {$chain}");
        }
    }
}
