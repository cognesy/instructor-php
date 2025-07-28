<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\PipelineMiddlewareInterface;

/**
 * Middleware that conditionally executes based on envelope state.
 *
 * This provides a way to add conditional logic to middleware execution,
 * similar to the finishWhen() hook functionality.
 *
 * Example:
 * ```php
 * // Only log when payload is above threshold
 * $middleware = new ConditionalMiddleware(
 *     condition: fn(Envelope $env) => $env->getResult()->unwrap() > 100,
 *     middleware: new LoggingMiddleware()
 * );
 *
 * // Early termination - skip remaining middleware if condition met
 * $middleware = new ConditionalMiddleware(
 *     condition: fn(Envelope $env) => $env->getResult()->unwrap() < 10,
 *     middleware: new CallBeforeMiddleware(fn($env) => echo "Terminating early\n"),
 *     skipRemaining: true
 * );
 * ```
 */
readonly class ConditionalMiddleware implements PipelineMiddlewareInterface
{
    public function __construct(
        private Closure $condition,
        private PipelineMiddlewareInterface $middleware,
        private bool $skipRemaining = false,
    ) {}

    /**
     * Execute middleware only if condition is met.
     */
    public function handle(Envelope $envelope, callable $next): Envelope {
        $shouldExecute = ($this->condition)($envelope);

        if (!$shouldExecute) {
            // Condition not met, skip to next middleware
            return $next($envelope);
        }

        if ($this->skipRemaining) {
            // Execute our middleware but skip remaining stack
            return $this->middleware->handle($envelope, fn($env) => $env);
        }

        // Execute our middleware then continue with stack
        return $this->middleware->handle($envelope, $next);
    }

    /**
     * Static factory for cleaner API.
     */
    public static function when(callable $condition, PipelineMiddlewareInterface $middleware, bool $skipRemaining = false): self {
        return new self($condition, $middleware, $skipRemaining);
    }

    /**
     * Factory for early termination scenarios.
     */
    public static function finishWhen(callable $condition, ?PipelineMiddlewareInterface $middleware = null): self {
        $finalMiddleware = $middleware ?? new CallBeforeMiddleware(fn($env) => $env);
        return new self($condition, $finalMiddleware, skipRemaining: true);
    }
}