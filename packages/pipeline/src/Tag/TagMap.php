<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Tag;

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
     * @param array<string, TagInterface[]> $tags Tags indexed by class name
     */
    private function __construct(
        private array $tags = [],
    ) {}

    /**
     * @param TagInterface[] $tags Array of tags to include
     */
    public static function create(array $tags = []): self {
        $indexed = [];
        foreach ($tags as $tag) {
            $class = $tag::class;
            $indexed[$class] = $indexed[$class] ?? [];
            $indexed[$class][] = $tag;
        }
        return new self($indexed);
    }

    public static function empty(): self {
        return new self([]);
    }

    public function with(TagInterface ...$tags): self {
        $newTags = $this->tags;
        foreach ($tags as $tag) {
            $class = $tag::class;
            $newTags[$class] = $newTags[$class] ?? [];
            $newTags[$class][] = $tag;
        }
        return new self($newTags);
    }

    /**
     * @param class-string ...$tagClasses Classes of tags to remove
     */
    public function without(string ...$tagClasses): self {
        $newTags = $this->tags;
        foreach ($tagClasses as $class) {
            unset($newTags[$class]);
        }
        return new self($newTags);
    }

    /**
     * @return TagInterface[] Array of tags
     */
    public function all(?string $tagClass = null): array {
        return match (true) {
            ($tagClass === null) => Arrays::flatten($this->tags),
            default => $this->tags[$tagClass] ?? [],
        };
    }

    /**
     * @param class-string $tagClass Class of the tag to retrieve
     */
    public function last(string $tagClass): ?TagInterface {
        $tags = $this->tags[$tagClass] ?? [];
        return empty($tags) ? null : end($tags);
    }

    /**
     * @param class-string $tagClass Class of the tag to retrieve
     */
    public function first(string $tagClass): ?TagInterface {
        $tags = $this->tags[$tagClass] ?? [];
        return empty($tags) ? null : reset($tags);
    }

    /**
     * @param class-string $tagClass Class of the tag to check
     */
    public function has(string $tagClass): bool {
        return !empty($this->tags[$tagClass]);
    }

    /**
     * @param class-string|null $tagClass Optional class filter
     */
    public function count(?string $tagClass = null): int {
        if ($tagClass === null) {
            return array_sum(array_map('count', $this->tags));
        }

        return count($this->tags[$tagClass] ?? []);
    }

    public function isEmpty(): bool {
        return $this->count() === 0;
    }

    /**
     * @return class-string[] Array of tag class names
     */
    public function classes(): array {
        return array_keys($this->tags);
    }

    /**
     * @param class-string ...$tagClasses Classes of tags to include
     */
    public function only(string ...$tagClasses): self {
        $newTags = [];
        foreach ($tagClasses as $class) {
            if (isset($this->tags[$class])) {
                $newTags[$class] = $this->tags[$class];
            }
        }
        return new self($newTags);
    }

    public function merge(self $other): self {
        $newTags = $this->tags;
        foreach ($other->tags as $class => $tags) {
            $newTags[$class] = $newTags[$class] ?? [];
            $newTags[$class] = array_merge($newTags[$class], $tags);
        }
        return new self($newTags);
    }

    /**
     * @param class-string $tagClass Class of the tags to transform
     * @param callable(TagInterface):TagInterface $callback Function to apply to each tag
     */
    public function map(string $tagClass, callable $callback): self {
        if (!isset($this->tags[$tagClass])) {
            return $this;
        }

        $newTags = $this->tags;
        $newTags[$tagClass] = array_map($callback, $this->tags[$tagClass]);
        return new self($newTags);
    }

    /**
     * @param class-string $tagClass Class of the tags to filter
     * @param callable(TagInterface):bool $predicate Function that returns true for tags to keep
     */
    public function filter(string $tagClass, callable $predicate): self {
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
     * @return array<string, TagInterface[]> Tags indexed by class name
     */
    public function toArray(): array {
        return $this->tags;
    }
}