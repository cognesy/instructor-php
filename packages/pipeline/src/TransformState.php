<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Pipeline\Contracts\CanCarryState;
use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\Contracts\TagMapInterface;
use Cognesy\Pipeline\Tag\ErrorTag;
use Cognesy\Pipeline\Tag\TagQuery;
use Cognesy\Utils\Result\Result;
use Throwable;

class TransformState {
    public function __construct(private CanCarryState $state) {}

    public static function with(CanCarryState $state): self {
        return new self($state);
    }

    // TERMINAL METHODS

    public function state(): CanCarryState {
        return $this->state;
    }

    public function result(): Result {
        return $this->state->result();
    }

    public function value(): mixed {
        return $this->state->value();
    }

    public function valueOr(mixed $default): mixed {
        return $this->state->valueOr($default);
    }

    public function exception(): Throwable {
        return $this->state->exception();
    }

    public function exceptionOr(mixed $default): mixed {
        return $this->state->exceptionOr($default);
    }

    public function isSuccess(): bool {
        return $this->state->isSuccess();
    }

    public function isFailure(): bool {
        return $this->state->isFailure();
    }

    public function tagMap(): TagMapInterface {
        return $this->state->tagMap();
    }

    public function tags(): TagQuery {
        return $this->state->tags();
    }

    public function hasTag(string $tagClass): bool {
        return $this->state->hasTag($tagClass);
    }

    public function allTags(): array {
        return $this->state->tagMap()->getAllInOrder();
    }

    // ERROR HANDLING

    public function recover(mixed $defaultValue): CanCarryState {
        return match(true) {
            $this->isFailure() => $this->state->withResult(Result::success($defaultValue)),
            default => $this->state,
        };
    }

    /** @param callable(mixed):mixed $recovery */
    public function recoverWith(callable $recovery): CanCarryState {
        if ($this->isSuccess()) {
            return $this->state;
        }
        try {
            $recoveredValue = $recovery($this->state);
        } catch (Throwable $e) {
            return $this->state->addTags(new ErrorTag(error: $e->getMessage()));
        }
        return $this->state->withResult(Result::success($recoveredValue));
    }

    // CHAINABLE METHODS

    /**
     * @param callable(mixed):bool $conditionFn
     * @param callable(mixed):mixed $transformationFn
     */
    public function when(
        callable $conditionFn,
        callable $transformationFn,
    ): self {
        if ($this->state->isFailure()) {
            return $this;
        }
        $newState = $conditionFn($this->state->value())
            ? $this->state->withResult(Result::from($transformationFn($this->state->value())))
            : $this->state->withResult(Result::from($this->state->value()));
        return new self($newState);
    }

    /**
     * @param callable(CanCarryState):bool $stateConditionFn
     * @param callable(CanCarryState):CanCarryState $stateTransformationFn
     */
    public function whenState(
        callable $stateConditionFn,
        callable $stateTransformationFn,
    ): self {
        $newState = $stateConditionFn($this->state)
            ? $stateTransformationFn($this->state)
            : $this->state;
        return new self($newState);
    }

    /**
     * @param callable(mixed):bool $condition Condition to check before adding tags
     */
    public function addTagsIf(callable $condition, TagInterface ...$tags): self {
        $newState = $condition($this->state)
            ? $this->state->addTags(...$tags)
            : $this->state;
        return new self($newState);
    }

    public function addTagsIfSuccess(TagInterface ...$tags): self {
        return $this->addTagsIf(fn($state) => $state->result()->isSuccess(), ...$tags);
    }

    public function addTagsIfFailure(TagInterface ...$tags): self {
        return $this->addTagsIf(fn($state) => $state->result()->isFailure(), ...$tags);
    }

    public function mergeFrom(mixed $source): self {
        $newState = $this->state->replaceTags(...$this->state->tagMap()->merge($source->tagMap())->getAllInOrder());
        return new self($newState);
    }

    public function mergeInto(mixed $target): self {
        $newState = $this->state->replaceTags(
            ...$target->tagMap()->merge($this->state->tagMap())->getAllInOrder()
        );
        return new self($newState);
    }

    /**
     * Combines this state with another state, merging tags and optionally combining results.
     * @param callable(Result, Result): Result|null $resultCombinator Optional function to combine results
     */
    public function combine(CanCarryState $other, ?callable $resultCombinator = null): self {
        $resultCombinator ??= fn($a, $b) => $b; // Default: use second result
        $output = $resultCombinator($this->state->result(), $other->result());
        $newState = $this->state->withResult($output)->addTags(...$other->tagMap()->getAllInOrder());
        return new self($newState);
    }

    /** @param callable(mixed):bool $conditionFn */
    public function failWhen(callable $conditionFn, string $errorMessage = 'Failure condition met'): self {
        if ($this->state->isFailure()) {
            return $this;
        }
        $newState = $conditionFn($this->state->value())
            ? $this->state
            : $this->state->failWith($errorMessage);
        return new self($newState);
    }

    // TRANSFORMATIONS

    public function map(callable $fn): self {
        if ($this->state->isFailure()) {
            return $this;
        }
        $newState = $this->mapAnyInput(fn() => $this->state->result()->unwrap(), $fn);
        return new self($newState);
    }

    public function mapResult(callable $fn): self {
        $newState = $this->mapAnyInput(fn() => $this->state->result(), $fn);
        return new self($newState);
    }

    public function mapState(callable $fn): self {
        $newState = $this->mapAnyInput(fn() => $this->state, $fn);
        return new self($newState);
    }

    // INTERNAL ///////////////////////////////////////////////

    private function mapAnyInput(callable $inputFn, callable $fn): CanCarryState {
        $output = $fn($inputFn());
        return match(true) {
            $output instanceof CanCarryState => $output->transform()->mergeInto($this->state)->state(),
            default => $this->state->withResult(Result::from($output)),
        };
    }
}