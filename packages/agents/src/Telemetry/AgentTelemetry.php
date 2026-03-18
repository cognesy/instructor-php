<?php declare(strict_types=1);

namespace Cognesy\Agents\Telemetry;

use Cognesy\Agents\Events\AgentEvent;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionFailed;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\AgentExecutionStopped;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Agents\Events\AgentStepStarted;
use Cognesy\Agents\Events\SubagentCompleted;
use Cognesy\Agents\Events\SubagentSpawning;
use Cognesy\Agents\Events\TokenUsageReported;
use Cognesy\Agents\Events\ToolCallBlocked;
use Cognesy\Agents\Events\ToolCallCompleted;
use Cognesy\Agents\Events\ToolCallStarted;
use Cognesy\Telemetry\Domain\Envelope\OperationCorrelation;
use Cognesy\Telemetry\Domain\Envelope\OperationDescriptor;
use Cognesy\Telemetry\Domain\Envelope\OperationIO;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;

final readonly class AgentTelemetry
{
    public static function attach(AgentEvent $event, ?AgentTelemetrySeed $seed = null): AgentEvent
    {
        $envelope = self::envelopeFor($event, $seed);

        if ($envelope === null) {
            return $event;
        }

        $event->data = [
            ...is_array($event->data) ? $event->data : [],
            TelemetryEnvelope::KEY => $envelope->toArray(),
        ];

        return $event;
    }

    private static function envelopeFor(
        AgentEvent $event,
        ?AgentTelemetrySeed $seed,
    ): ?TelemetryEnvelope {
        return match (true) {
            $event instanceof AgentExecutionStarted => self::executionStarted($event, $seed),
            $event instanceof AgentStepStarted => self::stepStarted($event, $seed),
            $event instanceof AgentStepCompleted => self::stepCompleted($event, $seed),
            $event instanceof AgentExecutionCompleted => self::executionCompleted($event, $seed),
            $event instanceof AgentExecutionStopped => self::executionStopped($event, $seed),
            $event instanceof AgentExecutionFailed => self::executionFailed($event, $seed),
            $event instanceof ToolCallStarted => self::toolCallStarted($event, $seed),
            $event instanceof ToolCallCompleted => self::toolCallCompleted($event, $seed),
            $event instanceof ToolCallBlocked => self::toolCallBlocked($event, $seed),
            $event instanceof TokenUsageReported => self::tokenUsage($event, $seed),
            $event instanceof SubagentSpawning => self::subagentSpawning($event, $seed),
            $event instanceof SubagentCompleted => self::subagentCompleted($event, $seed),
            default => null,
        };
    }

    private static function executionStarted(
        AgentExecutionStarted $event,
        ?AgentTelemetrySeed $seed,
    ): TelemetryEnvelope {
        $correlation = self::executionCorrelation($event->executionId, $seed);

        return (new TelemetryEnvelope(
            operation: new OperationDescriptor(
                id: $event->executionId,
                type: 'agent.execution',
                name: 'agent.execute',
                kind: match ($correlation->parentOperationId()) {
                    null => OperationKind::RootSpan,
                    default => OperationKind::Span,
                },
            ),
            correlation: $correlation,
            trace: $seed?->trace(),
        ))->withIO(new OperationIO(
            input: $event->messages !== [] ? $event->messages : ['message_count' => $event->messageCount],
        ));
    }

    private static function stepStarted(AgentStepStarted $event, ?AgentTelemetrySeed $seed): TelemetryEnvelope
    {
        return (new TelemetryEnvelope(
            operation: new OperationDescriptor(
                id: self::stepId($event->executionId, $event->stepNumber),
                type: 'agent.step',
                name: 'agent.step',
                kind: OperationKind::Span,
            ),
            correlation: self::childCorrelation(
                seed: $seed,
                rootOperationId: $event->executionId,
                parentOperationId: $event->executionId,
            ),
        ))->withIO(new OperationIO(
            input: $event->messages !== [] ? $event->messages : ['message_count' => $event->messageCount],
        ));
    }

    private static function stepCompleted(AgentStepCompleted $event, ?AgentTelemetrySeed $seed): TelemetryEnvelope
    {
        return (new TelemetryEnvelope(
            operation: new OperationDescriptor(
                id: self::stepId($event->executionId, $event->stepNumber),
                type: 'agent.step',
                name: 'agent.step',
                kind: OperationKind::Span,
            ),
            correlation: self::childCorrelation(
                seed: $seed,
                rootOperationId: $event->executionId,
                parentOperationId: $event->executionId,
            ),
        ))->withIO(new OperationIO(
            output: $event->outputMessages !== [] ? $event->outputMessages : array_filter([
                'finish_reason' => $event->finishReason?->value,
                'has_tool_calls' => $event->hasToolCalls,
                'error_count' => $event->errorCount > 0 ? $event->errorCount : null,
                'errors' => $event->errorMessages !== '' ? $event->errorMessages : null,
                'usage' => $event->usage->toArray(),
            ], static fn(mixed $v): bool => $v !== null),
        ));
    }

    private static function executionCompleted(AgentExecutionCompleted $event, ?AgentTelemetrySeed $seed): TelemetryEnvelope
    {
        return self::executionSpanEnvelope($event->executionId, $seed)->withIO(new OperationIO(
            output: $event->outputMessages !== [] ? $event->outputMessages : array_filter([
                'status' => $event->status->value,
                'total_steps' => $event->totalSteps,
                'usage' => $event->totalUsage->toArray(),
                'errors' => $event->errors,
            ], static fn(mixed $v): bool => $v !== null),
        ));
    }

    private static function executionStopped(AgentExecutionStopped $event, ?AgentTelemetrySeed $seed): TelemetryEnvelope
    {
        return self::executionSpanEnvelope($event->executionId, $seed);
    }

    private static function executionFailed(AgentExecutionFailed $event, ?AgentTelemetrySeed $seed): TelemetryEnvelope
    {
        return self::executionSpanEnvelope($event->executionId, $seed)->withIO(new OperationIO(
            output: array_filter([
                'error' => $event->exception->getMessage(),
                'error_type' => get_class($event->exception),
                'steps_completed' => $event->stepsCompleted,
            ], static fn(mixed $v): bool => $v !== null),
        ));
    }

    private static function executionSpanEnvelope(string $executionId, ?AgentTelemetrySeed $seed): TelemetryEnvelope
    {
        return new TelemetryEnvelope(
            operation: new OperationDescriptor(
                id: $executionId,
                type: 'agent.execution',
                name: 'agent.execute',
                kind: OperationKind::Span,
            ),
            correlation: self::executionCorrelation($executionId, $seed),
        );
    }

    private static function toolCallStarted(ToolCallStarted $event, ?AgentTelemetrySeed $seed): TelemetryEnvelope
    {
        $spanId = $event->toolCallId !== '' ? $event->toolCallId : $event->id;

        return self::toolSpanEnvelope(
            spanId: $spanId,
            executionId: $event->executionId,
            stepNumber: $event->stepNumber,
            name: 'agent.tool_call',
            seed: $seed,
        )->withIO(new OperationIO(
            input: ['tool' => $event->tool, 'args' => $event->args],
        ));
    }

    private static function toolCallCompleted(ToolCallCompleted $event, ?AgentTelemetrySeed $seed): TelemetryEnvelope
    {
        $spanId = $event->toolCallId !== '' ? $event->toolCallId : $event->id;

        return self::toolSpanEnvelope(
            spanId: $spanId,
            executionId: $event->executionId,
            stepNumber: $event->stepNumber,
            name: 'agent.tool_call',
            seed: $seed,
        )->withIO(new OperationIO(
            input: ['tool' => $event->tool],
            output: $event->result,
        ));
    }

    private static function toolCallBlocked(ToolCallBlocked $event, ?AgentTelemetrySeed $seed): TelemetryEnvelope
    {
        return self::toolEventEnvelope(
            eventId: $event->id,
            executionId: $event->executionId,
            stepNumber: $event->stepNumber,
            name: 'agent.tool_call.blocked',
            seed: $seed,
        )->withIO(new OperationIO(
            input: ['tool' => $event->tool, 'args' => $event->args],
        ));
    }

    private static function tokenUsage(TokenUsageReported $event, ?AgentTelemetrySeed $seed): TelemetryEnvelope
    {
        $stepNumber = is_int($event->context['step'] ?? null) ? $event->context['step'] : null;
        $parentOperationId = match ($stepNumber) {
            null => $event->executionId,
            default => self::stepId($event->executionId, $stepNumber),
        };

        return new TelemetryEnvelope(
            operation: new OperationDescriptor(
                id: $event->id,
                type: 'agent.token_usage',
                name: 'inference.client.token.usage.total',
                kind: OperationKind::Metric,
            ),
            correlation: self::childCorrelation(
                seed: $seed,
                rootOperationId: $event->executionId,
                parentOperationId: $parentOperationId,
            ),
        );
    }

    private static function subagentSpawning(SubagentSpawning $event, ?AgentTelemetrySeed $seed): ?TelemetryEnvelope
    {
        $correlation = self::subagentEventCorrelation(
            parentExecutionId: $event->parentExecutionId,
            parentStepNumber: $event->parentStepNumber,
            toolCallId: $event->toolCallId,
            seed: $seed,
        );
        if ($correlation === null) {
            return null;
        }

        return (new TelemetryEnvelope(
            operation: new OperationDescriptor(
                id: $event->id,
                type: 'agent.subagent',
                name: 'agent.subagent.spawning',
                kind: OperationKind::Event,
            ),
            correlation: $correlation,
        ))->withIO(new OperationIO(
            input: [
                'subagent' => $event->subagentName,
                'depth' => $event->depth,
                'max_depth' => $event->maxDepth,
                'prompt' => $event->prompt,
            ],
        ));
    }

    private static function subagentCompleted(SubagentCompleted $event, ?AgentTelemetrySeed $seed): ?TelemetryEnvelope
    {
        $correlation = self::subagentEventCorrelation(
            parentExecutionId: $event->parentExecutionId,
            parentStepNumber: $event->parentStepNumber,
            toolCallId: $event->toolCallId,
            seed: $seed,
        );
        if ($correlation === null) {
            return null;
        }

        return (new TelemetryEnvelope(
            operation: new OperationDescriptor(
                id: $event->id,
                type: 'agent.subagent',
                name: 'agent.subagent.completed',
                kind: OperationKind::Event,
            ),
            correlation: $correlation,
        ))->withIO(new OperationIO(
            output: array_filter([
                'subagent' => $event->subagentName,
                'subagent_id' => $event->subagentId,
                'status' => $event->status->value,
                'steps' => $event->steps,
                'tokens' => $event->usage?->total(),
                'duration_ms' => $event->data['duration_ms'] ?? null,
            ], static fn(mixed $value): bool => $value !== null),
        ));
    }

    private static function toolSpanEnvelope(
        string $spanId,
        string $executionId,
        int $stepNumber,
        string $name,
        ?AgentTelemetrySeed $seed,
    ): TelemetryEnvelope {
        return new TelemetryEnvelope(
            operation: new OperationDescriptor(
                id: $spanId,
                type: 'agent.tool_call',
                name: $name,
                kind: OperationKind::Span,
            ),
            correlation: self::childCorrelation(
                seed: $seed,
                rootOperationId: $executionId,
                parentOperationId: self::stepId($executionId, $stepNumber),
            ),
        );
    }

    private static function toolEventEnvelope(
        string $eventId,
        string $executionId,
        int $stepNumber,
        string $name,
        ?AgentTelemetrySeed $seed,
    ): TelemetryEnvelope {
        return new TelemetryEnvelope(
            operation: new OperationDescriptor(
                id: $eventId,
                type: 'agent.tool_call',
                name: $name,
                kind: OperationKind::Event,
            ),
            correlation: self::childCorrelation(
                seed: $seed,
                rootOperationId: $executionId,
                parentOperationId: self::stepId($executionId, $stepNumber),
            ),
        );
    }

    private static function executionCorrelation(string $executionId, ?AgentTelemetrySeed $seed): OperationCorrelation
    {
        return match ($seed?->parentOperationId()) {
            null => OperationCorrelation::root(
                operationId: $executionId,
                sessionId: $seed?->sessionId(),
                userId: $seed?->userId(),
                conversationId: $seed?->conversationId(),
                requestId: $seed?->requestId(),
            ),
            default => self::childCorrelation(
                seed: $seed,
                rootOperationId: $executionId,
                parentOperationId: $seed->parentOperationId(),
            ),
        };
    }

    private static function subagentEventCorrelation(
        ?string $parentExecutionId,
        ?int $parentStepNumber,
        ?string $toolCallId,
        ?AgentTelemetrySeed $seed,
    ): ?OperationCorrelation {
        $executionId = match (true) {
            is_string($parentExecutionId) && $parentExecutionId !== '' => $parentExecutionId,
            default => null,
        };
        if ($executionId === null) {
            return null;
        }

        $parentOperationId = match (true) {
            is_string($toolCallId) && $toolCallId !== '' => $toolCallId,
            is_int($parentStepNumber) => self::stepId($executionId, $parentStepNumber),
            default => $executionId,
        };

        return self::childCorrelation(
            seed: $seed,
            rootOperationId: $executionId,
            parentOperationId: $parentOperationId,
        );
    }

    private static function childCorrelation(
        ?AgentTelemetrySeed $seed,
        string $rootOperationId,
        string $parentOperationId,
    ): OperationCorrelation {
        return OperationCorrelation::child(
            rootOperationId: $seed?->rootOperationId() ?? $rootOperationId,
            parentOperationId: $parentOperationId,
            sessionId: $seed?->sessionId(),
            userId: $seed?->userId(),
            conversationId: $seed?->conversationId(),
            requestId: $seed?->requestId(),
        );
    }

    private static function stepId(string $executionId, int $stepNumber): string
    {
        return "{$executionId}:step:{$stepNumber}";
    }
}
