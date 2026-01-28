<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

use Cognesy\Agents\Core\Continuation\ContinuationCriteria;
use Cognesy\Agents\Core\Contracts\CanEmitAgentEvents;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Data\CurrentExecution;
use Cognesy\Agents\Core\Data\StepExecution;
use DateTimeImmutable;

/**
 * Records completed steps and evaluates continuation criteria.
 */
final readonly class StepRecorder
{
    public function __construct(
        private ContinuationCriteria $continuationCriteria,
        private CanEmitAgentEvents $eventEmitter,
    ) {}

    public function record(CurrentExecution $execution, AgentState $state, AgentStep $step): AgentState
    {
        $transitionState = $state->recordStep($step);

        $outcome = $this->continuationCriteria->evaluateAll($transitionState);
        $this->eventEmitter->continuationEvaluated($transitionState, $outcome);

        $stepExecution = new StepExecution(
            step: $step,
            outcome: $outcome,
            startedAt: $execution->startedAt,
            completedAt: new DateTimeImmutable(),
            stepNumber: $execution->stepNumber,
            id: $step->id(),
        );

        $nextState = $transitionState->recordStepExecution($stepExecution);
        $this->eventEmitter->stateUpdated($nextState);

        return $nextState;
    }
}

