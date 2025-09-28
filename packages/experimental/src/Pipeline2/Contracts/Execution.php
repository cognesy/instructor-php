<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Contracts;

// =============================================================================
// I. CORE CONTRACTS & INTERFACES
// =============================================================================

/**
 * Represents a controllable, running instance of a pipeline.
 *
 * This contract decouples the Runtime from the concrete execution mechanism.
 */
interface Execution
{
    /**
     * Runs the pipeline to completion and returns the final result.
     *
     * @return mixed The final payload after all applicable operators and the
     * terminal function have been executed.
     */
    public function run(): mixed;
}
