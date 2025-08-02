<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\ProcessingState;
use Exception;

/**
 * Middleware that executes a callback before processing continues.
 *
 * This provides a convenient way to add "before" hooks while using the
 * middleware pattern. The callback receives the current state and can:
 * - Perform side effects (logging, metrics, etc.)
 * - Return a modified state
 * - Return null to leave state unchanged
 *
 * Example:
 * ```php
 * // Add timestamp before each processor
 * $middleware = new CallBeforeMiddleware(function(ProcessingState $state) {
 *     echo "Processing: " . $state->result()->unwrap() . "\n";
 *     return $state->with(new TimestampTag());
 * });
 *
 * // Just side effects, no modification
 * $middleware = new CallBeforeMiddleware(function(ProcessingState $state) {
 *     logger()->info('Processing started', ['value' => $state->result()->unwrap()]);
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
    public function handle(ProcessingState $state, callable $next): ProcessingState {
        try {
            // Execute the before callback
            $result = ($this->callback)($state);
            // If callback returns an state, use it; otherwise use original
            $modifiedState = $result instanceof ProcessingState ? $result : $state;
            // Continue with next middleware
            return $next($modifiedState);
        } catch (Exception $e) {
            // If callback fails, create failure state but still try to continue
            // This matches the behavior of the original hook system
            $failureState = $state->withResult(\Cognesy\Utils\Result\Result::failure($e));
            return $next($failureState);
        }
    }

    /**
     * Static factory for cleaner API.
     */
    public static function call(callable $callback): self {
        return new self($callback);
    }
}