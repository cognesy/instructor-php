<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\PipelineMiddlewareInterface;
use Exception;

/**
 * Middleware that executes a callback before processing continues.
 *
 * This provides a convenient way to add "before" hooks while using the
 * middleware pattern. The callback receives the current envelope and can:
 * - Perform side effects (logging, metrics, etc.)
 * - Return a modified envelope
 * - Return null to leave envelope unchanged
 *
 * Example:
 * ```php
 * // Add timestamp before each processor
 * $middleware = new CallBeforeMiddleware(function(Envelope $env) {
 *     echo "Processing: " . $env->getResult()->unwrap() . "\n";
 *     return $env->with(new TimestampStamp());
 * });
 *
 * // Just side effects, no modification
 * $middleware = new CallBeforeMiddleware(function(Envelope $env) {
 *     logger()->info('Processing started', ['payload' => $env->getResult()->unwrap()]);
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
    public function handle(Envelope $envelope, callable $next): Envelope {
        try {
            // Execute the before callback
            $result = ($this->callback)($envelope);

            // If callback returns an envelope, use it; otherwise use original
            $modifiedEnvelope = $result instanceof Envelope ? $result : $envelope;

            // Continue with next middleware
            return $next($modifiedEnvelope);
        } catch (Exception $e) {
            // If callback fails, create failure envelope but still try to continue
            // This matches the behavior of the original hook system
            $failureEnvelope = $envelope->withMessage(\Cognesy\Utils\Result\Result::failure($e));
            return $next($failureEnvelope);
        }
    }

    /**
     * Static factory for cleaner API.
     */
    public static function call(callable $callback): self {
        return new self($callback);
    }
}