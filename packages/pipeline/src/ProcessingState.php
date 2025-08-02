<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Pipeline\Tag\TagInterface;
use Cognesy\Pipeline\Tag\TagMap;
use Cognesy\Utils\Result\Result;

/**
 * ProcessingState contains result with tags (metadata) for cross-cutting or meta concerns.
 *
 * The ProcessingState maintains separation of concerns:
 * - Result: Handles success/failure of the execution and type-safe value access
 * - ProcessingState: Manages metadata, observability, and cross-cutting concerns
 *
 * Key features:
 * - Immutable - every modification creates a new instance
 * - Tags survive success/failure transitions
 * - Type-safe tag retrieval
 * - Clean separation of data and metadata
 *
 * Example:
 * ```php
 * $state = new ProcessingState(Result::success($data), TagMap::create([
 *     new TraceTag('trace-123'),
 *     new TimestampTag(microtime(true))
 * ]));
 *
 * $newProcessingState = $state
 *     ->with(new MetricsTag($duration))
 *     ->without(TimestampTag::class);
 * ```
 */
final readonly class ProcessingState
{
    private Result $result;
    private TagMap $tags;

    public function __construct(
        Result $result,
        ?TagMap $tags = null,
    ) {
        $this->result = $result;
        $this->tags = $tags ?? TagMap::empty();
    }

    public static function empty(): self {
        return new self(
            result: Result::success(null),
            tags: TagMap::empty()
        );
    }

    /**
     * @param mixed $value The value to wrap in a ProcessingState
     * @param array<TagInterface> $tags Optional tags to associate with this state
     */
    public static function with(mixed $value, array $tags = []): self {
        return new self(
            result: Result::from($value),
            tags: TagMap::create($tags)
        );
    }

    public function combine(ProcessingState $other): self {
        return new self(
            result: $this->result,
            tags: $this->tags->merge($other->tags)
        );
    }

    public function withResult(Result $result): self {
        return new self($result, $this->tags);
    }

    public function withTags(TagInterface ...$tags): self {
        return new self($this->result, $this->tags->with(...$tags));
    }

    public function withoutTags(string ...$tagClasses): self {
        return new self($this->result, $this->tags->without(...$tagClasses));
    }

    /**
     * Get all tags, optionally filtered by class.
     *
     * @param string|null $tagClass Optional class filter
     * @return TagInterface[]
     */
    public function allTags(?string $tagClass = null): array {
        return $this->tags->all($tagClass);
    }

    public function lastTag(string $tagClass): ?TagInterface {
        return $this->tags->last($tagClass);
    }

    public function firstTag(string $tagClass): ?TagInterface {
        return $this->tags->first($tagClass);
    }

    public function hasTag(string $tagClass): bool {
        return $this->tags->has($tagClass);
    }

    public function countTag(?string $tagClass = null): int {
        return $this->tags->count($tagClass);
    }

    public function result(): Result {
        return $this->result;
    }

    public function value(): mixed {
        if ($this->result->isFailure()) {
            throw new \RuntimeException('Cannot unwrap value from a failed result');
        }
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

    public function exception(): mixed {
        if (!$this->result->isFailure()) {
            throw new \RuntimeException('Cannot get exception from a successful result');
        }
        return $this->result->exception();
    }

    public function exceptionOr(mixed $default): mixed {
        return $this->result->exceptionOr($default);
    }
}