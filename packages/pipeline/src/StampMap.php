<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Utils\Arrays;

/**
 * Immutable collection for managing stamps indexed by class name.
 * 
 * StampMap provides efficient storage and retrieval of stamps while maintaining
 * immutability. All operations return new instances, making it safe for concurrent
 * access and ensuring predictable behavior in functional programming contexts.
 * 
 * The internal structure uses `array<class-string, array<StampInterface>>` for
 * O(1) class-based access while preserving insertion order within each class.
 * 
 * Key design principles:
 * - Immutable: Every operation returns a new instance
 * - Type-safe: Leverages PHP 8.2+ generics and type system
 * - Efficient: Class-based indexing for fast retrieval
 * - Intuitive: Clean API that mirrors common collection operations
 * 
 * Example usage:
 * ```php
 * $stamps = StampMap::create([
 *     new TimingStamp(1.0, 2.0, 1.0),
 *     new ErrorStamp('Connection failed'),
 *     new TimingStamp(2.0, 3.0, 1.0)  // Multiple stamps of same type
 * ]);
 * 
 * $newStamps = $stamps
 *     ->with(new RetryStamp(3))
 *     ->without(ErrorStamp::class)
 *     ->with(new TimingStamp(3.0, 4.0, 1.0));
 * 
 * $timings = $newStamps->all(TimingStamp::class);
 * $lastTiming = $newStamps->last(TimingStamp::class);
 * ```
 */
final readonly class StampMap
{
    /**
     * @param array<class-string, array<StampInterface>> $stamps Stamps indexed by class name
     */
    private function __construct(
        private array $stamps = []
    ) {}

    /**
     * Create a new StampMap from an array of stamps.
     * 
     * Stamps are automatically indexed by their class name for efficient retrieval.
     * Multiple stamps of the same type are stored in insertion order.
     * 
     * @param StampInterface[] $stamps Array of stamps to include
     * @return self New StampMap instance
     */
    public static function create(array $stamps = []): self
    {
        $indexed = [];
        foreach ($stamps as $stamp) {
            $class = $stamp::class;
            $indexed[$class] = $indexed[$class] ?? [];
            $indexed[$class][] = $stamp;
        }
        return new self($indexed);
    }

    /**
     * Create an empty StampMap.
     * 
     * @return self New empty StampMap instance
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Add stamps to the collection.
     * 
     * Creates a new StampMap with the additional stamps. Stamps of the same
     * type are appended to existing stamps, maintaining insertion order.
     * 
     * @param StampInterface ...$stamps Stamps to add
     * @return self New StampMap instance with added stamps
     */
    public function with(StampInterface ...$stamps): self
    {
        $newStamps = $this->stamps;
        foreach ($stamps as $stamp) {
            $class = $stamp::class;
            $newStamps[$class] = $newStamps[$class] ?? [];
            $newStamps[$class][] = $stamp;
        }
        return new self($newStamps);
    }

    /**
     * Remove all stamps of the specified type(s).
     * 
     * Creates a new StampMap without stamps of the specified classes.
     * Unknown classes are silently ignored.
     * 
     * @param class-string ...$stampClasses Classes of stamps to remove
     * @return self New StampMap instance without specified stamp types
     */
    public function without(string ...$stampClasses): self
    {
        $newStamps = $this->stamps;
        foreach ($stampClasses as $class) {
            unset($newStamps[$class]);
        }
        return new self($newStamps);
    }

    /**
     * Get all stamps, optionally filtered by class.
     * 
     * When no class is specified, returns all stamps in the order they were
     * added across all types. When a class is specified, returns only stamps
     * of that type in insertion order.
     * 
     * @param class-string|null $stampClass Optional class filter
     * @return StampInterface[] Array of stamps
     */
    public function all(?string $stampClass = null): array
    {
        return match(true) {
            $stampClass === null => Arrays::flatten($this->stamps),
            default => $this->stamps[$stampClass] ?? [],
        };
    }

    /**
     * Get the most recently added stamp of a specific type.
     * 
     * Returns the last stamp added of the specified class, or null if no
     * stamps of that type exist.
     * 
     * @template T of StampInterface
     * @param class-string<T> $stampClass Class of stamp to retrieve
     * @return T|null Most recent stamp of specified type, or null
     */
    public function last(string $stampClass): ?StampInterface
    {
        $stamps = $this->stamps[$stampClass] ?? [];
        return empty($stamps) ? null : end($stamps);
    }

    /**
     * Get the first stamp of a specific type.
     * 
     * Returns the first stamp added of the specified class, or null if no
     * stamps of that type exist.
     * 
     * @template T of StampInterface
     * @param class-string<T> $stampClass Class of stamp to retrieve
     * @return T|null First stamp of specified type, or null
     */
    public function first(string $stampClass): ?StampInterface
    {
        $stamps = $this->stamps[$stampClass] ?? [];
        return empty($stamps) ? null : reset($stamps);
    }

    /**
     * Check if stamps of a specific type exist.
     * 
     * @param class-string $stampClass Class of stamp to check for
     * @return bool True if stamps of specified type exist
     */
    public function has(string $stampClass): bool
    {
        return !empty($this->stamps[$stampClass]);
    }

    /**
     * Get count of stamps.
     * 
     * When no class is specified, returns total count across all types.
     * When a class is specified, returns count for that specific type.
     * 
     * @param class-string|null $stampClass Optional class filter
     * @return int Count of stamps
     */
    public function count(?string $stampClass = null): int
    {
        if ($stampClass === null) {
            return array_sum(array_map('count', $this->stamps));
        }

        return count($this->stamps[$stampClass] ?? []);
    }

    /**
     * Check if the StampMap is empty.
     * 
     * @return bool True if no stamps exist
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Get all stamp classes present in the collection.
     * 
     * Returns an array of class names for all stamp types currently
     * stored in the collection.
     * 
     * @return class-string[] Array of stamp class names
     */
    public function classes(): array
    {
        return array_keys($this->stamps);
    }

    /**
     * Create a new StampMap containing only stamps of the specified types.
     * 
     * @param class-string ...$stampClasses Classes to include
     * @return self New StampMap with only specified stamp types
     */
    public function only(string ...$stampClasses): self
    {
        $newStamps = [];
        foreach ($stampClasses as $class) {
            if (isset($this->stamps[$class])) {
                $newStamps[$class] = $this->stamps[$class];
            }
        }
        return new self($newStamps);
    }

    /**
     * Merge with another StampMap.
     * 
     * Creates a new StampMap containing stamps from both collections.
     * Stamps from the other map are appended after existing stamps
     * of the same type.
     * 
     * @param self $other StampMap to merge with
     * @return self New StampMap containing stamps from both collections
     */
    public function merge(self $other): self
    {
        $newStamps = $this->stamps;
        foreach ($other->stamps as $class => $stamps) {
            $newStamps[$class] = $newStamps[$class] ?? [];
            $newStamps[$class] = array_merge($newStamps[$class], $stamps);
        }
        return new self($newStamps);
    }

    /**
     * Apply a transformation to all stamps of a specific type.
     * 
     * Creates a new StampMap where all stamps of the specified type
     * are replaced with the result of applying the callback function.
     * 
     * @template T of StampInterface
     * @param class-string<T> $stampClass Class of stamps to transform
     * @param callable(T): StampInterface $callback Transformation function
     * @return self New StampMap with transformed stamps
     */
    public function map(string $stampClass, callable $callback): self
    {
        if (!isset($this->stamps[$stampClass])) {
            return $this;
        }

        $newStamps = $this->stamps;
        $newStamps[$stampClass] = array_map($callback, $this->stamps[$stampClass]);
        return new self($newStamps);
    }

    /**
     * Filter stamps of a specific type using a predicate.
     * 
     * Creates a new StampMap containing only stamps of the specified type
     * that pass the predicate test.
     * 
     * @template T of StampInterface
     * @param class-string<T> $stampClass Class of stamps to filter
     * @param callable(T): bool $predicate Filter predicate
     * @return self New StampMap with filtered stamps
     */
    public function filter(string $stampClass, callable $predicate): self
    {
        if (!isset($this->stamps[$stampClass])) {
            return $this;
        }

        $newStamps = $this->stamps;
        $filtered = array_filter($this->stamps[$stampClass], $predicate);
        
        if (empty($filtered)) {
            unset($newStamps[$stampClass]);
        } else {
            $newStamps[$stampClass] = array_values($filtered);
        }
        
        return new self($newStamps);
    }

    /**
     * Convert to array representation for debugging/serialization.
     * 
     * Returns the internal array structure with class names as keys
     * and arrays of stamps as values.
     * 
     * @return array<class-string, array<StampInterface>> Internal array structure
     */
    public function toArray(): array
    {
        return $this->stamps;
    }
}