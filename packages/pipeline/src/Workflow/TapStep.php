<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Workflow;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;

/**
 * Executes a pipeline for side effects without affecting the main flow.
 *
 * The tap step runs the pipeline but ignores its result, always returning
 * the original state. This is useful for logging, metrics, or other
 * observability concerns that shouldn't impact the main data flow.
 */
readonly class TapStep implements CanProcessState
{
    public function __construct(
        private CanProcessState $step,
    ) {}

    public function process(ProcessingState $state): ProcessingState {
        $value = $this->step->process($state); // Force execution, ignore the result
        return $state;
    }
}