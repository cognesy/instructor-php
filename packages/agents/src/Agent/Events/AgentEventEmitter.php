<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Events;

use Cognesy\Agents\Agent\Contracts\CanEmitAgentEvents;
use Cognesy\Agents\Agent\Continuation\ContinuationOutcome;
use Cognesy\Agents\Agent\Data\ToolExecution;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Exceptions\AgentException;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;

/**
 * Emits agent lifecycle events to the event bus.
 */
final class AgentEventEmitter implements CanEmitAgentEvents
{
    use HandlesEvents;

    public function __construct(?CanHandleEvents $events = null)
    {
        $this->events = EventBusResolver::using($events);
    }

    #[\Override]
    public function executionStarted(AgentState $state, int $availableTools): void
    {
        $this->events->dispatch(new AgentExecutionStarted(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            messageCount: $state->messages()->count(),
            availableTools: $availableTools,
        ));
    }

    #[\Override]
    public function stepStarted(AgentState $state): void
    {
        $this->events->dispatch(new AgentStepStarted(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            stepNumber: $state->stepCount() + 1,
            messageCount: $state->messages()->count(),
            availableTools: 0, // Not tracked at step level
        ));
    }

    #[\Override]
    public function stepCompleted(AgentState $state): void
    {
        $usage = $state->currentStep()?->usage() ?? new Usage(0, 0);
        $lastStepExecution = $state->lastStepExecution();
        $errors = $state->currentStep()?->errors();
        $errorCount = $errors?->count() ?? 0;
        $startedAt = match (true) {
            $lastStepExecution !== null => $lastStepExecution->startedAt,
            default => new DateTimeImmutable(),
        };

        $this->events->dispatch(new AgentStepCompleted(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            stepNumber: $state->stepCount(),
            hasToolCalls: $state->currentStep()?->hasToolCalls() ?? false,
            errorCount: $errorCount,
            errorMessages: $state->currentStep()?->errorsAsString() ?? '',
            usage: $usage,
            finishReason: $state->currentStep()?->finishReason(),
            startedAt: $startedAt,
        ));

        // Report token usage
        if ($usage->total() > 0) {
            $this->events->dispatch(new TokenUsageReported(
                agentId: $state->agentId(),
                parentAgentId: $state->parentAgentId(),
                operation: 'step',
                usage: $usage,
                context: [
                    'step' => $state->stepCount(),
                    'hasToolCalls' => $state->currentStep()?->hasToolCalls() ?? false,
                ],
            ));
        }
    }

    #[\Override]
    public function stateUpdated(AgentState $state): void
    {
        $this->events->dispatch(new AgentStateUpdated(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            status: $state->status(),
            stepCount: $state->stepCount(),
            stateSnapshot: $state->toArray(),
            currentStepSnapshot: $state->currentStep()?->toArray() ?? [],
        ));
    }

    #[\Override]
    public function continuationEvaluated(AgentState $state, ContinuationOutcome $outcome): void
    {
        $this->events->dispatch(new ContinuationEvaluated(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            stepNumber: $state->stepCount(),
            outcome: $outcome,
        ));
    }

    #[\Override]
    public function executionFinished(AgentState $state): void
    {
        $this->events->dispatch(new AgentExecutionCompleted(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            status: $state->status(),
            totalSteps: $state->stepCount(),
            totalUsage: $state->usage(),
            errors: $state->currentStep()?->errorsAsString(),
        ));
    }

    #[\Override]
    public function executionFailed(AgentState $state, AgentException $exception): void
    {
        $this->events->dispatch(new AgentExecutionFailed(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            exception: $exception,
            status: $state->status(),
            stepsCompleted: $state->stepCount(),
            totalUsage: $state->usage(),
            errors: $state->currentStep()?->errorsAsString(),
        ));
    }

    #[\Override]
    public function toolCallStarted(ToolCall $toolCall, DateTimeImmutable $startedAt): void
    {
        $this->events->dispatch(new ToolCallStarted(
            tool: $toolCall->name(),
            args: $toolCall->args(),
            startedAt: $startedAt,
        ));
    }

    #[\Override]
    public function toolCallCompleted(ToolExecution $execution): void
    {
        $this->events->dispatch(new ToolCallCompleted(
            tool: $execution->toolCall()->name(),
            success: $execution->result()->isSuccess(),
            error: $execution->errorAsString(),
            startedAt: $execution->startedAt(),
            completedAt: $execution->completedAt(),
        ));
    }
}
