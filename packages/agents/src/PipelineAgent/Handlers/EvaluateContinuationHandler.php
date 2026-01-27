<?php declare(strict_types=1);

namespace Cognesy\Agents\PipelineAgent\Handlers;

use Cognesy\Agents\Core\Continuation\ContinuationCriteria;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\StepExecution;
use Cognesy\Agents\PipelineAgent\ExecutionContext;
use Cognesy\Agents\PipelineAgent\PhaseHandler;
use DateTimeImmutable;

/**
 * Core handler that evaluates continuation criteria and records StepExecution.
 *
 * This handler runs in the AfterStep phase to determine if the loop should continue.
 */
final class EvaluateContinuationHandler implements PhaseHandler
{
    public function __construct(
        private readonly ContinuationCriteria $criteria,
    ) {}

    #[\Override]
    public function handle(AgentState $state, ExecutionContext $ctx): AgentState
    {
        $outcome = $this->criteria->evaluateAll($state);

        $currentStep = $state->currentStep();
        if ($currentStep === null) {
            return $state;
        }

        $stepExecution = new StepExecution(
            step: $currentStep,
            outcome: $outcome,
            startedAt: $ctx->stepStartedAt ?? new DateTimeImmutable(),
            completedAt: new DateTimeImmutable(),
            stepNumber: $ctx->stepNumber,
            id: $currentStep->id(),
        );

        return $state->recordStepExecution($stepExecution);
    }
}
