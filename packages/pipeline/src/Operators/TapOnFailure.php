<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\ErrorTag;
use Throwable;

/**
 * Middleware that executes a callback when processing results in failure.
 *
 * This provides a convenient way to add "onFailure" hooks while using the
 * middleware pattern. The callback receives the failed state and can
 * perform side effects like logging, alerting, or metrics collection.
 *
 * Note: This middleware does NOT modify the failure - it's purely for
 * side effects. The failure continues to propagate normally.
 */
readonly final class TapOnFailure implements CanProcessState
{
    public function __construct(
        private CanProcessState $operator,
    ) {}

    /**
     * @param callable(ProcessingState):void $callback
     */
    public static function with(callable $callback): self {
        return new self(Call::withState($callback));
    }

    /**
     * @param callable(ProcessingState):ProcessingState $next
     */
    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        $newState = $next ? $next($state) : $state;
        if (!$newState->isFailure()) {
            return $newState;
        }

        try {
            $this->operator->process($newState, fn($s) => $s);
        } catch (Throwable $e) {
            $newState = $newState->withTags(new ErrorTag($e));
        }
        return $newState;
    }
}