<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\PipelineMiddlewareInterface;
use Exception;

/**
 * Middleware that executes a callback when processing results in failure.
 *
 * This provides a convenient way to add "onFailure" hooks while using the
 * middleware pattern. The callback receives the failed envelope and can
 * perform side effects like logging, alerting, or metrics collection.
 *
 * Note: This middleware does NOT modify the failure - it's purely for
 * side effects. The failure continues to propagate normally.
 *
 * Example:
 * ```php
 * // Log failures with context
 * $middleware = new CallOnFailureMiddleware(function(Envelope $env) {
 *     $error = $env->getResult()->error();
 *     $trace = $env->last(TraceStamp::class);
 *
 *     logger()->error('Processing failed', [
 *         'error' => $error,
 *         'trace_id' => $trace?->traceId,
 *         'payload' => json_encode($env->getResult()->unwrap())
 *     ]);
 * });
 *
 * // Send alerts on critical failures
 * $middleware = new CallOnFailureMiddleware(function(Envelope $env) {
 *     $error = $env->getResult()->error();
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
    public function handle(Envelope $envelope, callable $next): Envelope {
        // Process through next middleware
        $result = $next($envelope);

        // If result is a failure, execute the callback
        if ($result->getResult()->isFailure()) {
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