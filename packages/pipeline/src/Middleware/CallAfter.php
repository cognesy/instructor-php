<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\Contracts\PipelineMiddlewareInterface;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\StateFactory;

/**
 * Middleware that executes a callback after processing completes.
 */
readonly class CallAfter implements PipelineMiddlewareInterface
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
        return StateFactory::executeWithState($this->callback, $nextState, $this->onNull);
    }
}