<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\Tag\ErrorTag;
use Cognesy\Pipeline\Tag\TagMap;
use Cognesy\Utils\Result\Result;
use Throwable;

/**
 * ProcessingState contains result with tags (metadata) for cross-cutting or meta concerns.
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
            tags: TagMap::empty(),
        );
    }

    /**
     * @param mixed $value The value to wrap in a ProcessingState
     * @param array<TagInterface> $tags Optional tags to associate with this state
     */
    public static function with(mixed $value, array $tags = []): self {
        return new self(
            result: Result::from($value),
            tags: TagMap::create($tags),
        );
    }

    public function withResult(Result $result): self {
        return new self($result, $this->tags);
    }

    public function withTags(TagInterface ...$tags): self {
        return new self($this->result, $this->tags->with(...$tags));
    }

    public function failWith(Throwable $exception): self {
        return new self(
            result: Result::failure($exception),
            tags: $this->tags->with(new ErrorTag(error: $exception)),
        );
    }

    /**
     * @param array<class-string> $tagClasses
     */
    public function withoutTags(string ...$tagClasses): self {
        return new self($this->result, $this->tags->without(...$tagClasses));
    }

    public function mergeFrom(ProcessingState $source): self {
        return new self(
            result: $this->result,
            tags: $this->tags->merge($source->tags),
        );
    }

    public function mergeInto(ProcessingState $target): self {
        return new self(
            result: $this->result,
            tags: $target->tags->merge($this->tags),
        );
    }

    /**
     * Merges result and tags using a custom combinator function.
     *
     * @param callable(Result, Result): Result $resultCombinator Optional function to combine results
     */
    public function combine(ProcessingState $other, ?callable $resultCombinator = null): self {
        $resultCombinator ??= fn($a, $b) => $b;
        return new self(
            result: $resultCombinator($this->result, $other->result),
            tags: $this->tags->merge($other->tags),
        );
    }

    /**
     * Get all tags, optionally filtered by class.
     *
     * @param class-string|null $tagClass Optional class filter
     * @return TagInterface[]
     */
    public function allTags(?string $tagClass = null): array {
        return $this->tags->all($tagClass);
    }

    /**
     * @param class-string $tagClass
     */
    public function lastTag(string $tagClass): ?TagInterface {
        return $this->tags->last($tagClass);
    }

    /**
     * @param class-string $tagClass
     */
    public function firstTag(string $tagClass): ?TagInterface {
        return $this->tags->first($tagClass);
    }

    /**
     * @param class-string $tagClass
     */
    public function hasTag(string $tagClass): bool {
        return $this->tags->has($tagClass);
    }

    public function hasAllOfTags(array $tags): bool {
        foreach ($tags as $tag) {
            if (!$this->tags->has($tag::class)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<TagInterface> $tags Array of tag class names to check
     */
    public function hasAnyOfTags(array $tags): bool {
        foreach ($tags as $tag) {
            if ($this->tags->has($tag::class)) {
                return true;
            }
        }
        return false;
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

    /**
     * Apply function to value if success, preserve tags
     */
    public function map(callable $fn): self {
        if ($this->result->isFailure()) {
            return $this;
        }
        
        try {
            $newValue = $fn($this->result->unwrap());
            return new self(Result::from($newValue), $this->tags);
        } catch (\Throwable $e) {
            return new self(Result::failure($e), $this->tags);
        }
    }

    /**
     * Apply function that returns ProcessingState, merge tags
     */
    public function flatMap(callable $fn): self {
        if ($this->result->isFailure()) {
            return $this;
        }

        $newState = $fn($this->result->unwrap());
        return new self(
            $newState->result,
            $this->tags->merge($newState->tags),
        );
    }

    /**
     * Apply function to value if success, returning Result
     */
    public function mapResult(callable $fn): self {
        return new self(
            $this->result->map($fn),
            $this->tags,
        );
    }

    /**
     * Apply predicate, short-circuit on false
     */
    public function filter(callable $predicate, string $errorMessage = 'Filter failed'): self {
        if ($this->result->isFailure()) {
            return $this;
        }

        return $predicate($this->result->unwrap())
            ? $this
            : new self(Result::failure(new \RuntimeException($errorMessage)), $this->tags);
    }
}