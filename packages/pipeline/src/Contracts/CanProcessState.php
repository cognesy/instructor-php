<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Contracts;

use Cognesy\Pipeline\ProcessingState;

interface CanProcessState
{
    /**
     * Process the state and return the result.
     *
     * ATTENTION: Components implementing this interface should be stateless.
     *
     * @param ProcessingState $state The state to process
     * @return ProcessingState The processed state
     */
    public function process(ProcessingState $state): ProcessingState;
}
