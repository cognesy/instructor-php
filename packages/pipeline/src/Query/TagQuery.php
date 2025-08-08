<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Query;

use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\Contracts\TagMapInterface;

/**
 * Fluent query interface for tag maps.
 *
 * Provides chainable operations for filtering and accessing tags.
 * All operations are lazy until a terminal operation is called.
 */
final class TagQuery
{
    private array $tags;
    private TagMapInterface $tagMap;

    public function __construct(TagMapInterface $tagMap) {
        $this->tagMap = $tagMap;
        $this->tags = $tagMap->getAllInOrder();
    }

    /**
     * @param class-string $class
     */
    public function ofType(string $class): self {
        $filtered = array_filter(
            $this->tags,
            fn(TagInterface $tag): bool => $tag instanceof $class,
        );
        return new self($this->tagMap->newInstance(array_values($filtered)));
    }

    public function where(callable $predicate): self {
        $filtered = array_filter($this->tags, $predicate);
        return new self($this->tagMap->newInstance(array_values($filtered)));
    }

    public function limit(int $count): self {
        $limited = array_slice($this->tags, 0, $count);
        return new self($this->tagMap->newInstance($limited));
    }

    public function skip(int $count): self {
        $skipped = array_slice($this->tags, $count);
        return new self($this->tagMap->newInstance($skipped));
    }

    /**
     * @param class-string ...$tagClasses Classes of tags to include
     */
    public function only(string ...$tagClasses): self {
        $filtered = array_filter(
            $this->tags,
            fn(TagInterface $tag): bool => in_array(get_class($tag), $tagClasses, true),
        );
        return new self($this->tagMap->newInstance(array_values($filtered)));
    }

    /**
     * @param class-string ...$tagClasses Classes of tags to remove
     */
    public function without(string ...$tagClasses): self {
        $filtered = array_filter(
            $this->tags,
            fn(TagInterface $tag): bool => !in_array(get_class($tag), $tagClasses, true),
        );
        return new self($this->tagMap->newInstance(array_values($filtered)));
    }

    // TERMINAL OPERATIONS (execute the query)

    /**
     * @return array<TagInterface>
     */
    public function all(): array {
        return $this->tags;
    }

    public function any(callable $predicate): bool {
        foreach ($this->tags as $tag) {
            if ($predicate($tag)) {
                return true;
            }
        }
        return false;
    }

    public function at(int $index): ?TagInterface {
        return $this->tags[$index] ?? null;
    }

    /**
     * @return class-string[] Array of tag class names
     */
    public function classes(): array {
        return array_unique(
            array_map(
                fn(TagInterface $tag): string => get_class($tag),
                $this->tags,
            )
        );
    }

    public function count(): int {
        return count($this->tags);
    }

    public function empty(): bool {
        return empty($this->tags);
    }

    public function every(callable $predicate): bool {
        foreach ($this->tags as $tag) {
            if (!$predicate($tag)) {
                return false;
            }
        }
        return true;
    }

    public function first(): ?TagInterface {
        return $this->tags[0] ?? null;
    }

    /**
     * @param class-string $tagClass Class of the tag to check
     */
    public function has(string $tagClass): bool {
        return !empty(array_filter($this->tags, fn(TagInterface $tag): bool => $tag instanceof $tagClass));
    }

    /**
     * @param array<TagInterface> $tags Array of tags to check
     */
    public function hasAll(array $array) : bool {
        foreach ($array as $tag) {
            if (!$this->has($tag::class)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<TagInterface> $tags Array of tags to check
     */
    public function hasAny(array $array): bool {
        foreach ($array as $tag) {
            if ($this->has($tag::class)) {
                return true;
            }
        }
        return false;
    }

    public function last(): ?TagInterface {
        return empty($this->tags) ? null : end($this->tags);
    }

    /**
     * @return array<mixed>
     */
    public function map(callable $transformer): array {
        return array_map($transformer, $this->tags);
    }

    public function reduce(callable $reducer, mixed $initial = null): mixed {
        return array_reduce($this->tags, $reducer, $initial);
    }

    /**
     * @return array<string, TagInterface[]> Tags indexed by class name
     */
    public function toArray(): array {
        return array_map(
            fn(TagInterface $tag): array => [$tag::class => $tag],
            $this->tags
        );
    }
}