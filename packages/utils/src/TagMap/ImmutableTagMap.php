<?php declare(strict_types=1);

namespace Cognesy\Utils\TagMap;

use Cognesy\Utils\Arrays;
use Cognesy\Utils\TagMap\Contracts\TagInterface;
use Cognesy\Utils\TagMap\Contracts\TagMapInterface;

/**
 * Immutable collection for managing tags indexed by class name.
 *
 * Tag map provides efficient storage and retrieval of tags while maintaining
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
 * $tags = SimpleTagMap::create([
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
final readonly class ImmutableTagMap implements TagMapInterface
{
    /**
     * @param array<class-string, array<TagInterface>> $tags Tags indexed by class name
     */
    private function __construct(
        private array $tags = [],
    ) {}

    // CONSTRUCTORS

    /**
     * @param TagInterface[] $tags Array of tags to include
     */
    #[\Override]
    public static function create(array $tags = []): self {
        return match(true) {
            empty($tags) => self::empty(),
            default => new self(self::addTagsTo([], $tags)),
        };
    }

    #[\Override]
    public static function empty(): self {
        return new self([]);
    }

    // PUBLIC API

    /**
     * @param class-string|null $tagClass Optional class filter
     */
    public function count(?string $tagClass = null): int {
        if ($tagClass === null) {
            return array_sum(array_map('count', $this->tags));
        }
        return count($this->tags[$tagClass] ?? []);
    }

    /**
     * @return TagInterface[]
     */
    #[\Override]
    public function getAllInOrder(): array {
        return Arrays::flatten($this->tags);
    }

    /**
     * @param class-string $tagClass Class of the tag to check
     */
    #[\Override]
    public function has(string $tagClass): bool {
        return !empty($this->tags[$tagClass]);
    }

    #[\Override]
    public function isEmpty(): bool {
        return $this->count() === 0;
    }

    /**
     * @param class-string $tagClass Class of the tag to retrieve
     */
    public function last(string $tagClass): ?TagInterface {
        $tags = $this->tags[$tagClass] ?? [];
        return empty($tags) ? null : end($tags);
    }

    #[\Override]
    public function merge(TagMapInterface $other): TagMapInterface {
        $otherTags = $this->extractTagsFrom($other);
        return new self(self::mergeGroupedTags($this->tags, $otherTags));
    }

    #[\Override]
    public function mergeInto(TagMapInterface $target): TagMapInterface {
        $targetTags = $this->extractTagsFrom($target);
        return new self(self::mergeGroupedTags($targetTags, $this->tags));
    }

    #[\Override]
    public function newInstance(array $tags): TagMapInterface {
        // If already grouped by class-string, use directly
        if ($this->isGroupedArray($tags)) {
            /** @var array<class-string, array<array-key, TagInterface>> $tags */
            return new self($tags);
        }
        // Otherwise, group the flat array
        return new self(self::addTagsTo([], $tags));
    }

    #[\Override]
    public function query(): TagQuery {
        return new TagQuery($this);
    }

    #[\Override]
    public function add(TagInterface ...$tags): self {
        return new self(self::addTagsTo($this->tags, $tags));
    }

    #[\Override]
    public function replace(TagInterface ...$tags): TagMapInterface {
        return self::create($tags);
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////////

    /**
     * Extract grouped tags from another TagMapInterface instance
     * @return array<class-string, array<TagInterface>>
     */
    private function extractTagsFrom(TagMapInterface $other): array {
        if ($other instanceof self) {
            return $other->tags;
        }
        // For other implementations, rebuild from flat array
        return self::addTagsTo([], $other->getAllInOrder());
    }

    private static function addTagsTo(array $target, array $tags) : array {
        foreach ($tags as $tag) {
            $class = $tag::class;
            $target[$class] = $target[$class] ?? [];
            $target[$class][] = $tag;
        }
        return $target;
    }

    private static function mergeGroupedTags(array $target, array $tags): array {
        foreach ($tags as $class => $tagList) {
            $target[$class] = $target[$class] ?? [];
            $target[$class] = array_merge($target[$class], $tagList);
        }
        return $target;
    }

    /**
     * Check if array is already grouped by class-string
     * @param array<array-key, mixed> $tags
     */
    private function isGroupedArray(array $tags): bool {
        if (empty($tags)) {
            return false;
        }
        // Check if first key is a class-string and first value is an array
        $firstKey = array_key_first($tags);
        return is_string($firstKey)
            && class_exists($firstKey)
            && is_array($tags[$firstKey]);
    }
}
