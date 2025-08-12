<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanCarryState;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\Tag\ErrorTag;
use RuntimeException;

/**
 * Middleware that skips processing based on a condition.
 *
 * This middleware allows you to skip further processing in the pipeline
 * if a certain condition is met, returning the current state without modification.
 */
readonly final class Skip implements CanProcessState {
    private function __construct(
        private CanProcessState $conditionChecker,
    ) {}

    /**
     * @param callable(CanCarryState):bool $condition
     */
    public static function when(callable $condition): self {
        return new self(ConditionalCall::withState($condition)->then(Call::withNoArgs(fn() => true)));
    }

    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        $tempState = $this->conditionChecker->process($state, fn($s) => $s);
        return match(true) {
            $tempState->isFailure() => $tempState
                ->transform()->mergeInto($state)
                ->withTags(new ErrorTag(new RuntimeException('Failure while evaluating skip condition'))),
            $tempState->value() => $state,
            default => $next ? $next($state) : $state,
        };
    }
}