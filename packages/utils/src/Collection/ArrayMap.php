<?php declare(strict_types=1);

namespace Cognesy\Utils\Collection;

use ArrayIterator;
use Cognesy\Utils\Collection\Contracts\MapInterface;
use OutOfBoundsException;
use Traversable;

/**
 * @template K of array-key
 * @template V
 * @implements MapInterface<K,V>
 */
final class ArrayMap implements MapInterface
{
    /** @var array<K,V> */
    private array $entries;

    /**
     * @param array<K,V> $entries
     */
    private function __construct(array $entries) {
        $this->entries = $entries;
    }

    /**
     * @template K2 of array-key
     * @template V2
     * @param array<K2,V2> $entries
     * @return ArrayMap<K2,V2>
     */
    public static function fromArray(array $entries): self {
        return new self($entries);
    }

    /**
     * @template K2 of array-key
     * @template V2
     * @return ArrayMap<K2,V2>
     */
    public static function empty(): self {
        /** @var array<K2,V2> $e */
        $e = [];
        return new self($e);
    }

    public function count(): int {
        return count($this->entries);
    }

    public function has(int|string $key): bool {
        return array_key_exists($key, $this->entries);
    }

    public function get(int|string $key): mixed {
        if (!array_key_exists($key, $this->entries)) {
            throw new OutOfBoundsException("ArrayMap key not found: {$key}");
        }
        return $this->entries[$key];
    }

    public function getOrNull(int|string $key): mixed {
        return $this->entries[$key] ?? null;
    }

    public function with(int|string $key, mixed $value): static {
        $n = $this->entries;
        /** @var array<array-key,mixed> $n */
        $n[$key] = $value;
        return new self($n);
    }

    public function withAll(array $entries): static {
        return new self($this->entries + $entries); // existing keys preserved; change to array_replace for overwrite
    }

    public function withRemoved(int|string $key): static {
        if (!array_key_exists($key, $this->entries)) {
            return $this; // idempotent
        }
        $n = $this->entries;
        unset($n[$key]);
        return new self($n);
    }

    public function merge(MapInterface $other): static {
        // other wins on collisions (array_replace)
        return new self(array_replace($this->entries, $other->toArray()));
    }

    /** @return list<K> */
    public function keys(): array {
        /** @var list<K> $k */
        $k = array_keys($this->entries);
        return $k;
    }

    /** @return list<V> */
    public function values(): array {
        /** @var list<V> $v */
        $v = array_values($this->entries);
        return $v;
    }

    /** @return array<K,V> */
    public function toArray(): array {
        return $this->entries;
    }

    /** @return Traversable<K,V> */
    public function getIterator(): Traversable {
        return new ArrayIterator($this->entries);
    }
}