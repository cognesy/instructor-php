<?php

declare(strict_types=1);

namespace Cognesy\Tell\Observability;

use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionFailed;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\AgentExecutionStopped;
use Cognesy\Agents\Events\AgentStateUpdated;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Agents\Events\AgentStepStarted;
use Cognesy\Agents\Events\ContinuationEvaluated;
use Cognesy\Agents\Events\HookExecuted;
use Cognesy\Agents\Events\InferenceRequestStarted;
use Cognesy\Agents\Events\InferenceResponseReceived;
use Cognesy\Agents\Events\StopSignalReceived;
use Cognesy\Agents\Events\TokenUsageReported;
use Cognesy\Agents\Events\ToolCallBlocked;
use Cognesy\Agents\Events\ToolCallCompleted;
use Cognesy\Agents\Events\ToolCallStarted;
use Cognesy\Events\Event;
use DateTimeImmutable;

/**
 * Converts framework observations into Tell's public, payload-free event contract.
 *
 * The envelope deliberately exposes counters and identifiers only. Prompt text,
 * tool arguments/results, provider bodies, exception text, and hook contexts stay
 * out of every public event sink by default.
 */
final class TellEventNormalizer
{
    public const string SCHEMA = 'tell.event.v1';

    private int $sequence = 0;

    private ?string $executionId = null;

    public function __construct(
        private readonly ?string $branch = null,
        private readonly ?string $session = null,
    ) {}

    /** @return array{schema: string, kind: string, sequence: int, executionId: string, branch: ?string, session: ?string, timestamp: string, metadata: array<string, int|float|string|bool|null>, terminal: ?string} */
    public function normalize(object $event): array
    {
        [$kind, $metadata, $terminal] = $this->map($event);
        $executionId = $this->executionId($event);

        return [
            'schema' => self::SCHEMA,
            'kind' => $kind,
            'sequence' => ++$this->sequence,
            'executionId' => $executionId,
            'branch' => $this->branch,
            'session' => $this->session,
            'timestamp' => $this->timestamp($event),
            'metadata' => $metadata,
            'terminal' => $terminal,
        ];
    }

    /** @param array<string, int|float|string|bool|null> $metadata */
    /** @return array{schema: string, kind: string, sequence: int, executionId: string, branch: ?string, session: ?string, timestamp: string, metadata: array<string, int|float|string|bool|null>, terminal: string} */
    public function terminal(string $status, array $metadata = []): array
    {
        return [
            'schema' => self::SCHEMA,
            'kind' => 'execution.completed',
            'sequence' => ++$this->sequence,
            'executionId' => $this->executionId ?? 'unknown',
            'branch' => $this->branch,
            'session' => $this->session,
            'timestamp' => (new DateTimeImmutable)->format(DATE_ATOM),
            'metadata' => ['status' => $status, ...$metadata],
            'terminal' => $status,
        ];
    }

    /** @param array<string, int|float|string|bool|null> $metadata */
    /** @return array{schema: string, kind: string, sequence: int, executionId: string, branch: ?string, session: ?string, timestamp: string, metadata: array<string, int|float|string|bool|null>, terminal: ?string} */
    public function direct(string $kind, array $metadata = []): array
    {
        return [
            'schema' => self::SCHEMA,
            'kind' => $kind,
            'sequence' => ++$this->sequence,
            'executionId' => 'direct',
            'branch' => $this->branch,
            'session' => $this->session,
            'timestamp' => (new DateTimeImmutable)->format(DATE_ATOM),
            'metadata' => $metadata,
            'terminal' => null,
        ];
    }

    /** @return array{0: string, 1: array<string, int|float|string|bool|null>, 2: ?string} */
    private function map(object $event): array
    {
        return match (true) {
            $event instanceof AgentExecutionStarted => ['execution.started', ['tools' => $event->availableTools], null],
            $event instanceof AgentStepStarted => ['step.started', [
                'step' => $event->stepNumber,
                'messages' => $event->messageCount,
                'tools' => $event->availableTools,
            ], null],
            $event instanceof InferenceRequestStarted => ['inference.started', [
                'step' => $event->stepNumber,
                'messages' => $event->messageCount,
                'model' => $event->model,
            ], null],
            $event instanceof InferenceResponseReceived => ['inference.completed', [
                'step' => $event->stepNumber,
                'inputTokens' => $event->usage->inputTokens,
                'outputTokens' => $event->usage->outputTokens,
                'finishReason' => $event->finishReason,
            ], null],
            $event instanceof ToolCallStarted => ['tool.started', [
                'step' => $event->stepNumber,
                'tool' => $event->tool,
            ], null],
            $event instanceof ToolCallCompleted => ['tool.completed', [
                'step' => $event->stepNumber,
                'tool' => $event->tool,
                'success' => $event->success,
                'durationMs' => $this->durationMs($event->startedAt, $event->completedAt),
            ], null],
            $event instanceof ToolCallBlocked => ['tool.blocked', [
                'step' => $event->stepNumber,
                'tool' => $event->tool,
            ], null],
            $event instanceof AgentStepCompleted => ['step.completed', [
                'step' => $event->stepNumber,
                'hasToolCalls' => $event->hasToolCalls,
                'errors' => $event->errorCount,
                'inputTokens' => $event->usage->inputTokens,
                'outputTokens' => $event->usage->outputTokens,
                'finishReason' => $event->finishReason?->value,
            ], null],
            $event instanceof TokenUsageReported => ['usage.reported', [
                'operation' => $event->operation,
                'inputTokens' => $event->usage->inputTokens,
                'outputTokens' => $event->usage->outputTokens,
            ], null],
            $event instanceof ContinuationEvaluated => ['continuation.evaluated', [
                'step' => $event->stepNumber,
                'stopping' => $event->shouldStop(),
                'reason' => $event->stopReason()?->value,
            ], null],
            $event instanceof StopSignalReceived => ['stop.requested', [
                'reason' => $event->reason->value,
            ], null],
            $event instanceof HookExecuted => ['hook.executed', [
                'trigger' => $event->triggerType,
                'durationMs' => $event->getDurationMs(),
            ], null],
            $event instanceof AgentStateUpdated => ['state.updated', [
                'status' => $event->status->value,
                'steps' => $event->stepCount,
            ], null],
            $event instanceof AgentExecutionStopped => ['execution.stopped', [
                'reason' => $event->stopReason->value,
                'steps' => $event->totalSteps,
            ], null],
            $event instanceof AgentExecutionFailed => ['execution.failed', [
                'steps' => $event->stepsCompleted,
                'inputTokens' => $event->totalUsage->inputTokens,
                'outputTokens' => $event->totalUsage->outputTokens,
            ], null],
            $event instanceof AgentExecutionCompleted => ['execution.completed', [
                'status' => $event->status->value,
                'steps' => $event->totalSteps,
                'inputTokens' => $event->totalUsage->inputTokens,
                'outputTokens' => $event->totalUsage->outputTokens,
            ], $event->status->value],
            default => ['unknown', [], null],
        };
    }

    private function executionId(object $event): string
    {
        if (property_exists($event, 'executionId') && is_string($event->executionId) && $event->executionId !== '') {
            return $this->executionId = $event->executionId;
        }

        return $this->executionId ?? 'unknown';
    }

    private function timestamp(object $event): string
    {
        return match (true) {
            $event instanceof Event => $event->createdAt->format(DATE_ATOM),
            default => (new DateTimeImmutable)->format(DATE_ATOM),
        };
    }

    private function durationMs(DateTimeImmutable $started, DateTimeImmutable $completed): int
    {
        return max(0, (int) round(((float) $completed->format('U.u') - (float) $started->format('U.u')) * 1000));
    }
}
