<?php declare(strict_types=1);

namespace Cognesy\Utils\TagMap;

use Cognesy\Utils\TagMap\Contracts\TagInterface;
use Cognesy\Utils\TagMap\Contracts\TagMapInterface;

/**
 * Fluent query interface for tag maps.
 *
 * Provides chainable operations for filtering and accessing tags.
 * All operations are lazy until a terminal operation is called.
 */
final readonly class TagQuery
{
    private array $tags;
    private TagMapInterface $tagMap;

    public function __construct(TagMapInterface $tagMap) {
        $this->tagMap = $tagMap;
        $this->tags = $this->tagMap->getAllInOrder();
    }

    // TRANSFORMATIONS

    /** @param callable(TagInterface):bool $predicate */
    public function filter(callable $predicate): self {
        $filtered = array_filter($this->tags, $predicate);
        return new self($this->tagMap->newInstance(array_values($filtered)));
    }

    public function limit(int $count): self {
        $limited = array_slice($this->tags, 0, $count);
        return new self($this->tagMap->newInstance($limited));
    }

    /**
     * @param TagInterface $tagClass Class of the tag to map
     * @param callable(TagInterface):TagInterface $callback Function to apply to each tag
     * */
    public function map(callable $callback): self {
        $tags = [];
        foreach ($this->tags as $tag) {
            $tags[] = $callback($tag);
        }
        return new self($this->tagMap->newInstance($tags));
    }

    /** @param TagInterface $class */
    public function ofType(string $class): self {
        $filtered = array_filter(
            $this->tags,
            fn(TagInterface $tag): bool => $tag instanceof $class,
        );
        return new self($this->tagMap->newInstance(array_values($filtered)));
    }

    /** @param TagInterface ...$tagClasses Classes of tags to include */
    public function only(string ...$tagClasses): self {
        $filtered = array_filter(
            $this->tags,
            fn(TagInterface $tag): bool => in_array(get_class($tag), $tagClasses, true),
        );
        return new self($this->tagMap->newInstance(array_values($filtered)));
    }

    public function skip(int $count): self {
        $skipped = array_slice($this->tags, $count);
        return new self($this->tagMap->newInstance($skipped));
    }

    public function tag(string $class): self {
        if ($this->tagMap->has($class)) {
            return $this;
        }
        return $this->only($class);
    }

    /** @param TagInterface ...$tagClasses Classes of tags to remove */
    public function without(string ...$tagClasses): self {
        $filtered = array_filter(
            $this->tags,
            fn(TagInterface $tag): bool => !in_array(get_class($tag), $tagClasses, true),
        );
        return new self($this->tagMap->newInstance(array_values($filtered)));
    }

    // TERMINAL OPERATIONS (execute the query)

    public function get(): TagMapInterface {
        return $this->tagMap;
    }

    /** @return array<TagInterface> */
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

    /** @return TagInterface Array of tag class names */
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

    /** @param TagInterface $tag Class of the tag to check */
    public function has(string|TagInterface $tag): bool {
        return match(true) {
            $tag instanceof TagInterface => in_array(get_class($tag), $this->classes(), true),
            is_string($tag) => in_array($tag, $this->classes(), true),
            default => throw new \InvalidArgumentException('Expected TagInterface or class string, got ' . get_debug_type($tag)),
        };
    }

    /** @param TagInterface $tags Array of tags to check */
    public function hasAll(string|TagInterface ...$tags) : bool {
        foreach ($tags as $tag) {
            if (!$this->has($tag)) {
                return false;
            }
        }
        return true;
    }

    /** @param TagInterface $tags Array of tags to check */
    public function hasAny(string|TagInterface ...$tags): bool {
        foreach ($tags as $tag) {
            if ($this->has($tag)) {
                return true;
            }
        }
        return false;
    }

    public function isEmpty(): bool {
        return empty($this->tags);
    }

    public function isNotEmpty(): bool {
        return !empty($this->tags);
    }

    public function last(): ?TagInterface {
        return empty($this->tags) ? null : $this->tags[array_key_last($this->tags)];
    }

    /**
     * @param callable(TagInterface):mixed $transformer Function to apply to each tag
     * @return array<mixed>
     */
    public function mapTo(callable $transformer): array {
        return array_map($transformer, $this->tags);
    }

    public function reduce(callable $reducer, mixed $initial = null): mixed {
        return array_reduce($this->tags, $reducer, $initial);
    }
}