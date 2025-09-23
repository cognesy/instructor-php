<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Closure;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\StateContracts\CanCarryState;

/**
 * Middleware that fails the pipeline when a condition is met.
 *
 * This is useful for validation and early failure scenarios where
 * you want to stop processing based on the current state.
 */
readonly final class FailWhen implements CanProcessState
{
    public function __construct(
        private Closure $condition,
        private string $message,
    ) {}

    /**
     * @param callable(CanCarryState):bool $condition
     */
    public static function with(callable $condition, string $message = 'Condition failed'): self {
        return new self($condition, $message);
    }

    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        if (($this->condition)($state)) {
            return $state->failWith($this->message);
        }
        return $next ? $next($state) : $state;
    }
}