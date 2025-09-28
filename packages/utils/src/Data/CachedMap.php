<?php declare(strict_types=1);

namespace Cognesy\Utils\Data;

use ArrayAccess;
use Closure;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * A lightweight, lazy-loading map that caches values computed by a producer.
 * Supports array-like access, flexible key types, and cache introspection.
 *
 * @template TKey of array-key
 * @template TValue
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 */
final class CachedMap implements ArrayAccess, IteratorAggregate, Countable
{
    /** @var Closure(TKey, mixed...): TValue */
    private Closure $producer;

    /** @var array<TKey, TValue> */
    private array $cache = [];

    /** @var array<TKey, true> */
    private array $resolved = [];

    /**
     * @param callable(TKey, mixed...): TValue $producer
     * @param array<TKey, TValue> $preloaded
     */
    public function __construct(callable $producer, array $preloaded = [])
    {
        $this->producer = $producer instanceof Closure ? $producer : Closure::fromCallable($producer);
        foreach ($preloaded as $key => $value) {
            $this->cache[$key] = $value;
            $this->resolved[$key] = true;
        }
    }

    /**
     * Convenience factory.
     *
     * @param callable(TKey, mixed...): TValue $producer
     * @param array<TKey, TValue> $preloaded
     * @return self<TKey, TValue>
     */
    public static function from(callable $producer, array $preloaded = []): self
    {
        return new self($producer, $preloaded);
    }

    /**
     * Gets the cached or computed value for a key.
     *
     * @param TKey $key
     * @param mixed ...$args Passed to producer if not cached.
     * @return TValue
     */
    public function get(int|string $key, mixed ...$args): mixed
    {
        if (!isset($this->resolved[$key])) {
            $this->cache[$key] = ($this->producer)($key, ...$args);
            $this->resolved[$key] = true;
        }
        return $this->cache[$key];
    }

    /**
     * Manually sets a value, bypassing the producer.
     *
     * @param TKey $key
     * @param TValue $value
     */
    public function set(int|string $key, mixed $value): void
    {
        $this->cache[$key] = $value;
        $this->resolved[$key] = true;
    }

    /**
     * Checks if a key exists in the cache (resolved or not).
     *
     * @param TKey $key
     */
    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }

    /**
     * Checks if a key has been resolved (computed or manually set).
     *
     * @param TKey $key
     */
    public function isResolved(int|string $key): bool
    {
        return isset($this->resolved[$key]);
    }

    /**
     * Clears the cache for a specific key, forcing re-computation on next access.
     *
     * @param TKey $key
     */
    public function forget(int|string $key): void
    {
        unset($this->cache[$key], $this->resolved[$key]);
    }

    /**
     * Clears all cached values and resolved states.
     */
    public function fresh(): void
    {
        $this->cache = [];
        $this->resolved = [];
    }

    /**
     * Returns all keys in the cache (resolved or not).
     *
     * @return TKey[]
     */
    public function keys(): array {
        return array_keys($this->cache);
    }

    public function toArray(): array {
        return $this->cache;
    }

    // ARRAY ACCESS /////////////////////////////////////////

    public function offsetExists(mixed $offset): bool {
        return $this->isResolved($offset);
    }

    public function offsetGet(mixed $offset): mixed {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void {
        $this->set($offset, $value);
    }

    public function offsetUnset(mixed $offset): void {
        $this->forget($offset);
    }

    // ITERATOR AGGREGATE ///////////////////////////////////

    public function getIterator(): Traversable {
        yield from $this->cache;
    }

    /** Number of resolved entries. */
    public function count(): int {
        return \count($this->resolved);
    }
}
