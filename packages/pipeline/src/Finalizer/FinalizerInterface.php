<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Finalizer;

use Cognesy\Pipeline\ProcessingState;

/**
 * Interface for pipeline finalizers that extract final values from states.
 *
 * Finalizers are conceptually different from processors:
 * - Processors: Transform ProcessingState → ProcessingState (pipeline steps)
 * - Finalizers: Extract final value from ProcessingState → mixed (pipeline output)
 */
interface FinalizerInterface
{
    /**
     * Extract a final value from the state.
     *
     * @param ProcessingState $state The state to finalize
     * @return mixed The final extracted value
     */
    public function finalize(ProcessingState $state): mixed;
}