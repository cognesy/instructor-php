<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Processor\Call;

/**
 * Middleware that executes a callback before processing continues.
 */
readonly class CallBefore implements CanControlStateProcessing
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
     * Handles the processing state by executing the callback before continuing.
     *
     * @param callable(ProcessingState):ProcessingState $next
     */
    public function handle(ProcessingState $state, callable $next): ProcessingState {
        $modifiedState = Call::withState($this->callback)->onNull($this->onNull)->process($state);
        return $next($modifiedState);
    }
}