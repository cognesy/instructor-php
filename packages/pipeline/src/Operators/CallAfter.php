<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanCarryState;
use Cognesy\Pipeline\Contracts\CanProcessState;

/**
 * Middleware that executes a callback after processing completes.
 */
readonly final class CallAfter implements CanProcessState
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
     * @param callable(CanCarryState):CanCarryState $next
     */
    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        $nextState = $next ? $next($state) : $state;
        return $this->operator->process($nextState);
    }
}