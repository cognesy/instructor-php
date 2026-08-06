<?php declare(strict_types=1);

namespace Cognesy\Agents\Telemetry;

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
use Cognesy\Metrics\Data\Counter;
use Cognesy\Metrics\Data\Gauge;
use Cognesy\Metrics\Data\Histogram;
use Cognesy\Metrics\Data\Timer;
use Cognesy\Telemetry\Application\Projector\CanProjectTelemetry;
use Cognesy\Telemetry\Application\Projector\Support\EventData;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelopeAttributes;
use Cognesy\Telemetry\Domain\Metric\MetricNames;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;
use Cognesy\Telemetry\Domain\Value\AttributeBag;
use Override;

final readonly class AgentsTelemetryProjector implements CanProjectTelemetry
{
    public function __construct(
        private Telemetry $telemetry,
    ) {}

    #[Override]
    public function project(object $event): void {
        match (true) {
            $event instanceof AgentExecutionStarted => $this->onExecutionStarted($event),
            $event instanceof AgentStepStarted => $this->onStepStarted($event),
            $event instanceof AgentStepCompleted => $this->onStepCompleted($event),
            $event instanceof AgentExecutionCompleted => $this->onExecutionCompleted($event),
            $event instanceof AgentExecutionStopped => $this->onExecutionStopped($event),
            $event instanceof AgentExecutionFailed => $this->onExecutionFailed($event),
            $event instanceof ToolCallStarted => $this->onToolCallStarted($event),
            $event instanceof ToolCallCompleted => $this->onToolCallCompleted($event),
            $event instanceof ToolCallBlocked => $this->onToolCallBlocked($event),
            $event instanceof SubagentSpawning => $this->onSubagentSpawning($event),
            $event instanceof SubagentCompleted => $this->onSubagentCompleted($event),
            $event instanceof TokenUsageReported => $this->onTokenUsageReported($event),
            default => null,
        };
    }

    private function onExecutionStarted(AgentExecutionStarted $event): void {
        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);
        if ($envelope !== null) {
            $this->openEnvelope($envelope, $this->attributes([
                'agent.id' => $event->agentId,
                'agent.parent_id' => $event->parentAgentId,
                'agent.message_count' => $event->messageCount,
                'agent.available_tools' => $event->availableTools,
            ]));
            return;
        }

        if ($event->executionId === '' || $this->telemetry->spanReference($event->executionId) !== null) {
            return;
        }

        $this->telemetry->openRoot(
            key: $event->executionId,
            name: 'agent.execute',
            attributes: $this->attributes([
                'agent.id' => $event->agentId,
                'agent.parent_id' => $event->parentAgentId,
                'agent.message_count' => $event->messageCount,
                'agent.available_tools' => $event->availableTools,
            ]),
        );
    }

    private function onStepStarted(AgentStepStarted $event): void {
        // Point-in-time state, not a total: how large the context is right now.
        $this->telemetry->metric(Gauge::create(
            'agent.context.message_count',
            $event->messageCount,
            $this->metricTags(['agent.is_subagent' => $this->isSubagent($event->parentAgentId)]),
        ));

        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);
        if ($envelope !== null) {
            $this->openEnvelope($envelope, $this->attributes([
                'agent.id' => $event->agentId,
                'agent.step_number' => $event->stepNumber,
                'agent.message_count' => $event->messageCount,
            ]));
            return;
        }

        if ($event->executionId === '') {
            return;
        }

        $this->telemetry->openChild(
            key: $this->stepKey($event->executionId, $event->stepNumber),
            parentKey: $event->executionId,
            name: 'agent.step',
            attributes: $this->attributes([
                'agent.id' => $event->agentId,
                'agent.step_number' => $event->stepNumber,
                'agent.message_count' => $event->messageCount,
            ]),
        );
    }

    private function onStepCompleted(AgentStepCompleted $event): void {
        $stepTags = $this->metricTags([
            'agent.has_tool_calls' => $event->hasToolCalls,
            'inference.finish_reason' => $event->finishReason?->value,
            'agent.is_subagent' => $this->isSubagent($event->parentAgentId),
        ]);
        $this->telemetry->metric(Counter::create('agent.step.count', 1, $stepTags));
        // Timer rejects negative durations by throwing. A clock that went backwards must not
        // take down the step it is only observing, so skip the Timer instead.
        if ($event->durationMs >= 0) {
            $this->telemetry->metric(Timer::create('agent.step.duration', $event->durationMs, $stepTags));
        }

        $data = EventData::of($event);
        $attributes = $this->attributes([
            'agent.has_tool_calls' => $event->hasToolCalls,
            'agent.error_count' => $event->errorCount,
            'inference.finish_reason' => $event->finishReason?->value,
            'inference.duration_ms' => $event->durationMs,
            'inference.tokens.total' => $event->usage->total(),
        ]);

        $envelope = EventData::telemetry($data);
        if ($envelope !== null) {
            $this->completeEnvelope($envelope, $attributes);
            return;
        }

        $this->telemetry->complete(
            $this->stepKey($event->executionId, $event->stepNumber),
            $attributes,
        );
    }

    private function onExecutionCompleted(AgentExecutionCompleted $event): void {
        $this->emitExecutionMetrics('success', $event->totalSteps, [
            'agent.status' => $event->status->value,
            'agent.is_subagent' => $this->isSubagent($event->parentAgentId),
        ]);

        $attributes = $this->attributes([
            'agent.status' => $event->status->value,
            'agent.total_steps' => $event->totalSteps,
            'inference.tokens.total' => $event->totalUsage->total(),
            'error.message' => $event->errors,
        ]);
        $envelope = EventData::telemetry(EventData::of($event));
        if ($envelope !== null) {
            $this->completeEnvelope($envelope, $attributes);
            return;
        }

        $this->telemetry->complete($event->executionId, $attributes);
    }

    private function onExecutionStopped(AgentExecutionStopped $event): void {
        // Always fires before executionCompleted — do not close the span here.
        // Only log when the execution was genuinely interrupted (limit hit, user cancel, error, etc.).
        if (!$event->stopReason->wasForceStopped()) {
            return;
        }

        if ($this->telemetry->spanReference($event->executionId) === null) {
            return;
        }

        $this->telemetry->log($event->executionId, 'agent.execution.interrupted', $this->attributes([
            'agent.stop_reason' => $event->stopReason->value,
            'agent.stop_message' => $event->stopMessage !== '' ? $event->stopMessage : null,
            'agent.stop_source' => $event->source,
            'agent.total_steps' => $event->totalSteps,
            'agent.stop_context' => $event->stopContext !== [] ? json_encode($event->stopContext) : null,
        ]));
    }

    private function onExecutionFailed(AgentExecutionFailed $event): void {
        $this->emitExecutionMetrics('failure', $event->stepsCompleted, [
            'agent.status' => $event->status->value,
            'agent.is_subagent' => $this->isSubagent($event->parentAgentId),
            'error.type' => $event->exception::class,
        ]);

        $attributes = $this->attributes([
            'agent.status' => $event->status->value,
            'agent.total_steps' => $event->stepsCompleted,
            'inference.tokens.total' => $event->totalUsage->total(),
            'error.message' => $event->exception->getMessage(),
            'error.type' => $event->exception::class,
        ]);
        $envelope = EventData::telemetry(EventData::of($event));
        if ($envelope !== null) {
            $this->failEnvelope($envelope, $attributes);
            return;
        }

        $this->telemetry->fail($event->executionId, $attributes);
    }

    private function onToolCallStarted(ToolCallStarted $event): void {
        $envelope = EventData::telemetry(EventData::of($event));
        if ($envelope !== null) {
            $this->openEnvelope($envelope, $this->attributes([
                'agent.tool' => $event->tool,
            ]));
            return;
        }

        $stepKey = $this->stepKey($event->executionId, $event->stepNumber);
        if ($this->telemetry->spanReference($stepKey) === null) {
            return;
        }

        $toolCallKey = $event->toolCallId !== '' ? $event->toolCallId : null;
        if ($toolCallKey === null) {
            return;
        }

        $this->telemetry->openChild(
            key: $toolCallKey,
            parentKey: $stepKey,
            name: 'agent.tool_call',
            attributes: $this->attributes([
                'agent.tool' => $event->tool,
            ]),
        );
    }

    private function onToolCallCompleted(ToolCallCompleted $event): void {
        $toolTags = $this->metricTags([
            'agent.tool' => $event->tool,
            'agent.outcome' => $event->success ? 'success' : 'error',
        ]);
        $this->telemetry->metric(Counter::create('agent.tool_call.count', 1, $toolTags));
        $toolDurationMs = EventData::int(EventData::of($event), 'duration_ms');
        if ($toolDurationMs !== null && $toolDurationMs >= 0) {
            $this->telemetry->metric(Timer::create('agent.tool_call.duration', $toolDurationMs, $toolTags));
        }

        $attributes = $this->attributes([
            'agent.tool' => $event->tool,
            'agent.tool_success' => $event->success,
            'error.message' => $event->error,
            'agent.tool_duration_ms' => EventData::int(EventData::of($event), 'duration_ms'),
        ]);
        $envelope = EventData::telemetry(EventData::of($event));
        if ($envelope !== null) {
            if ($event->success) {
                $this->completeEnvelope($envelope, $attributes);
            } else {
                $this->failEnvelope($envelope, $attributes);
            }
            return;
        }

        $toolCallKey = $event->toolCallId !== '' ? $event->toolCallId : null;
        if ($toolCallKey === null || $this->telemetry->spanReference($toolCallKey) === null) {
            return;
        }

        if ($event->success) {
            $this->telemetry->complete($toolCallKey, $attributes);
        } else {
            $this->telemetry->fail($toolCallKey, $attributes);
        }
    }

    private function onToolCallBlocked(ToolCallBlocked $event): void {
        // Blocked calls share agent.tool_call.count so the denominator stays whole:
        // success + error + blocked is every call the agent attempted.
        $this->telemetry->metric(Counter::create('agent.tool_call.count', 1, $this->metricTags([
            'agent.tool' => $event->tool,
            'agent.outcome' => 'blocked',
        ])));

        $attributes = $this->attributes([
            'agent.tool' => $event->tool,
            'error.message' => $event->reason,
            'agent.hook' => $event->hookName,
        ]);
        $envelope = EventData::telemetry(EventData::of($event));
        if ($envelope !== null) {
            $this->logEnvelope(
                envelope: $envelope,
                attributes: $attributes,
                status: ObservationStatus::Error,
            );
            return;
        }

        $stepKey = $this->stepKey($event->executionId, $event->stepNumber);
        if ($this->telemetry->spanReference($stepKey) === null) {
            return;
        }

        $this->telemetry->log(
            key: $stepKey,
            name: 'agent.tool_call.blocked',
            attributes: $attributes,
            status: ObservationStatus::Error,
        );
    }

    private function onSubagentSpawning(SubagentSpawning $event): void {
        // Current nesting level — goes up and back down, so a gauge rather than a counter.
        $this->telemetry->metric(Gauge::create(
            'agent.subagent.depth',
            $event->depth,
            $this->metricTags(['agent.subagent' => $event->subagentName]),
        ));

        $attributes = $this->attributes([
            'agent.parent_id' => $event->parentAgentId,
            'agent.subagent' => $event->subagentName,
            'agent.subagent.depth' => $event->depth,
            'agent.subagent.max_depth' => $event->maxDepth,
        ]);
        $envelope = EventData::telemetry(EventData::of($event));
        if ($envelope !== null) {
            $this->logEnvelope($envelope, $attributes);
            return;
        }

        $parentKey = $this->subagentParentKey($event->parentExecutionId, $event->parentStepNumber, $event->toolCallId);
        if ($parentKey === null || $this->telemetry->spanReference($parentKey) === null) {
            return;
        }

        $this->telemetry->log($parentKey, 'agent.subagent.spawning', $attributes);
    }

    private function onSubagentCompleted(SubagentCompleted $event): void {
        $this->telemetry->metric(Counter::create('agent.subagent.count', 1, $this->metricTags([
            'agent.subagent' => $event->subagentName,
            'agent.subagent.status' => $event->status->value,
        ])));

        $attributes = $this->attributes([
            'agent.parent_id' => $event->parentAgentId,
            'agent.subagent' => $event->subagentName,
            'agent.subagent_id' => $event->subagentId,
            'agent.subagent.status' => $event->status->value,
            'agent.subagent.steps' => $event->steps,
            'inference.tokens.total' => $event->usage?->total(),
            'inference.duration_ms' => EventData::int(EventData::of($event), 'duration_ms'),
        ]);
        $envelope = EventData::telemetry(EventData::of($event));
        if ($envelope !== null) {
            $this->logEnvelope($envelope, $attributes);
            return;
        }

        $parentKey = $this->subagentParentKey($event->parentExecutionId, $event->parentStepNumber, $event->toolCallId);
        if ($parentKey === null || $this->telemetry->spanReference($parentKey) === null) {
            return;
        }

        $this->telemetry->log($parentKey, 'agent.subagent.completed', $attributes);
    }

    private function onTokenUsageReported(TokenUsageReported $event): void {
        $attributes = $this->attributes([
            'agent.id' => $event->agentId,
            MetricNames::TAG_EXECUTION_ID => $event->executionId,
            'agent.operation' => $event->operation,
            'agent.parent_id' => $event->parentAgentId,
        ]);
        $envelope = EventData::telemetry(EventData::of($event));
        if ($envelope !== null) {
            $attributes = $attributes->merge($this->envelopeAttributes($envelope));
        }

        $this->telemetry->metric(Histogram::create(
            name: MetricNames::TOKEN_USAGE_TOTAL,
            value: $event->usage->total(),
            tags: $attributes->toArray(),
        ));
    }

    /**
     * Terminal outcome of one agent execution: how many finished, and how many steps it took.
     *
     * @param array<string, string|int|float|bool|null> $tags
     */
    private function emitExecutionMetrics(string $outcome, int $steps, array $tags): void {
        $resolved = $this->metricTags([...$tags, 'agent.outcome' => $outcome]);

        $this->telemetry->metric(Counter::create('agent.execution.count', 1, $resolved));
        $this->telemetry->metric(Histogram::create('agent.execution.steps', $steps, $resolved));
    }

    /**
     * Whether this execution runs under a parent agent. Stands in for `agent.parent_id` on
     * metrics: the id itself is per-run, the boolean is a dimension worth grouping by.
     */
    private function isSubagent(?string $parentAgentId): bool {
        return $parentAgentId !== null && $parentAgentId !== '';
    }

    /**
     * Metric tags are aggregation dimensions, not span attributes: every key here must be
     * low-cardinality. `agent.id` and `executionId` are per-run identifiers — tagging a
     * metric with one gives the metrics backend a fresh time series per execution, which is
     * why the metrics routed through here carry `agent.is_subagent` and tool/subagent *names*
     * instead.
     *
     * `inference.client.token.usage.total` in onTokenUsageReported() does NOT go through here,
     * and is the one deliberate exception: it is a correlation-carrying metric rather than an
     * aggregation-friendly one. LangfusePayloadMapper matches it back to its span by
     * `inference.execution.id`, which already makes it one series per run, so the `agent.id`
     * it also carries costs nothing further and stays useful to consumers grouping token spend
     * by agent. Do not "clean up" its tags on the strength of the rule above.
     *
     * @param array<string, string|int|float|bool|null> $items
     * @return array<string, string|int|float|bool>
     */
    private function metricTags(array $items): array {
        return array_filter($items, static fn (string|int|float|bool|null $value): bool => $value !== null);
    }

    /** @param array<string, scalar|array<array-key, scalar>|null> $items */
    private function attributes(array $items): AttributeBag {
        return AttributeBag::fromArray(array_filter($items, static fn (mixed $value): bool => $value !== null));
    }

    private function stepKey(string $executionId, int $stepNumber): string {
        return "{$executionId}:step:{$stepNumber}";
    }

    private function subagentParentKey(?string $executionId, ?int $stepNumber, ?string $toolCallId): ?string {
        return match (true) {
            is_string($toolCallId) && $toolCallId !== '' => $toolCallId,
            is_string($executionId) && $executionId !== '' && is_int($stepNumber) => $this->stepKey($executionId, $stepNumber),
            is_string($executionId) && $executionId !== '' => $executionId,
            default => null,
        };
    }

    private function openEnvelope(TelemetryEnvelope $envelope, AttributeBag $attributes): void {
        $operation = $envelope->operation();
        if ($this->telemetry->spanReference($operation->id()) !== null) {
            return;
        }

        $correlation = $envelope->correlation();
        $parentKey = $correlation->parentOperationId() ?? $correlation->rootOperationId();
        $rootKey = $correlation->rootOperationId();
        $resolvedParent = match (true) {
            $this->telemetry->spanReference($parentKey) !== null => $parentKey,
            $parentKey !== $rootKey && $this->telemetry->spanReference($rootKey) !== null => $rootKey,
            default => null,
        };
        match ($operation->kind()) {
            OperationKind::RootSpan => $this->telemetry->openRoot(
                key: $operation->id(),
                name: $operation->name(),
                context: $envelope->trace(),
                attributes: $this->envelopeAttributes($envelope)->merge($attributes),
            ),
            OperationKind::Span => match ($resolvedParent) {
                null => $this->telemetry->openRoot(
                    key: $operation->id(),
                    name: $operation->name(),
                    context: $envelope->trace(),
                    attributes: $this->envelopeAttributes($envelope)->merge($attributes),
                ),
                default => $this->telemetry->openChild(
                    key: $operation->id(),
                    parentKey: $resolvedParent,
                    name: $operation->name(),
                    attributes: $this->envelopeAttributes($envelope)->merge($attributes),
                ),
            },
            OperationKind::Event => match ($resolvedParent) {
                null => null,
                default => $this->telemetry->log(
                    key: $resolvedParent,
                    name: $operation->name(),
                    attributes: $this->envelopeAttributes($envelope)->merge($attributes),
                ),
            },
            default => null,
        };
    }

    private function completeEnvelope(TelemetryEnvelope $envelope, AttributeBag $attributes): void {
        $this->telemetry->complete(
            $envelope->operation()->id(),
            $this->envelopeAttributes($envelope)->merge($attributes),
        );
    }

    private function failEnvelope(TelemetryEnvelope $envelope, AttributeBag $attributes): void {
        $this->telemetry->fail(
            $envelope->operation()->id(),
            $this->envelopeAttributes($envelope)->merge($attributes),
        );
    }

    private function logEnvelope(
        TelemetryEnvelope $envelope,
        AttributeBag $attributes,
        ObservationStatus $status = ObservationStatus::Ok,
    ): void {
        $parentKey = $envelope->correlation()->parentOperationId() ?? $envelope->correlation()->rootOperationId();
        if ($this->telemetry->spanReference($parentKey) === null) {
            return;
        }

        $this->telemetry->log(
            key: $parentKey,
            name: $envelope->operation()->name(),
            attributes: $this->envelopeAttributes($envelope)->merge($attributes),
            status: $status,
        );
    }

    private function envelopeAttributes(TelemetryEnvelope $envelope): AttributeBag {
        return TelemetryEnvelopeAttributes::fromEnvelope($envelope);
    }
}
