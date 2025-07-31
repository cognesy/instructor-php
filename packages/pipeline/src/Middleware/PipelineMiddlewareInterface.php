<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Cognesy\Pipeline\Computation;

/**
 * Interface for MessageChain middleware components.
 * 
 * Middleware provides a composable way to add cross-cutting concerns like
 * logging, metrics, tracing, validation, etc. to message processing chains.
 * 
 * Each middleware can:
 * - Inspect/modify the computation before processing
 * - Decide whether to continue to next middleware
 * - Inspect/modify the result after processing
 * - Handle errors and failures
 * 
 * Example:
 * ```php
 * class TimingMiddleware implements MessageMiddlewareInterface
 * {
 *     public function handle(Computation $computation, callable $next): Computation
 *     {
 *         $start = microtime(true);
 *         $result = $next($computation);
 *         $duration = microtime(true) - $start;
 *         
 *         return $result->with(new MetricsTag('duration', $duration));
 *     }
 * }
 * ```
 */
interface PipelineMiddlewareInterface
{
    /**
     * Process an computation through this middleware.
     * 
     * @param Computation $computation The current computation with message and tags
     * @param callable $next Callback to invoke next middleware/processor
     * @return Computation The processed computation (may be modified)
     */
    public function handle(Computation $computation, callable $next): Computation;
}