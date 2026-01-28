<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

use Cognesy\Agents\Core\Contracts\CanEmitAgentEvents;
use Cognesy\Agents\Core\Contracts\CanHandleAgentErrors;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\CurrentExecution;
use Cognesy\Agents\Core\Data\StepExecution;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\Exceptions\AgentException;
use DateTimeImmutable;
use Throwable;

/**
 * Records failure steps and emits related events.
 */
final readonly class ErrorRecorder
{
    public function __construct(
        private CanHandleAgentErrors $errorHandler,
        private CanEmitAgentEvents $eventEmitter,
    ) {}

    public function record(Throwable $error, AgentState $state, CurrentExecution $execution): ErrorRecordingResult
    {
        $handling = $this->errorHandler->handleError($error, $state);

        $transitionState = $state
            ->withStatus(AgentStatus::Failed)
            ->withNewStepRecorded($handling->failureStep);

        $this->eventEmitter->continuationEvaluated($transitionState, $handling->outcome);

        $stepExecution = new StepExecution(
            step: $handling->failureStep,
            outcome: $handling->outcome,
            startedAt: $execution->startedAt,
            completedAt: new DateTimeImmutable(),
            stepNumber: $execution->stepNumber,
            id: $handling->failureStep->id(),
        );

        $nextState = $transitionState
            ->withStatus($handling->finalStatus)
            ->withStepExecutionRecorded($stepExecution);

        $this->eventEmitter->stateUpdated($nextState);

        $agentException = $handling->exception instanceof AgentException
            ? $handling->exception
            : AgentException::fromThrowable($handling->exception);

        $isFailed = $handling->finalStatus === AgentStatus::Failed;

        if ($isFailed) {
            $this->eventEmitter->executionFailed($nextState, $agentException);
        }

        return new ErrorRecordingResult(
            state: $nextState,
            exception: $agentException,
            isFailed: $isFailed,
        );
    }
}

