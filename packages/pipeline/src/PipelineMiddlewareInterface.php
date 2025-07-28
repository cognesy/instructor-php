<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

/**
 * Interface for MessageChain middleware components.
 * 
 * Middleware provides a composable way to add cross-cutting concerns like
 * logging, metrics, tracing, validation, etc. to message processing chains.
 * 
 * Each middleware can:
 * - Inspect/modify the envelope before processing
 * - Decide whether to continue to next middleware
 * - Inspect/modify the result after processing
 * - Handle errors and failures
 * 
 * Example:
 * ```php
 * class TimingMiddleware implements MessageMiddlewareInterface
 * {
 *     public function handle(Envelope $envelope, callable $next): Envelope
 *     {
 *         $start = microtime(true);
 *         $result = $next($envelope);
 *         $duration = microtime(true) - $start;
 *         
 *         return $result->with(new MetricsStamp('duration', $duration));
 *     }
 * }
 * ```
 */
interface PipelineMiddlewareInterface
{
    /**
     * Process an envelope through this middleware.
     * 
     * @param Envelope $envelope The current envelope with message and stamps
     * @param callable $next Callback to invoke next middleware/processor
     * @return Envelope The processed envelope (may be modified)
     */
    public function handle(Envelope $envelope, callable $next): Envelope;
}