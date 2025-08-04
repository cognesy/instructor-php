<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\ErrorTag;

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
readonly class CallOnFailure implements CanControlStateProcessing
{
    /**
     * @param Closure(ProcessingState):void $callback
     */
    public function __construct(
        private Closure $callback,
    ) {}

    /**
     * @param callable(ProcessingState):void $callback
     */
    public static function with(callable $callback): self {
        return new self($callback);
    }

    /**
     * @param callable(ProcessingState):ProcessingState $next
     */
    public function handle(ProcessingState $state, callable $next): ProcessingState {
        $newState = $next($state);
        if (!$newState->isFailure()) {
            return $newState;
        }

        $tags = $newState->allTags();
        try {
            ($this->callback)($newState);
        } catch (\Throwable $e) {
            $tags[] = new ErrorTag(
                error: $e,
                context: 'Error while executing CallOnFailure callback',
                category: 'error',
                timestamp: microtime(true),
                metadata: [
                    'root_cause' => $newState->exception(),
                ]
            );
        }
        return $newState->withTags($tags);
    }
}