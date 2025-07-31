<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Pipeline\Tag\TagInterface;
use Cognesy\Pipeline\Tag\TagMap;
use Cognesy\Utils\Result\Result;

/**
 * Computation contains result with tags (metadata) for cross-cutting or meta concerns.
 *
 * The Computation maintains separation of concerns:
 * - Result: Handles success/failure of the execution and type-safe value access
 * - Computation: Manages metadata, observability, and cross-cutting concerns
 *
 * Key features:
 * - Immutable - every modification creates a new instance
 * - Tags survive success/failure transitions
 * - Type-safe tag retrieval
 * - Clean separation of data and metadata
 *
 * Example:
 * ```php
 * $computation = new Computation(Result::success($data), TagMap::create([
 *     new TraceTag('trace-123'),
 *     new TimestampTag(microtime(true))
 * ]));
 *
 * $newComputation = $computation
 *     ->with(new MetricsTag($duration))
 *     ->without(TimestampTag::class);
 * ```
 */
final readonly class Computation
{
    private Result $result;
    private TagMap $tags;

    /**
     * @param Result $result The computation result (success or failure)
     * @param TagMap|null $tags Collection of tags for metadata management
     */
    public function __construct(
        Result $result,
        ?TagMap $tags = null,
    ) {
        $this->result = $result;
        $this->tags = $tags ?? TagMap::empty();
    }

    /**
     * Wrap a value in a Computation, adding optional tags.
     *
     * If the value is already a Result, it's used directly.
     * Otherwise, it's wrapped in Result::success().
     */
    public static function for(mixed $value, array $tags = []): self {
        return new self(
            result: Result::from($value),
            tags: TagMap::create($tags)
        );
    }

    /**
     * Get the Result containing the computation.
     */
    public function result(): Result {
        return $this->result;
    }

    public function value(): mixed {
        return $this->result->unwrap();
    }

    public function valueOr(mixed $default): mixed {
        return $this->result->valueOr($default);
    }

    public function isSuccess(): bool {
        return $this->result->isSuccess();
    }

    public function isFailure(): bool {
        return $this->result->isFailure();
    }

    /**
     * Create a new Computation with a different Result.
     *
     * This preserves all tags while changing the computation output.
     */
    public function withResult(Result $result): self {
        return new self($result, $this->tags);
    }

    /**
     * Create a new Computation with additional tags.
     *
     * @param TagInterface ...$tags
     */
    public function with(TagInterface ...$tags): self {
        return new self($this->result, $this->tags->with(...$tags));
    }

    /**
     * Create a new Computation without tags of the specified type(s).
     *
     * @param string ...$tagClasses
     */
    public function without(string ...$tagClasses): self {
        return new self($this->result, $this->tags->without(...$tagClasses));
    }

    /**
     * Get all tags, optionally filtered by class.
     *
     * @param string|null $tagClass Optional class filter
     * @return TagInterface[]
     */
    public function all(?string $tagClass = null): array {
        return $this->tags->all($tagClass);
    }

    /**
     * Get the last (most recent) tag of a specific type.
     *
     * @template T of TagInterface
     * @param class-string<T> $tagClass
     * @return TagInterface|null
     */
    public function last(string $tagClass): ?TagInterface {
        return $this->tags->last($tagClass);
    }

    /**
     * Get the first tag of a specific type.
     *
     * @template T of TagInterface
     * @param class-string<T> $tagClass
     * @return TagInterface|null
     */
    public function first(string $tagClass): ?TagInterface {
        return $this->tags->first($tagClass);
    }

    /**
     * Check if the computation has tags of a specific type.
     */
    public function has(string $tagClass): bool {
        return $this->tags->has($tagClass);
    }

    /**
     * Get count of tags of a specific type.
     */
    public function count(?string $tagClass = null): int {
        return $this->tags->count($tagClass);
    }

}