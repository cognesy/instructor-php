<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Query;

use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\ErrorTag;
use Cognesy\Utils\Result\Result;
use Throwable;

/**
 * Fluent interface for state transformations.
 */
final class TransformQuery
{
    public function __construct(private readonly ProcessingState $state) {}

    /**
     * Transform value if successful, otherwise return unchanged.
     */
    public function mapValue(callable $transformer): ProcessingState {
        if ($this->state->result()->isFailure()) {
            return $this->state;
        }
        try {
            $newValue = $transformer($this->state->result()->unwrap());
            return $this->state->withResult(Result::success($newValue));
        } catch (Throwable $e) {
            return $this->state->withResult(Result::failure($e->getMessage()));
        }
    }

    /**
     * Transform entire state if successful.
     */
    public function flatMap(callable $transformer): ProcessingState {
        if ($this->state->result()->isFailure()) {
            return $this->state;
        }
        try {
            $result = $transformer($this->state);
            return $result instanceof ProcessingState
                ? $result
                : $this->state->withResult(Result::from($result));
        } catch (Throwable $e) {
            return $this->state->withResult(Result::failure($e->getMessage()));
        }
    }

    // ERROR HANDLING

    public function failWith(string|Throwable $error): ProcessingState {
        $errorMessage = $error instanceof Throwable ? $error->getMessage() : $error;
        $newResult = Result::failure($errorMessage);
        if ($error instanceof Throwable) {
            return $this->state
                ->withResult($newResult)
                ->withTags(new ErrorTag($error));
        }
        return $this->state->withResult($newResult);
    }

    public function recover(mixed $defaultValue): ProcessingState {
        return $this->state->result()->isFailure()
            ? $this->state->withResult(Result::success($defaultValue))
            : $this->state;
    }

    public function recoverWith(callable $recovery): ProcessingState {
        if ($this->state->result()->isSuccess()) {
            return $this->state;
        }
        try {
            $recoveredValue = $recovery(
                $this->state->result()->errorMessage(),
                $this->state->result()->exception()
            );
            return $this->state->withResult(Result::success($recoveredValue));
        } catch (Throwable $e) {
            return $this->state; // Keep original failure
        }
    }

    // TAG TRANSFORMATIONS

    public function addTagsIf(bool $condition, TagInterface ...$tags): ProcessingState {
        return $condition ? $this->state->withTags(...$tags) : $this->state;
    }

    public function addTagsIfSuccess(TagInterface ...$tags): ProcessingState {
        return $this->addTagsIf($this->state->result()->isSuccess(), ...$tags);
    }

    public function addTagsIfFailure(TagInterface ...$tags): ProcessingState {
        return $this->addTagsIf($this->state->result()->isFailure(), ...$tags);
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
        return $this->state
            ->withResult($this->state->result())
            ->withTags(...$transformedTags);
    }

    // CONDITIONAL OPERATIONS

    public function when(bool $condition, callable $transformation): ProcessingState {
        return $condition ? $transformation($this->state) : $this->state;
    }

    public function whenValue(callable $predicate, callable $transformation): ProcessingState {
        $shouldTransform = $this->state->result()->isSuccess()
            && $predicate($this->state->result()->unwrap());
        return $shouldTransform ? $transformation($this->state) : $this->state;
    }
}