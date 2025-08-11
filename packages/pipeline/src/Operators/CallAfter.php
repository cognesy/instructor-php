<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\ProcessingState;

/**
 * Middleware that executes a callback after processing completes.
 */
readonly final class CallAfter implements CanControlStateProcessing
{
    public function __construct(
        private CanControlStateProcessing $operator,
    ) {}

    /**
     * @param callable(ProcessingState):mixed $callback
     */
    public static function with(callable $callback): self {
        return new self(Call::withState($callback));
    }

    /**
     * @param callable(ProcessingState):ProcessingState $next
     */
    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        $nextState = $next ? $next($state) : $state;
        return $this->operator->process($nextState);
    }
}