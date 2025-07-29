<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Workflow;

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\Pipeline;

/**
 * Executes a pipeline for side effects without affecting the main flow.
 *
 * The tap step runs the pipeline but ignores its result, always returning
 * the original computation. This is useful for logging, metrics, or other
 * observability concerns that shouldn't impact the main data flow.
 */
readonly class TapStep implements WorkflowStepInterface
{
    public function __construct(
        private Pipeline $pipeline,
    ) {}

    public function execute(Computation $computation): Computation {
        $value = $this->pipeline->process($computation)->value(); // Force execution, ignore the result
        return $computation;
    }
}