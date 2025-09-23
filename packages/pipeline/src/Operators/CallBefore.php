<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\StateContracts\CanCarryState;

/**
 * Middleware that executes a callback before processing continues.
 */
readonly final class CallBefore implements CanProcessState
{
    public function __construct(
        private CanProcessState $operator,
    ) {}

    /**
     * @param callable(CanCarryState):mixed $callback
     */
    public static function with(callable $callback): self {
        return new self(Call::withState($callback));
    }

    /**
     * Handles the processing state by executing the callback before continuing.
     *
     * @param callable(CanCarryState):CanCarryState $next
     */
    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        $modifiedState = $this->operator->process($state);
        return $next ? $next($modifiedState) : $modifiedState;
    }
}