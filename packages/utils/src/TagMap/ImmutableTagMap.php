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
     * @param TagInterface[] $tags Tags indexed by class name
     */
    private function __construct(
        private array $tags = [],
    ) {}

    // CONSTRUCTORS

    /**
     * @param TagInterface[] $tags Array of tags to include
     */
    public static function create(array $tags = []): self {
        return match(true) {
            empty($tags) => self::empty(),
            default => new self(self::addTagsTo([], $tags)),
        };
    }

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
    public function getAllInOrder(): array {
        return Arrays::flatten($this->tags);
    }

    /**
     * @param class-string $tagClass Class of the tag to check
     */
    public function has(string $tagClass): bool {
        return !empty($this->tags[$tagClass]);
    }

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

    public function merge(TagMapInterface $other): TagMapInterface {
        return new self(self::mergeGroupedTags($this->tags, $other->tags));
    }

    public function mergeInto(TagMapInterface $target): TagMapInterface {
        return new self(self::mergeGroupedTags($target->tags, $this->tags));
    }

    public function newInstance(array $tags): TagMapInterface {
        return new self($tags);
    }

    public function query(): TagQuery {
        return new TagQuery($this);
    }

    public function add(TagInterface ...$tags): self {
        return new self(self::addTagsTo($this->tags, $tags));
    }

    public function replace(TagInterface ...$tags): TagMapInterface {
        return self::create($tags);
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////////

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
}
