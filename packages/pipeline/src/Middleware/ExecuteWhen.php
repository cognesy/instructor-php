<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\ProcessingState;

/**
 * Middleware that conditionally executes based on processing state.
 */
readonly class ExecuteWhen implements CanControlStateProcessing
{
    /**
     * @param Closure(ProcessingState):bool $condition
     */
    public function __construct(
        private Closure $condition,
        private CanControlStateProcessing $middleware,
    ) {}

    /**
     * @param callable(ProcessingState):bool $condition
     */
    public static function when(callable $condition, CanControlStateProcessing $middleware): self {
        return new self($condition, $middleware);
    }

    /**
     * @param callable(ProcessingState):ProcessingState $next
     */
    public function handle(ProcessingState $state, callable $next): ProcessingState {
        $shouldExecute = ($this->condition)($state);
        return match(true) {
            !$shouldExecute => $next($state),
            default => $this->middleware->handle($state, $next),
        };
    }
}