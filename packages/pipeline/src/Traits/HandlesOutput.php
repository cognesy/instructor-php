<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Traits;

use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\ErrorTag;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use RuntimeException;
use Throwable;

trait HandlesOutput
{
    protected function asResult(
        mixed $value,
        NullStrategy $onNull = NullStrategy::Allow,
    ): Result {
        return match (true) {
            $value instanceof Result => $value,
            $value === null && NullStrategy::Fail->is($onNull) => Result::failure(new RuntimeException('Value cannot be null')),
            $value === null && NullStrategy::Allow->is($onNull) => Result::success(null),
            default => Result::success($value),
        };
    }

    protected function asProcessingState(
        mixed $input,
        ?ProcessingState $prior = null,
        NullStrategy $onNull = NullStrategy::Allow,
    ): ProcessingState {
        return match (true) {
            ($input === null) => $this->handleNullOutput($prior ?? ProcessingState::empty(), $onNull),
            $input instanceof ProcessingState => $prior ? $input->combine($prior) : $input,
            $input instanceof Result => $prior ? $prior->withResult($input) : ProcessingState::with($input),
            default => $prior ? $prior->withResult(Result::success($input)) : ProcessingState::with($input),
        };
    }

    protected function createFailureState(ProcessingState $state, mixed $error): ProcessingState {
        $failure = $this->asFailure($error);
        $errorTag = $this->asErrorTag($error);
        return $state->withResult($failure)->withTags($errorTag);
    }

    protected function handleNullOutput(
        ProcessingState $state,
        NullStrategy $onNull,
    ): ProcessingState {
        return match ($onNull) {
            NullStrategy::Skip => $state,
            NullStrategy::Fail => $this->createFailureState($state, new RuntimeException('Processor returned null value')),
            NullStrategy::Allow => $state->withResult(Result::success(null)),
        };
    }

    private function asFailure(mixed $error): Failure {
        return match (true) {
            $error instanceof Result => $error,
            $error instanceof Throwable => Result::failure($error),
            is_string($error) => Result::failure(new RuntimeException($error)),
            default => Result::failure(new RuntimeException(json_encode(['error' => $error]))),
        };
    }

    private function asErrorTag(mixed $error): ErrorTag {
        return match (true) {
            $error instanceof Throwable => ErrorTag::fromException($error),
            $error instanceof Result => ErrorTag::fromResult($error),
            default => ErrorTag::fromMessage((string)$error),
        };
    }
}