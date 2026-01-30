<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Contracts\CanEmitAgentEvents;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Data\CurrentExecution;
use Cognesy\Agents\Core\Data\StepExecution;
use DateTimeImmutable;

/**
 * Records completed steps and emits continuation evaluation.
 */
final readonly class StepRecorder
{
    public function __construct(
        private CanEmitAgentEvents $eventEmitter,
    ) {}

    /**
     * Records a completed step and emits continuationEvaluated.
     */
    public function record(
        CurrentExecution $execution,
        AgentState $state,
        AgentStep $step,
        ContinuationOutcome $outcome,
    ): AgentState {
        $transitionState = $state
            ->withCurrentStep($step)
            ->withContinuationOutcome($outcome);

        $this->eventEmitter->continuationEvaluated($transitionState);

        $stepExecution = new StepExecution(
            step: $step,
            outcome: $outcome,
            startedAt: $execution->startedAt(),
            completedAt: new DateTimeImmutable(),
            stepNumber: $execution->stepNumber(),
            id: $step->id(),
        );

        $nextState = $transitionState->withStepExecutionRecorded($stepExecution);
        $this->eventEmitter->stateUpdated($nextState);

        return $nextState;
    }
}
