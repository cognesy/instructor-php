<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\PipelineMiddlewareInterface;
use Exception;

/**
 * Middleware that executes a callback after processing completes.
 *
 * This provides a convenient way to add "after" hooks while using the
 * middleware pattern. The callback receives the processed computation and can:
 * - Perform side effects (logging, metrics, cleanup, etc.)
 * - Return a modified computation
 * - Return null to leave computation unchanged
 *
 * Example:
 * ```php
 * // Log results after processing
 * $middleware = new CallAfterMiddleware(function(Computation $computation) {
 *     $result = $computation->result();
 *     if ($result->isSuccess()) {
 *         echo "Success: " . $result->unwrap() . "\n";
 *     } else {
 *         echo "Failed: " . $result->error() . "\n";
 *     }
 * });
 *
 * // Modify result based on conditions
 * $middleware = new CallAfterMiddleware(function(Computation $computation) {
 *     $result = $computation->result();
 *     if ($result->isSuccess() && $result->unwrap() > 100) {
 *         return $computation->withMessage(Result::success($result->unwrap() / 2));
 *     }
 *     return $computation;
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
    public function handle(Computation $computation, callable $next): Computation {
        // Process through next middleware first
        $processedComputation = $next($computation);
        try {
            // Execute the after callback on the processed result
            $result = ($this->callback)($processedComputation);
            // If callback returns an computation, use it; otherwise use processed computation
            return $result instanceof Computation ? $result : $processedComputation;
        } catch (Exception $e) {
            // If callback fails, return the processed computation as-is
            // This prevents after hooks from breaking the main processing flow
            return $processedComputation;
        }
    }

    /**
     * Static factory for cleaner API.
     */
    public static function call(callable $callback): self {
        return new self($callback);
    }
}