<?php declare(strict_types=1);

namespace Cognesy\Agents\PipelineAgent;

use Cognesy\Agents\Core\Continuation\ContinuationCriteria;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\StepExecution;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\Contracts\CanHandleAgentErrors;
use DateTimeImmutable;
use Throwable;

/**
 * A composable pipeline that orchestrates handlers for each execution phase.
 *
 * Handlers are registered per phase and executed in order.
 * Core functionality (continuation checking, error handling) is built-in.
 */
final class ComposablePipeline implements ExecutionPipeline
{
    /** @var array<string, list<PhaseHandler>> */
    private array $handlers = [];

    public function __construct(
        private readonly ContinuationCriteria $continuationCriteria,
        private readonly CanHandleAgentErrors $errorHandler,
    ) {}

    /**
     * Add a handler for a specific phase.
     *
     * Handlers are executed in the order they are added.
     */
    public function addHandler(Phase $phase, PhaseHandler $handler): self
    {
        $clone = clone $this;
        $clone->handlers = $this->handlers;
        $clone->handlers[$phase->value][] = $handler;
        return $clone;
    }

    /**
     * Add multiple handlers for a phase.
     */
    public function addHandlers(Phase $phase, PhaseHandler ...$handlers): self
    {
        $clone = $this;
        foreach ($handlers as $handler) {
            $clone = $clone->addHandler($phase, $handler);
        }
        return $clone;
    }

    #[\Override]
    public function beforeExecution(AgentState $state, ExecutionContext $ctx): AgentState
    {
        $ctx->beginExecution();
        $this->continuationCriteria->executionStarted($ctx->executionStartedAt);
        return $this->runHandlers(Phase::BeforeExecution, $state, $ctx);
    }

    #[\Override]
    public function shouldContinue(AgentState $state, ExecutionContext $ctx): bool
    {
        if ($state->status() === AgentStatus::Failed) {
            return false;
        }
        if ($state->stepCount() === 0) {
            return $this->continuationCriteria->canContinue($state);
        }
        return $state->stepExecutions()->shouldContinue();
    }

    #[\Override]
    public function beforeStep(AgentState $state, ExecutionContext $ctx): AgentState
    {
        $ctx->beginStep();
        $state = $state->beginStepExecution();
        return $this->runHandlers(Phase::BeforeStep, $state, $ctx);
    }

    #[\Override]
    public function executeStep(AgentState $state, ExecutionContext $ctx): AgentState
    {
        return $this->runHandlers(Phase::ExecuteStep, $state, $ctx);
    }

    #[\Override]
    public function afterStep(AgentState $state, ExecutionContext $ctx): AgentState
    {
        return $this->runHandlers(Phase::AfterStep, $state, $ctx);
    }

    #[\Override]
    public function afterExecution(AgentState $state, ExecutionContext $ctx): AgentState
    {
        $state = $state->clearCurrentExecution();
        return $this->runHandlers(Phase::AfterExecution, $state, $ctx);
    }

    #[\Override]
    public function onError(Throwable $error, AgentState $state, ExecutionContext $ctx): AgentState
    {
        $handling = $this->errorHandler->handleError($error, $state);
        $ctx->lastException = $handling->exception;

        $stepExecution = new StepExecution(
            step: $handling->failureStep,
            outcome: $handling->outcome,
            startedAt: $ctx->stepStartedAt ?? new DateTimeImmutable(),
            completedAt: new DateTimeImmutable(),
            stepNumber: $ctx->stepNumber,
            id: $handling->failureStep->id(),
        );

        $state = $state
            ->withStatus(AgentStatus::Failed)
            ->recordStep($handling->failureStep)
            ->recordStepExecution($stepExecution)
            ->withStatus($handling->finalStatus);

        return $this->runHandlers(Phase::OnError, $state, $ctx);
    }

    private function runHandlers(Phase $phase, AgentState $state, ExecutionContext $ctx): AgentState
    {
        foreach ($this->handlers[$phase->value] ?? [] as $handler) {
            $state = $handler->handle($state, $ctx);
        }
        return $state;
    }
}
