<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Workflow;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;

/**
 * Executes a pipeline and returns its result state.
 *
 * This is the main workflow step that processes data through a pipeline
 * and preserves all tags and metadata from the execution.
 */
readonly class ThroughStep implements CanProcessState
{
    public function __construct(
        private CanProcessState $step,
    ) {}

    public function process(ProcessingState $state): ProcessingState {
        return $this->step->process($state);
    }
}