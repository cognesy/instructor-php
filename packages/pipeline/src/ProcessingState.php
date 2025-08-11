<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\Contracts\TagMapInterface;
use Cognesy\Pipeline\Internal\IndexedTagMap;
use Cognesy\Pipeline\Tag\ErrorTag;
use Cognesy\Pipeline\Tag\TagQuery;
use Cognesy\Utils\Result\Result;
use RuntimeException;
use Throwable;

/**
 * ProcessingState is an immutable object containing current processing state.
 *
 * It consists of:
 *  - output - value (payload) wrapped in Result for uniform handling of Success and Failure states
 *  - tags - metadata objects for cross-cutting concerns
 */
final readonly class ProcessingState
{
    private Result $result;
    private TagMapInterface $tags;

    public function __construct(
        Result $result,
        TagMapInterface $tags,
    ) {
        $this->result = $result;
        $this->tags = $tags;
    }

    // CONSTRUCTORS

    public static function empty(): self {
        return new self(
            result: Result::success(null),
            tags: self::defaultTagMap(),
        );
    }

    /**
     * @param mixed $value The value to wrap in a ProcessingState
     * @param array<TagInterface> $tags Optional tags to associate with this state
     */
    public static function with(mixed $value, array $tags = []): self {
        return new self(
            result: Result::from($value),
            tags: self::defaultTagMap($tags),
        );
    }

    public function withResult(Result $result): self {
        return new self($result, $this->tags);
    }

    public function withTags(TagInterface ...$tags): self {
        return new self($this->result, $this->tags->with(...$tags));
    }

    public function failWith(string|Throwable $cause): self {
        $message = $cause instanceof Throwable ? $cause->getMessage() : $cause;
        $exception = match (true) {
            is_string($cause) => new RuntimeException($cause),
            $cause instanceof Throwable => $cause,
        };
        return $this
            ->withResult(Result::failure($exception))
            ->withTags(new ErrorTag(error: $message));
    }

    // ACCESSORS - RESULT

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

    // TRANSFORMATIONS

    public function map(callable $fn): mixed {
        if ($this->isFailure()) {
            return $this;
        }
        return $this->mapAnyInput(fn() => $this->result->unwrap(), $fn);
    }

    public function mapResult(callable $fn): mixed {
        return $this->mapAnyInput(fn() => $this->result, $fn);
    }

    public function mapState(callable $fn): mixed {
        return $this->mapAnyInput(fn() => $this, $fn);
    }

    // ACCESSORS - TAGS

    public function tagMap(): TagMapInterface {
        return $this->tags;
    }

    /**
     * Get all tags, optionally filtered by class.
     *
     * @param class-string|null $tagClass Optional class filter
     * @return TagInterface[]
     */
    public function allTags(?string $tagClass = null): array {
        return $this->tags->query()->only($tagClass)->all();
    }

    /**
     * @param class-string $tagClass
     */
    public function hasTag(string $tagClass): bool {
        return $this->tags->has($tagClass);
    }

    // QUERY AND TRANSFORMATION APIs

    public function tags(): TagQuery {
        return $this->tagMap()->query();
    }

    // ERROR HANDLING

    public function recover(mixed $defaultValue): self {
        return match(true) {
            $this->isFailure() => $this->withResult(Result::success($defaultValue)),
            default => $this,
        };
    }

    /** @param callable(mixed):mixed $recovery */
    public function recoverWith(callable $recovery): self {
        if ($this->isSuccess()) {
            return $this;
        }
        try {
            $recoveredValue = $recovery($this);
        } catch (Throwable $e) {
            return $this->withTags(new ErrorTag(error: $e->getMessage()));
        }
        return $this->withResult(Result::success($recoveredValue));
    }

    // TAG TRANSFORMATIONS

    /**
     * @param callable(mixed):bool $condition Condition to check before adding tags
     */
    public function addTagsIf(callable $condition, TagInterface ...$tags): self {
        return $condition($this) ? $this->withTags(...$tags) : $this;
    }

    public function addTagsIfSuccess(TagInterface ...$tags): self {
        return $this->addTagsIf(fn($state) => $state->result()->isSuccess(), ...$tags);
    }

    public function addTagsIfFailure(TagInterface ...$tags): self {
        return $this->addTagsIf(fn($state) => $state->result()->isFailure(), ...$tags);
    }

    public function mergeFrom(mixed $source): self {
        return new self($this->result, $this->tagMap()->merge($source->tagMap()));
    }

    public function mergeInto(mixed $target): self {
        return new self($this->result, $target->tagMap()->merge($this->tagMap()));
    }

    /**
     * Combines this state with another state, merging tags and optionally combining results.
     *
     * @param mixed $other The other state to combine with
     * @param callable(Result, Result): Result|null $resultCombinator Optional function to combine results
     * @return self New ProcessingState with combined result and merged tags
     */
    public function combine(mixed $other, ?callable $resultCombinator = null): self {
        $resultCombinator ??= fn($a, $b) => $b; // Default: use second result
        return new self(
            result: $resultCombinator($this->result, $other->result()),
            tags: $this->tagMap()->merge($other->tagMap()),
        );
    }

    // CONDITIONAL OPERATIONS

    /** @param callable(mixed):bool $conditionFn */
    public function failWhen(callable $conditionFn, string $errorMessage = 'Failure condition met'): self {
        if ($this->isFailure()) {
            return $this;
        }
        return $conditionFn($this->value()) ? $this : $this->failWith($errorMessage);
    }

    /**
     * @param callable(mixed):bool $conditionFn
     * @param callable(mixed):mixed $transformationFn
     */
    public function when(callable $conditionFn, callable $transformationFn): self {
        if ($this->isFailure()) {
            return $this;
        }
        return $conditionFn($this->value())
            ? $this->withResult(Result::from($transformationFn($this->value())))
            : $this->withResult(Result::from($this->value()));
    }

    /**
     * @param callable(ProcessingState):bool $stateConditionFn
     * @param callable(ProcessingState):ProcessingState $stateTransformationFn
     */
    public function whenState(callable $stateConditionFn, callable $stateTransformationFn): self {
        return $stateConditionFn($this) ? $stateTransformationFn($this) : $this;
    }

    // PRIVATE

    private static function defaultTagMap(?array $tags = []): TagMapInterface {
        return match(true) {
            $tags === null => IndexedTagMap::empty(),
            default => IndexedTagMap::create($tags),
        };
    }

    private function mapAnyInput(callable $inputFn, callable $fn): mixed {
        $output = $fn($inputFn());
        return match(true) {
            $output instanceof ProcessingState => $output->mergeInto($this),
            default => $this->withResult(Result::from($output)),
        };
    }
}