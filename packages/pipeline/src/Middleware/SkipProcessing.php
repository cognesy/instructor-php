<?php

namespace Cognesy\Pipeline\Middleware;

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Processor\Call;
use Cognesy\Pipeline\Processor\ConditionalCall;
use Cognesy\Pipeline\Tag\ErrorTag;
use RuntimeException;

/**
 * Middleware that skips processing based on a condition.
 *
 * This middleware allows you to skip further processing in the pipeline
 * if a certain condition is met, returning the current state without modification.
 */
readonly class SkipProcessing implements CanControlStateProcessing {
    private function __construct(
        private CanProcessState $conditionChecker,
    ) {}

    /**
     * @param callable(ProcessingState):bool $condition
     */
    public static function when(callable $condition): self {
        return new self(ConditionalCall::withState($condition)->then(Call::withNoArgs(fn() => true)));
    }

    public function handle(ProcessingState $state, callable $next): ProcessingState {
        $tempState = $this->conditionChecker->process($state);
        return match(true) {
            $tempState->isFailure() => $tempState
                ->mergeInto($state)
                ->withTags(new ErrorTag(new RuntimeException('Failure while evaluating skip condition'))),
            $tempState->value() => $state,
            default => $next($state),
        };
    }
}