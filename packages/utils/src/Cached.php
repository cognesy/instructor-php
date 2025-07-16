<?php declare(strict_types=1);

namespace Cognesy\Utils;

use Closure;
use RuntimeException;
use Stringable;

/**
 * A container for a value that is expensive to compute or can only be retrieved once.
 * The value is resolved on the first call to get() and then cached internally.
 *
 * @template T
 */
final class Cached
{
    private ?Closure $producer;
    /** @var T */
    private mixed $value;
    private bool $isResolved = false;

    /**
     * The constructor is private to enforce clarity through named static constructors.
     */
    private function __construct() {}

    /**
     * Creates a lazy-loaded Cached instance from a producer callable.
     * The producer will be called only on the first access.
     *
     * @param callable(mixed...): T $producer
     * @return self<T>
     */
    public static function from(callable $producer): self {
        $instance = new self();
        $instance->producer = $producer instanceof Closure ? $producer : Closure::fromCallable($producer);
        return $instance;
    }

    /**
     * Creates an eagerly-loaded Cached instance from a value that is already resolved.
     *
     * @param T $value
     * @return self<T>
     */
    public static function withValue(mixed $value): self {
        $instance = new self();
        $instance->value = $value;
        $instance->isResolved = true;
        $instance->producer = null;
        return $instance;
    }

    /**
     * Checks if the value has been resolved and is currently cached.
     */
    public function isResolved(): bool {
        return $this->isResolved;
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

        if ($this->producer === null) {
            // This state is only reachable if fresh() is called on a value-based instance, which is prevented.
            throw new RuntimeException('Cached value is not resolved and no producer is available.');
        }

        $this->value = ($this->producer)(...$args);
        $this->isResolved = true;

        return $this->value;
    }

    /**
     * Resets the cache, forcing re-computation on the next get() call.
     * This method does nothing if the instance was created from a value, as there is no producer to re-run.
     */
    public function fresh(): self {
        // Cannot make a value "fresh" if it has no producer.
        if ($this->producer !== null) {
            $this->isResolved = false;
            unset($this->value); // Free memory
        }
        return $this;
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
        if (!$this->isResolved) {
            return '(unresolved)';
        }

        $value = $this->value;
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