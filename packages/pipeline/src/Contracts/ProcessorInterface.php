<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Contracts;

use Cognesy\Pipeline\ProcessingState;

/**
 * Interface for pipeline processors that can handle different input types.
 *
 * This interface eliminates runtime type detection by performing upfront
 * wrapping when processors are added to the pipeline.
 */
interface ProcessorInterface
{
    /**
     * Process the state and return the result.
     *
     * @param ProcessingState $state The state to process
     * @return ProcessingState The processed state
     */
    public function process(ProcessingState $state): ProcessingState;
}