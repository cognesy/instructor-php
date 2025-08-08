<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Query;

use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Utils\Result\Result;
use Throwable;

/**
 * Fluent interface for state transformations.
 */
final class TransformQuery
{
    public function __construct(
        private readonly ProcessingState $state
    ) {}

    // TERMINAL OPERATIONS

    public function get(): ProcessingState {
        return $this->state;
    }

    public function getResult(): Result {
        return $this->state->getResult();
    }

    // TRANSFORMATIONS

    /**
     * @param callable(mixed):mixed $mapper
     */
    public function map(callable $mapper): self {
        if ($this->state->isFailure()) {
            return $this;
        }
        try {
            $output = $mapper($this->state->value());
        } catch (Throwable $e) {
            return new self($this->state->failWith($e));
        }
        return new self($this->state->withResult(Result::success($output)));
    }

    /**
     * Apply function that returns ProcessingState, merge tags
     */
    public function flatMap(callable $fn): self {
        if ($this->state->isFailure()) {
            return $this;
        }
        $output = $fn($this->state->value());
        return new self(match(true) {
            $output instanceof ProcessingState => $output->transform()->mergeInto($this->state),
            default => $this->state->withResult(Result::from($output)),
        });
    }

    /**
     * Apply function that returns ProcessingState, merge tags
     */
    public function flatMapResult(callable $fn): self {
        if ($this->state->isFailure()) {
            return $this;
        }
        $output = $fn($this->state->result());
        return new self(match(true) {
            $output instanceof ProcessingState => $output->transform()->mergeInto($this->state),
            default => $this->state->withResult(Result::from($output)),
        });
    }

    /**
     * Transform entire state if successful.
     */
    public function flatMapState(callable $transformer): self {
        if ($this->state->isFailure()) {
            return $this;
        }
        try {
            $output = $transformer($this->state);
        } catch (Throwable $e) {
            return new self($this->state->failWith($e));
        }
        return new self(match(true) {
            $output instanceof ProcessingState => $output->transform()->mergeInto($this->state),
            default => $this->state->withResult(Result::from($output)),
        });
    }

    /**
     * Merges result and tags using a custom combinator function.
     *
     * @param callable(Result, Result): Result $resultCombinator Optional function to combine results
     */
    public function combine(ProcessingState $other, ?callable $resultCombinator = null): ProcessingState {
        $resultCombinator ??= fn($a, $b) => $b;
        return new ProcessingState(
            result: $resultCombinator($this->state->getResult(), $other->getResult()),
            tags: $this->state->getTagMap()->merge($other->getTagMap()),
        );
    }

    // ERROR HANDLING

    public function recover(mixed $defaultValue): self {
        return new self(match(true) {
            $this->state->getResult()->isFailure() => $this->state->withResult(Result::success($defaultValue)),
            default => $this->state,
        });
    }

    public function recoverWith(callable $recovery): self {
        if ($this->state->getResult()->isSuccess()) {
            return new self($this->state);
        }
        try {
            $recoveredValue = $recovery(
                $this->state->getResult()->errorMessage(),
                $this->state->getResult()->exception()
            );
            return new self($this->state->withResult(Result::success($recoveredValue)));
        } catch (Throwable $e) {
            return new self($this->state); // Keep original failure
        }
    }

    // TAG TRANSFORMATIONS

    public function addTagsIf(bool $condition, TagInterface ...$tags): ProcessingState {
        return $condition ? $this->state->withTags(...$tags) : $this->state;
    }

    public function addTagsIfSuccess(TagInterface ...$tags): ProcessingState {
        return $this->addTagsIf($this->state->getResult()->isSuccess(), ...$tags);
    }

    public function addTagsIfFailure(TagInterface ...$tags): ProcessingState {
        return $this->addTagsIf($this->state->getResult()->isFailure(), ...$tags);
    }

    public function mergeFrom(ProcessingState $source): ProcessingState {
        return new ProcessingState(
            result: $this->state->getResult(),
            tags: $this->state->getTagMap()->merge($source->getTagMap()),
        );
    }

    public function mergeInto(ProcessingState $target): ProcessingState {
        return new ProcessingState(
            result: $this->state->getResult(),
            tags: $target->getTagMap()->merge($this->state->getTagMap()),
        );
    }

    public function mapTags(string $tagClass, callable $transformer): ProcessingState {
        $allTags = $this->state->getTagMap()->getAllInOrder();
        $transformedTags = [];
        foreach ($allTags as $tag) {
            if ($tag instanceof $tagClass) {
                $transformedTags[] = $transformer($tag);
            } else {
                $transformedTags[] = $tag;
            }
        }
        return $this->state->withTags(...$transformedTags);
    }

    /**
     * Apply predicate, short-circuit on false
     */
    public function filter(callable $predicate, string $errorMessage = 'Filter failed'): self {
        if ($this->state->getResult()->isFailure()) {
            return $this;
        }
        return $predicate($this->state->value())
            ? $this
            : new self($this->state->failWith($errorMessage));
    }

    // CONDITIONAL OPERATIONS

    public function when(bool $condition, callable $transformation): ProcessingState {
        return $condition ? $transformation($this->state) : $this->state;
    }

    public function whenValue(callable $predicate, callable $transformation): ProcessingState {
        return ($this->state->isSuccess() && $predicate($this->state->value()))
            ? $transformation($this->state)
            : $this->state;
    }

    // TERMINAL OPERATIONS

}