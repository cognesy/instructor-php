<?php declare(strict_types=1);

namespace Cognesy\Utils\Context;

use RuntimeException;

final class Context
{
    /** @var array<class-string,object> */
    private array $services;

    public function __construct(array $services = []) {
        $this->services = $services;
    }

    public static function empty(): self {
        return new self();
    }

    /**
     * Return **new** context with service added/replaced.
     */
    public function with(string $class, object $service): self {
        $copy = clone $this;
        $copy->services[$class] = $service;
        return $copy;
    }

    /**
     * Get a service or throw.
     */
    public function get(string $class): object {
        if (!array_key_exists($class, $this->services)) {
            throw new RuntimeException("Service {$class} not provided");
        }
        return $this->services[$class];
    }

    public function has(string $class): bool {
        return array_key_exists($class, $this->services);
    }

    /**
     * Merge two contexts – **right‑bias** overrides duplicates.
     */
    public function merge(self $other): self {
        return new self([...$this->services, ...$other->services]);
    }
}