<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Processor\Call;

/**
 * Middleware that executes a callback after processing completes.
 */
readonly class CallAfter implements CanControlStateProcessing
{
    /**
     * @param Closure(ProcessingState):mixed $callback
     */
    public function __construct(
        private Closure $callback,
        private NullStrategy $onNull = NullStrategy::Allow,
    ) {}

    /**
     * @param callable(ProcessingState):mixed $callback
     */
    public static function with(callable $callback): self {
        return new self($callback);
    }

    /**
     * @param callable(ProcessingState):ProcessingState $next
     */
    public function handle(ProcessingState $state, callable $next): ProcessingState {
        $nextState = $next($state);
        return Call::withState($this->callback)->onNull($this->onNull)->process($nextState);
    }
}