<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Contracts;

use Cognesy\Pipeline\ProcessingState;

/**
 * Interface for state processing components.
 * 
 * Middleware provides a composable way to add cross-cutting concerns like
 * logging, metrics, tracing, validation, etc. to message processing chains.
 * 
 * They can:
 * - Inspect/modify the state before processing
 * - Decide whether to continue to next middleware
 * - Inspect/modify the result after processing
 * - Handle errors and failures
 *
 * ATTENTION: Components implementing this interface should be stateless.
 */
interface CanControlStateProcessing
{
    /**
     * @param callable(ProcessingState):ProcessingState $next Callback to invoke next component
     */
    public function handle(ProcessingState $state, callable $next): ProcessingState;
}