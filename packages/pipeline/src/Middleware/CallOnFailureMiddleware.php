<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\PipelineMiddlewareInterface;
use Exception;

/**
 * Middleware that executes a callback when processing results in failure.
 *
 * This provides a convenient way to add "onFailure" hooks while using the
 * middleware pattern. The callback receives the failed computation and can
 * perform side effects like logging, alerting, or metrics collection.
 *
 * Note: This middleware does NOT modify the failure - it's purely for
 * side effects. The failure continues to propagate normally.
 *
 * Example:
 * ```php
 * // Log failures with context
 * $middleware = new CallOnFailureMiddleware(function(Computation $computation) {
 *     $error = $computation->result()->error();
 *     $trace = $computation->last(TraceTag::class);
 *
 *     logger()->error('Processing failed', [
 *         'error' => $error,
 *         'trace_id' => $trace?->traceId,
 *         'value' => json_encode($computation->result()->unwrap())
 *     ]);
 * });
 *
 * // Send alerts on critical failures
 * $middleware = new CallOnFailureMiddleware(function(Computation $computation) {
 *     $error = $computation->result()->error();
 *     if ($error instanceof CriticalException) {
 *         alerting()->sendAlert('Critical processing failure', $error);
 *     }
 * });
 * ```
 */
readonly class CallOnFailureMiddleware implements PipelineMiddlewareInterface
{
    public function __construct(
        private Closure $callback,
    ) {}

    /**
     * Process through next middleware and call callback if result is failure.
     */
    public function handle(Computation $computation, callable $next): Computation {
        // Process through next middleware
        $result = $next($computation);

        // If result is a failure, execute the callback
        if ($result->result()->isFailure()) {
            try {
                ($this->callback)($result);
            } catch (Exception) {
                // Ignore callback errors - onFailure is for side effects only
                // We don't want to mask the original failure
            }
        }

        // Always return the result unchanged - this is for side effects only
        return $result;
    }

    /**
     * Static factory for cleaner API.
     */
    public static function call(callable $callback): self {
        return new self($callback);
    }
}