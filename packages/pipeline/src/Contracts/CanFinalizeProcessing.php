<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Contracts;

use Cognesy\Pipeline\ProcessingState;

/**
 * Interface for pipeline finalizers that extract final values from states.
 *
 * Finalizers are conceptually different from processors:
 * - Processors: Transform ProcessingState → ProcessingState (pipeline steps)
 * - Finalizers: Extract final value from ProcessingState → mixed (pipeline output)
 *
 * ATTENTION: Components implementing this interface should be stateless.
 */
interface CanFinalizeProcessing {
    public function finalize(ProcessingState $state): mixed;
}