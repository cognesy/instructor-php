<?php declare(strict_types=1);

namespace Cognesy\Utils;

use Closure;
use Stringable;

/**
 * A container for a value that is expensive to compute or can only be retrieved once.
 * The value is resolved on the first call to get() and then cached internally.
 *
 * @template T
 */
final class Cached
{
    /**
     * Resolved state lives on the instance. It used to live in a static
     * WeakMap keyed by $this, to work around the readonly properties, but
     * WeakMap cannot represent a cached null: both isset() and offsetExists()
     * report a stored null as absent, so a producer returning null was re-run
     * on every get() and isResolved() never turned true. The properties are
     * private on a final class, so dropping readonly changes no public contract.
     *
     * @param (Closure(mixed...): mixed)|null $producer
     */
    private function __construct(
        private readonly ?Closure $producer,
        private mixed $value = null,
        private bool $isResolved = false
    ) {}

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
    public static function fromValue(mixed $value): self {
        return new self(producer: null, value: $value, isResolved: true);
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
            throw new \RuntimeException('Cached value is not resolved and no producer is available.');
        }

        $this->value = ($this->producer)(...$args);
        $this->isResolved = true;

        return $this->value;
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
