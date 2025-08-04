<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Processor\Call;

/**
 * Middleware that executes a callback after processing completes.
 */
readonly class CallAfter implements CanControlStateProcessing
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
     * @param callable(ProcessingState):ProcessingState $next
     */
    public function handle(ProcessingState $state, callable $next): ProcessingState {
        $nextState = $next($state);
        return $this->processor->process($nextState);
    }
}