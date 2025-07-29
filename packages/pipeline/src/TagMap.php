<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Utils\Arrays;

/**
 * Immutable collection for managing tags indexed by class name.
 * 
 * TagMap provides efficient storage and retrieval of tags while maintaining
 * immutability. All operations return new instances, making it safe for concurrent
 * access and ensuring predictable behavior in functional programming contexts.
 * 
 * The internal structure uses `array<class-string, array<TagInterface>>` for
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
 * $tags = TagMap::create([
 *     new TimingTag(1.0, 2.0, 1.0),
 *     new ErrorTag('Connection failed'),
 *     new TimingTag(2.0, 3.0, 1.0)  // Multiple tags of same type
 * ]);
 * 
 * $newTags = $tags
 *     ->with(new RetryTag(3))
 *     ->without(ErrorTag::class)
 *     ->with(new TimingTag(3.0, 4.0, 1.0));
 * 
 * $timings = $newTags->all(TimingTag::class);
 * $lastTiming = $newTags->last(TimingTag::class);
 * ```
 */
final readonly class TagMap
{
    /**
     * @param array<class-string, array<TagInterface>> $tags Tags indexed by class name
     */
    private function __construct(
        private array $tags = []
    ) {}

    /**
     * Create a new TagMap from an array of tags.
     * 
     * Tags are automatically indexed by their class name for efficient retrieval.
     * Multiple tags of the same type are stored in insertion order.
     * 
     * @param TagInterface[] $tags Array of tags to include
     * @return self New TagMap instance
     */
    public static function create(array $tags = []): self
    {
        $indexed = [];
        foreach ($tags as $tag) {
            $class = $tag::class;
            $indexed[$class] = $indexed[$class] ?? [];
            $indexed[$class][] = $tag;
        }
        return new self($indexed);
    }

    /**
     * Create an empty TagMap.
     * 
     * @return self New empty TagMap instance
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Add tags to the collection.
     * 
     * Creates a new TagMap with the additional tags. Tags of the same
     * type are appended to existing tags, maintaining insertion order.
     * 
     * @param TagInterface ...$tags Tags to add
     * @return self New TagMap instance with added tags
     */
    public function with(TagInterface ...$tags): self
    {
        $newTags = $this->tags;
        foreach ($tags as $tag) {
            $class = $tag::class;
            $newTags[$class] = $newTags[$class] ?? [];
            $newTags[$class][] = $tag;
        }
        return new self($newTags);
    }

    /**
     * Remove all tags of the specified type(s).
     * 
     * Creates a new TagMap without tags of the specified classes.
     * Unknown classes are silently ignored.
     * 
     * @param class-string ...$tagClasses Classes of tags to remove
     * @return self New TagMap instance without specified tag types
     */
    public function without(string ...$tagClasses): self
    {
        $newTags = $this->tags;
        foreach ($tagClasses as $class) {
            unset($newTags[$class]);
        }
        return new self($newTags);
    }

    /**
     * Get all tags, optionally filtered by class.
     * 
     * When no class is specified, returns all tags in the order they were
     * added across all types. When a class is specified, returns only tags
     * of that type in insertion order.
     * 
     * @param class-string|null $tagClass Optional class filter
     * @return TagInterface[] Array of tags
     */
    public function all(?string $tagClass = null): array
    {
        return match(true) {
            $tagClass === null => Arrays::flatten($this->tags),
            default => $this->tags[$tagClass] ?? [],
        };
    }

    /**
     * Get the most recently added tag of a specific type.
     * 
     * Returns the last tag added of the specified class, or null if no
     * tags of that type exist.
     * 
     * @template T of TagInterface
     * @param class-string<T> $tagClass Class of tag to retrieve
     * @return T|null Most recent tag of specified type, or null
     */
    public function last(string $tagClass): ?TagInterface
    {
        $tags = $this->tags[$tagClass] ?? [];
        return empty($tags) ? null : end($tags);
    }

    /**
     * Get the first tag of a specific type.
     * 
     * Returns the first tag added of the specified class, or null if no
     * tags of that type exist.
     * 
     * @template T of TagInterface
     * @param class-string<T> $tagClass Class of tag to retrieve
     * @return T|null First tag of specified type, or null
     */
    public function first(string $tagClass): ?TagInterface
    {
        $tags = $this->tags[$tagClass] ?? [];
        return empty($tags) ? null : reset($tags);
    }

    /**
     * Check if tags of a specific type exist.
     * 
     * @param class-string $tagClass Class of tag to check for
     * @return bool True if tags of specified type exist
     */
    public function has(string $tagClass): bool
    {
        return !empty($this->tags[$tagClass]);
    }

    /**
     * Get count of tags.
     * 
     * When no class is specified, returns total count across all types.
     * When a class is specified, returns count for that specific type.
     * 
     * @param class-string|null $tagClass Optional class filter
     * @return int Count of tags
     */
    public function count(?string $tagClass = null): int
    {
        if ($tagClass === null) {
            return array_sum(array_map('count', $this->tags));
        }

        return count($this->tags[$tagClass] ?? []);
    }

    /**
     * Check if the TagMap is empty.
     * 
     * @return bool True if no tags exist
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Get all tag classes present in the collection.
     * 
     * Returns an array of class names for all tag types currently
     * stored in the collection.
     * 
     * @return class-string[] Array of tag class names
     */
    public function classes(): array
    {
        return array_keys($this->tags);
    }

    /**
     * Create a new TagMap containing only tags of the specified types.
     * 
     * @param class-string ...$tagClasses Classes to include
     * @return self New TagMap with only specified tag types
     */
    public function only(string ...$tagClasses): self
    {
        $newTags = [];
        foreach ($tagClasses as $class) {
            if (isset($this->tags[$class])) {
                $newTags[$class] = $this->tags[$class];
            }
        }
        return new self($newTags);
    }

    /**
     * Merge with another TagMap.
     * 
     * Creates a new TagMap containing tags from both collections.
     * Tags from the other map are appended after existing tags
     * of the same type.
     * 
     * @param self $other TagMap to merge with
     * @return self New TagMap containing tags from both collections
     */
    public function merge(self $other): self
    {
        $newTags = $this->tags;
        foreach ($other->tags as $class => $tags) {
            $newTags[$class] = $newTags[$class] ?? [];
            $newTags[$class] = array_merge($newTags[$class], $tags);
        }
        return new self($newTags);
    }

    /**
     * Apply a transformation to all tags of a specific type.
     * 
     * Creates a new TagMap where all tags of the specified type
     * are replaced with the result of applying the callback function.
     * 
     * @template T of TagInterface
     * @param class-string<T> $tagClass Class of tags to transform
     * @param callable(T): TagInterface $callback Transformation function
     * @return self New TagMap with transformed tags
     */
    public function map(string $tagClass, callable $callback): self
    {
        if (!isset($this->tags[$tagClass])) {
            return $this;
        }

        $newTags = $this->tags;
        $newTags[$tagClass] = array_map($callback, $this->tags[$tagClass]);
        return new self($newTags);
    }

    /**
     * Filter tags of a specific type using a predicate.
     * 
     * Creates a new TagMap containing only tags of the specified type
     * that pass the predicate test.
     * 
     * @template T of TagInterface
     * @param class-string<T> $tagClass Class of tags to filter
     * @param callable(T): bool $predicate Filter predicate
     * @return self New TagMap with filtered tags
     */
    public function filter(string $tagClass, callable $predicate): self
    {
        if (!isset($this->tags[$tagClass])) {
            return $this;
        }

        $newTags = $this->tags;
        $filtered = array_filter($this->tags[$tagClass], $predicate);
        
        if (empty($filtered)) {
            unset($newTags[$tagClass]);
        } else {
            $newTags[$tagClass] = array_values($filtered);
        }
        
        return new self($newTags);
    }

    /**
     * Convert to array representation for debugging/serialization.
     * 
     * Returns the internal array structure with class names as keys
     * and arrays of tags as values.
     * 
     * @return array<class-string, array<TagInterface>> Internal array structure
     */
    public function toArray(): array
    {
        return $this->tags;
    }
}