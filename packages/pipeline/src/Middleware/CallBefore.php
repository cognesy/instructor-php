<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Processor\Call;

/**
 * Middleware that executes a callback before processing continues.
 */
readonly class CallBefore implements CanControlStateProcessing
{
    public function __construct(
        private CanProcessState $processor,
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
    public function handle(ProcessingState $state, callable $next): ProcessingState {
        $modifiedState = $this->processor->process($state);
        return $next($modifiedState);
    }
}