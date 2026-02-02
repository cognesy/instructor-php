<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\Stop\StopSignal;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;
use Throwable;

/**
 * Emits agent lifecycle events to the event bus.
 */
final class AgentEventEmitter implements CanEmitAgentEvents
{
    use HandlesEvents;

    public function __construct(?CanHandleEvents $events = null) {
        $this->events = EventBusResolver::using($events);
    }

    public function eventHandler(): CanHandleEvents {
        return $this->events;
    }

    #[\Override]
    public function executionStarted(AgentState $state, int $availableTools): void {
        $this->events->dispatch(new AgentExecutionStarted(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            messageCount: $state->messages()->count(),
            availableTools: $availableTools,
        ));
    }

    #[\Override]
    public function stepStarted(AgentState $state): void {
        $this->events->dispatch(new AgentStepStarted(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            stepNumber: $state->stepCount() + 1,
            messageCount: $state->messages()->count(),
            availableTools: 0, // Not tracked at step level
        ));
    }

    #[\Override]
    public function stepCompleted(AgentState $state): void {
        $usage = $state->currentStep()?->usage() ?? new Usage(0, 0);
        $lastStepExecution = $state->lastStepExecution();
        $errors = $state->currentStep()?->errors();
        $errorCount = $errors?->count() ?? 0;
        $startedAt = match (true) {
            $lastStepExecution !== null => $lastStepExecution->startedAt(),
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
    public function stateUpdated(AgentState $state): void {
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
    public function continuationEvaluated(AgentState $state): void {
        $this->events->dispatch(new ContinuationEvaluated(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            stepNumber: $state->stepCount(),
            continuation: $state->executionContinuation(),
        ));
    }

    #[\Override]
    public function executionFinished(AgentState $state): void {
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
    public function executionFailed(AgentState $state, Throwable $exception): void{
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

    #[\Override]
    public function toolCallBlocked(ToolCall $toolCall, string $reason, ?string $hookName = null): void
    {
        $this->events->dispatch(new ToolCallBlocked(
            tool: $toolCall->name(),
            args: $toolCall->args(),
            reason: $reason,
            hookName: $hookName,
        ));
    }

    // INFERENCE EVENTS ////////////////////////////////////////////

    #[\Override]
    public function inferenceRequestStarted(AgentState $state, int $messageCount, ?string $model = null): void
    {
        $this->events->dispatch(new InferenceRequestStarted(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            stepNumber: $state->stepCount() + 1,
            messageCount: $messageCount,
            model: $model,
        ));
    }

    #[\Override]
    public function inferenceResponseReceived(AgentState $state, ?InferenceResponse $response, DateTimeImmutable $requestStartedAt): void
    {
        $this->events->dispatch(new InferenceResponseReceived(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            stepNumber: $state->stepCount() + 1,
            usage: $response?->usage(),
            finishReason: $response?->finishReason()?->value,
            requestStartedAt: $requestStartedAt,
        ));
    }

    // SUBAGENT EVENTS ////////////////////////////////////////////

    #[\Override]
    public function subagentSpawning(string $parentAgentId, string $subagentName, string $prompt, int $depth, int $maxDepth): void
    {
        $this->events->dispatch(new SubagentSpawning(
            parentAgentId: $parentAgentId,
            subagentName: $subagentName,
            prompt: $prompt,
            depth: $depth,
            maxDepth: $maxDepth,
        ));
    }

    #[\Override]
    public function subagentCompleted(string $parentAgentId, string $subagentId, string $subagentName, AgentStatus $status, int $steps, ?Usage $usage, DateTimeImmutable $startedAt): void
    {
        $this->events->dispatch(new SubagentCompleted(
            parentAgentId: $parentAgentId,
            subagentId: $subagentId,
            subagentName: $subagentName,
            status: $status,
            steps: $steps,
            usage: $usage,
            startedAt: $startedAt,
        ));
    }

    // HOOK EVENTS ////////////////////////////////////////////

    #[\Override]
    public function hookExecuted(string $hookType, string $tool, string $outcome, ?string $reason, DateTimeImmutable $startedAt): void
    {
        $this->events->dispatch(new HookExecuted(
            hookType: $hookType,
            tool: $tool,
            outcome: $outcome,
            reason: $reason,
            startedAt: $startedAt,
        ));
    }

    // EXTRACTION/VALIDATION EVENTS ////////////////////////////////////////////

    #[\Override]
    public function decisionExtractionFailed(
        AgentState $state,
        string $errorMessage,
        string $errorType,
        int $attemptNumber = 1,
        int $maxAttempts = 1
    ): void {
        $this->events->dispatch(new DecisionExtractionFailed(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            stepNumber: $state->stepCount() + 1,
            errorMessage: $errorMessage,
            errorType: $errorType,
            attemptNumber: $attemptNumber,
            maxAttempts: $maxAttempts,
        ));
    }

    #[\Override]
    public function validationFailed(AgentState $state, string $validationType, array $errors): void
    {
        $this->events->dispatch(new ValidationFailed(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            stepNumber: $state->stepCount() + 1,
            validationType: $validationType,
            errors: $errors,
        ));
    }

    public function stopSignalReceived(StopSignal $signal) : void {
        $this->events->dispatch(new StopSignalReceived(
            reason: $signal->reason,
            message: $signal->message,
            context: $signal->context,
            source: $signal->source,
        ));
    }
}
