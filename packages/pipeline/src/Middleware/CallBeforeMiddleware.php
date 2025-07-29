<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\PipelineMiddlewareInterface;
use Exception;

/**
 * Middleware that executes a callback before processing continues.
 *
 * This provides a convenient way to add "before" hooks while using the
 * middleware pattern. The callback receives the current computation and can:
 * - Perform side effects (logging, metrics, etc.)
 * - Return a modified computation
 * - Return null to leave computation unchanged
 *
 * Example:
 * ```php
 * // Add timestamp before each processor
 * $middleware = new CallBeforeMiddleware(function(Computation $computation) {
 *     echo "Processing: " . $computation->result()->unwrap() . "\n";
 *     return $computation->with(new TimestampTag());
 * });
 *
 * // Just side effects, no modification
 * $middleware = new CallBeforeMiddleware(function(Computation $computation) {
 *     logger()->info('Processing started', ['value' => $computation->result()->unwrap()]);
 * });
 * ```
 */
readonly class CallBeforeMiddleware implements PipelineMiddlewareInterface
{
    public function __construct(
        private Closure $callback,
    ) {}

    /**
     * Execute the callback before continuing with next middleware.
     */
    public function handle(Computation $computation, callable $next): Computation {
        try {
            // Execute the before callback
            $result = ($this->callback)($computation);

            // If callback returns an computation, use it; otherwise use original
            $modifiedComputation = $result instanceof Computation ? $result : $computation;

            // Continue with next middleware
            return $next($modifiedComputation);
        } catch (Exception $e) {
            // If callback fails, create failure computation but still try to continue
            // This matches the behavior of the original hook system
            $failureComputation = $computation->withResult(\Cognesy\Utils\Result\Result::failure($e));
            return $next($failureComputation);
        }
    }

    /**
     * Static factory for cleaner API.
     */
    public static function call(callable $callback): self {
        return new self($callback);
    }
}