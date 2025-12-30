<?php declare(strict_types=1);

namespace Cognesy\Utils\Context;

use Cognesy\Utils\Context\Exceptions\MissingServiceException;
use Cognesy\Utils\Result\Result;
use TypeError;

/**
 * Immutable, typed service context.
 *
 * Provides strongly-typed registration and retrieval via class-string<T> keys.
 * Static analyzers (PHPStan/Psalm) infer types through the generic annotations.
 */
final class Context
{
    /**
     * @var array<class-string, object>
     */
    private array $services;
    /**
     * @var array<string, object>
     */
    private array $keyed = [];

    /**
     * @param array<class-string, object> $services
     */
    public function __construct(array $services = []) {
        $this->services = $services;
    }

    public static function empty(): self {
        return new self();
    }

    /**
     * Return new context with service added/replaced.
     *
     * @template T of object
     * @param class-string<T> $class
     * @param T $service
     */
    public function with(string $class, object $service): self {
        if (!$service instanceof $class) {
            throw new TypeError("Service must be instance of {$class}, got " . get_debug_type($service));
        }
        $copy = clone $this;
        $copy->services[$class] = $service;
        return $copy;
    }

    /**
     * Get a service or throw.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     * @phpstan-return T
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement - Generic type preserved in array storage
     */
    public function get(string $class): object {
        if (!array_key_exists($class, $this->services)) {
            throw new MissingServiceException($class);
        }
        /** @var T */
        return $this->services[$class];
    }

    /**
     * Try to get a service as a Result.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return Result<T, MissingServiceException>
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement - Result monad preserves generic type
     */
    public function tryGet(string $class): Result {
        return array_key_exists($class, $this->services)
            ? Result::success($this->services[$class])
            : Result::failure(new MissingServiceException($class));
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     */
    public function has(string $class): bool {
        return array_key_exists($class, $this->services);
    }

    /**
     * Merge two contexts – right-bias overrides duplicates.
     */
    public function merge(self $other): self {
        $merged = new self([...$this->services, ...$other->services]);
        $merged->keyed = [...$this->keyed, ...$other->keyed];
        return $merged;
    }

    /**
     * Return new context with keyed service added/replaced.
     *
     * @template T of object
     * @param Key<T> $key
     * @param T $service
     */
    public function withKey(Key $key, object $service): self {
        if (!$service instanceof $key->type) {
            throw new TypeError("Service for key '{$key->id}' must be instance of {$key->type}, got " . get_debug_type($service));
        }
        $copy = clone $this;
        $copy->keyed[$key->id] = $service;
        return $copy;
    }

    /**
     * Get a keyed service or throw.
     *
     * @template T of object
     * @param Key<T> $key
     * @return T
     * @phpstan-return T
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement - Generic type preserved in keyed storage
     */
    public function getKey(Key $key): object {
        if (!array_key_exists($key->id, $this->keyed)) {
            // re-use MissingService for consistency (typed by expected class)
            throw new MissingServiceException($key->type);
        }
        /** @var T */
        return $this->keyed[$key->id];
    }
}
