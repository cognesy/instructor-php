<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Workflow;

use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\Pipeline;

/**
 * Executes a pipeline for side effects without affecting the main flow.
 *
 * The tap step runs the pipeline but ignores its result, always returning
 * the original envelope. This is useful for logging, metrics, or other
 * observability concerns that shouldn't impact the main data flow.
 */
readonly class TapStep implements WorkflowStepInterface
{
    public function __construct(
        private Pipeline $pipeline,
    ) {}

    public function execute(Envelope $envelope): Envelope {
        // Execute pipeline for side effects, but ignore the result
        $payload = $this->pipeline->process($envelope)->payload(); // Force execution
        return $envelope;
    }
}