<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\ProcessingState;
use Exception;

/**
 * Middleware that executes a callback after processing completes.
 *
 * This provides a convenient way to add "after" hooks while using the
 * middleware pattern. The callback receives the processed state and can:
 * - Perform side effects (logging, metrics, cleanup, etc.)
 * - Return a modified state
 * - Return null to leave state unchanged
 *
 * Example:
 * ```php
 * // Log results after processing
 * $middleware = new CallAfterMiddleware(function(ProcessingState $state) {
 *     $result = $state->result();
 *     if ($result->isSuccess()) {
 *         echo "Success: " . $result->unwrap() . "\n";
 *     } else {
 *         echo "Failed: " . $result->error() . "\n";
 *     }
 * });
 *
 * // Modify result based on conditions
 * $middleware = new CallAfterMiddleware(function(ProcessingState $state) {
 *     $result = $state->result();
 *     if ($result->isSuccess() && $result->unwrap() > 100) {
 *         return $state->withMessage(Result::success($result->unwrap() / 2));
 *     }
 *     return $state;
 * });
 * ```
 */
readonly class CallAfterMiddleware implements PipelineMiddlewareInterface
{
    public function __construct(
        private Closure $callback,
    ) {}

    /**
     * Execute next middleware first, then run the callback on the result.
     */
    public function handle(ProcessingState $state, callable $next): ProcessingState {
        // Process through next middleware first
        $processedState = $next($state);
        try {
            // Execute the after callback on the processed result
            $result = ($this->callback)($processedState);
            // If callback returns an state, use it; otherwise use processed state
            return $result instanceof ProcessingState ? $result : $processedState;
        } catch (Exception $e) {
            // If callback fails, return the processed state as-is
            // This prevents after hooks from breaking the main processing flow
            return $processedState;
        }
    }

    /**
     * Static factory for cleaner API.
     */
    public static function call(callable $callback): self {
        return new self($callback);
    }
}