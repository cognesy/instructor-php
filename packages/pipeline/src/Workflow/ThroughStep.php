<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Workflow;

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\Pipeline;

/**
 * Executes a pipeline and returns its result computation.
 *
 * This is the main workflow step that processes data through a pipeline
 * and preserves all tags and metadata from the execution.
 */
readonly class ThroughStep implements WorkflowStepInterface
{
    public function __construct(
        private Pipeline $pipeline,
    ) {}

    public function execute(Computation $computation): Computation {
        return $this->pipeline->process($computation)->computation();
    }
}