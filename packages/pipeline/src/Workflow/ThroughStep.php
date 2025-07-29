<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Workflow;

use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\Pipeline;

/**
 * Executes a pipeline and returns its result envelope.
 *
 * This is the main workflow step that processes data through a pipeline
 * and preserves all stamps and metadata from the execution.
 */
readonly class ThroughStep implements WorkflowStepInterface
{
    public function __construct(
        private Pipeline $pipeline,
    ) {}

    public function execute(Envelope $envelope): Envelope {
        return $this->pipeline->process($envelope)->envelope();
    }
}