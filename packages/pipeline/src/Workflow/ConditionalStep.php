<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Workflow;

use Closure;
use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\Pipeline;

/**
 * Conditionally executes a pipeline based on computation state.
 *
 * The condition receives the current computation and determines whether
 * the associated pipeline should execute. If not, the computation passes
 * through unchanged.
 */
readonly class ConditionalStep implements WorkflowStepInterface
{
    public function __construct(
        private Closure $condition,
        private Pipeline $pipeline,
    ) {}

    public function execute(Computation $computation): Computation {
        return match(true) {
            ($this->condition)($computation) => $this->pipeline->process($computation)->computation(),
            default => $computation,
        };
    }
}