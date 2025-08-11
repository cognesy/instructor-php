<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\ProcessingState;

/**
 * Middleware that executes a callback before processing continues.
 */
readonly final class CallBefore implements CanControlStateProcessing
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
     * Handles the processing state by executing the callback before continuing.
     *
     * @param callable(ProcessingState):ProcessingState $next
     */
    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        $modifiedState = $this->operator->process($state);
        return $next ? $next($modifiedState) : $modifiedState;
    }
}