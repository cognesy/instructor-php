<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Workflow;

use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\Pipeline;

/**
 * Conditionally executes a pipeline based on envelope state.
 *
 * The condition receives the current envelope and determines whether
 * the associated pipeline should execute. If not, the envelope passes
 * through unchanged.
 */
readonly class ConditionalStep implements WorkflowStepInterface
{
    public function __construct(
        private \Closure $condition,
        private Pipeline $pipeline,
    ) {}

    public function execute(Envelope $envelope): Envelope {
        if (($this->condition)($envelope)) {
            return $this->pipeline->process($envelope)->envelope();
        }

        return $envelope;
    }
}