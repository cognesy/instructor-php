<?php declare(strict_types=1);

namespace Cognesy\Utils;

use Closure;
use Stringable;
use WeakMap;

/**
 * A container for a value that is expensive to compute or can only be retrieved once.
 * The value is resolved on the first call to get() and then cached internally.
 *
 * @template T
 */
final class Cached
{
    /** @var WeakMap<object, mixed> */
    private static WeakMap $cache;

    /**
     * @param (Closure(mixed...): mixed)|null $producer
     */
    private function __construct(
        private readonly ?Closure $producer,
        private readonly mixed $value = null,
        private readonly bool $isResolved = false
    ) {
        /** @phpstan-ignore-next-line */
        self::$cache ??= new WeakMap();
    }

    /**
     * Creates a lazy-loaded Cached instance from a producer callable.
     * The producer will be called only on the first access.
     *
     * @template TValue
     * @param callable(mixed...): TValue $producer
     * @return self<TValue>
     */
    public static function from(callable $producer): self {
        return new self(
            producer: $producer instanceof Closure ? $producer : Closure::fromCallable($producer),
            isResolved: false
        );
    }

    /**
     * Creates an eagerly-loaded Cached instance from a value that is already resolved.
     *
     * @template TValue
     * @param TValue $value
     * @return self<TValue>
     */
    public static function withValue(mixed $value): self {
        return new self(producer: null, value: $value, isResolved: true);
    }

    /**
     * Checks if the value has been resolved and is currently cached.
     */
    public function isResolved(): bool {
        return $this->isResolved || isset(self::$cache[$this]);
    }

    /**
     * Retrieves the value.
     * If the value is not cached, it executes the producer, caches the result, and returns it.
     * Any arguments are forwarded to the producer *only* on the first call.
     *
     * @param mixed ...$args
     * @return T The cached value.
     */
    public function get(mixed ...$args): mixed {
        if ($this->isResolved) {
            return $this->value;
        }

        if (isset(self::$cache[$this])) {
            return self::$cache[$this];
        }

        if ($this->producer === null) {
            throw new \RuntimeException('Cached value is not resolved and no producer is available.');
        }

        return self::$cache[$this] = ($this->producer)(...$args);
    }

    /**
     * Allows retrieving the value by invoking the object as a function.
     * e.g., $cachedValue(...$args)
     *
     * @param mixed ...$args
     * @return T
     */
    public function __invoke(mixed ...$args): mixed {
        return $this->get(...$args);
    }

    /**
     * Provides a safe string representation for debugging.
     */
    public function __toString(): string {
        if (!$this->isResolved()) {
            return '(unresolved)';
        }

        $value = $this->isResolved ? $this->value : self::$cache[$this];
        return match (true) {
            $value === null => 'NULL',
            is_string($value) => $value,
            is_scalar($value) => (string) $value,
            $value instanceof Stringable => (string) $value,
            is_object($value) => 'object(' . $value::class . ')',
            default => gettype($value),
        };
    }
}