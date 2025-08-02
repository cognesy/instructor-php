<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Cognesy\Pipeline\ProcessingState;

/**
 * Interface for MessageChain middleware components.
 * 
 * Middleware provides a composable way to add cross-cutting concerns like
 * logging, metrics, tracing, validation, etc. to message processing chains.
 * 
 * Each middleware can:
 * - Inspect/modify the state before processing
 * - Decide whether to continue to next middleware
 * - Inspect/modify the result after processing
 * - Handle errors and failures
 * 
 * Example:
 * ```php
 * class TimingMiddleware implements MessageMiddlewareInterface
 * {
 *     public function handle(ProcessingState $state, callable $next): ProcessingState
 *     {
 *         $start = microtime(true);
 *         $result = $next($state);
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
     * Process an state through this middleware.
     * 
     * @param ProcessingState $state The current state with result and tags
     * @param callable $next Callback to invoke next middleware/processor
     * @return ProcessingState The processed state (may be modified)
     */
    public function handle(ProcessingState $state, callable $next): ProcessingState;
}