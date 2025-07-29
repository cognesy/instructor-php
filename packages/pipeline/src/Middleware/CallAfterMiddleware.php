<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\PipelineMiddlewareInterface;
use Exception;

/**
 * Middleware that executes a callback after processing completes.
 *
 * This provides a convenient way to add "after" hooks while using the
 * middleware pattern. The callback receives the processed envelope and can:
 * - Perform side effects (logging, metrics, cleanup, etc.)
 * - Return a modified envelope
 * - Return null to leave envelope unchanged
 *
 * Example:
 * ```php
 * // Log results after processing
 * $middleware = new CallAfterMiddleware(function(Envelope $env) {
 *     $result = $env->result();
 *     if ($result->isSuccess()) {
 *         echo "Success: " . $result->unwrap() . "\n";
 *     } else {
 *         echo "Failed: " . $result->error() . "\n";
 *     }
 * });
 *
 * // Modify result based on conditions
 * $middleware = new CallAfterMiddleware(function(Envelope $env) {
 *     $result = $env->result();
 *     if ($result->isSuccess() && $result->unwrap() > 100) {
 *         return $env->withMessage(Result::success($result->unwrap() / 2));
 *     }
 *     return $env;
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
    public function handle(Envelope $envelope, callable $next): Envelope {
        // Process through next middleware first
        $processedEnvelope = $next($envelope);

        try {
            // Execute the after callback on the processed result
            $result = ($this->callback)($processedEnvelope);

            // If callback returns an envelope, use it; otherwise use processed envelope
            return $result instanceof Envelope ? $result : $processedEnvelope;
        } catch (Exception $e) {
            // If callback fails, return the processed envelope as-is
            // This prevents after hooks from breaking the main processing flow
            return $processedEnvelope;
        }
    }

    /**
     * Static factory for cleaner API.
     */
    public static function call(callable $callback): self {
        return new self($callback);
    }
}