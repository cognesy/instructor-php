<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Broadcasting;

use Cognesy\Addons\Agent\Events\AgentStepCompleted;
use Cognesy\Addons\Agent\Events\AgentStepStarted;
use Cognesy\Addons\Agent\Events\ContinuationEvaluated;
use Cognesy\Addons\Agent\Events\ToolCallCompleted;
use Cognesy\Addons\Agent\Events\ToolCallStarted;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\StopReason;
use Cognesy\Events\Event;
use Cognesy\Polyglot\Inference\Events\StreamEventReceived;
use DateTimeImmutable;

/**
 * Adapts agent events to a broadcast-friendly envelope format for real-time UIs.
 *
 * Usage with wiretap (recommended):
 *   $adapter = new ReverbAgentEventAdapter($broadcaster, $sessionId, $executionId);
 *   $agent->wiretap($adapter->wiretap());
 *
 * Usage with config preset:
 *   $adapter = new ReverbAgentEventAdapter(
 *       $broadcaster, $sessionId, $executionId,
 *       BroadcastConfig::debug()
 *   );
 *   $agent->wiretap($adapter->wiretap());
 *
 * Legacy usage (still supported):
 *   $agent->onEvent(AgentStepStarted::class, [$adapter, 'onAgentStepStarted']);
 */
final class ReverbAgentEventAdapter
{
    private int $chunkCounter = 0;
    private string $currentStatus = 'idle';

    public function __construct(
        private readonly CanBroadcastAgentEvents $broadcaster,
        private readonly string $sessionId,
        private readonly string $executionId,
        private readonly BroadcastConfig $config = new BroadcastConfig(),
    ) {}

    /**
     * Returns a wiretap callable that handles all supported events.
     *
     * Usage:
     *   $agent->wiretap($adapter->wiretap());
     *
     * @return callable(Event): void
     */
    public function wiretap(): callable
    {
        return function (Event $event): void {
            match (true) {
                $event instanceof StreamEventReceived => $this->onStreamChunk($event),
                $event instanceof AgentStepStarted => $this->onAgentStepStarted($event),
                $event instanceof AgentStepCompleted => $this->onAgentStepCompleted($event),
                $event instanceof ToolCallStarted => $this->onToolCallStarted($event),
                $event instanceof ToolCallCompleted => $this->onToolCallCompleted($event),
                $event instanceof ContinuationEvaluated => $this->onContinuationEvaluated($event),
                default => null,
            };
        };
    }

    /**
     * Handle streaming text chunks for real-time chat display.
     */
    public function onStreamChunk(StreamEventReceived $event): void
    {
        if (!$this->config->includeStreamChunks) {
            return;
        }

        $content = $event->content;
        if ($content === '') {
            return;
        }

        $this->emit('agent.stream.chunk', [
            'content' => $content,
            'is_complete' => false,
            'chunk_index' => $this->chunkCounter++,
        ]);
    }

    public function onAgentStepStarted(AgentStepStarted $event): void
    {
        if ($this->config->autoStatusTracking) {
            $this->transitionStatus('processing');
        }

        $this->emit('agent.step.started', [
            'step_number' => $event->stepNumber,
            'message_count' => $event->messageCount ?? 0,
            'available_tools' => $event->availableTools ?? 0,
        ]);
    }

    public function onAgentStepCompleted(AgentStepCompleted $event): void
    {
        $payload = [
            'step_number' => $event->stepNumber,
            'has_tool_calls' => $event->hasToolCalls,
            'errors' => $event->errorCount,
            'finish_reason' => $event->finishReason?->value,
            'usage' => $event->usage->toArray(),
            'duration_ms' => $event->durationMs,
        ];

        $this->emit('agent.step.completed', $payload);
    }

    public function onToolCallStarted(ToolCallStarted $event): void
    {
        $args = is_array($event->args) ? $event->args : [];

        $payload = [
            'tool_name' => $event->tool,
            'tool_call_id' => null,
        ];

        if ($this->config->includeToolArgs) {
            $payload['args'] = $this->truncateArgs($args, $this->config->maxArgLength);
        } else {
            $payload['args_summary'] = $this->summarizeArgs($args);
        }

        $this->emit('agent.tool.started', $payload);
    }

    public function onToolCallCompleted(ToolCallCompleted $event): void
    {
        $this->emit('agent.tool.completed', [
            'tool_name' => $event->tool,
            'tool_call_id' => null,
            'success' => $event->success,
            'error' => $event->error,
            'duration_ms' => $this->durationMs($event->startedAt, $event->endedAt),
            'result_summary' => null,
        ]);
    }

    public function onContinuationEvaluated(ContinuationEvaluated $event): void
    {
        // Auto-transition status on stop
        if ($this->config->autoStatusTracking && !$event->outcome->shouldContinue) {
            $finalStatus = match ($event->outcome->stopReason) {
                StopReason::Completed => 'completed',
                StopReason::ErrorForbade => 'failed',
                StopReason::UserRequested => 'cancelled',
                default => 'stopped',
            };
            $this->transitionStatus($finalStatus);

            // Reset chunk counter for next execution
            $this->chunkCounter = 0;
        }

        if (!$this->config->includeContinuationTrace) {
            return;
        }

        $this->emit('agent.continuation', [
            'step_number' => $event->stepNumber,
            'should_continue' => $event->outcome->shouldContinue,
            'stop_reason' => $event->outcome->stopReason->value,
            'resolved_by' => $event->outcome->resolvedBy,
            'evaluations' => array_map(
                static fn(ContinuationEvaluation $evaluation): array => [
                    'criterion' => basename(str_replace('\\', '/', $evaluation->criterionClass)),
                    'decision' => $evaluation->decision->value,
                    'reason' => $evaluation->reason,
                ],
                $event->outcome->evaluations,
            ),
        ]);
    }

    /**
     * Manually emit a status change. Use this for custom status transitions
     * when auto-tracking is disabled or for additional status events.
     */
    public function onAgentStatusChanged(string $status, ?string $error = null, ?string $lastResponse = null): void
    {
        $this->emit('agent.status', [
            'status' => $status,
            'previous_status' => $this->currentStatus,
            'error_message' => $error,
            'last_response' => $lastResponse,
        ]);
        $this->currentStatus = $status;
    }

    /**
     * Reset adapter state for a new execution within the same session.
     */
    public function reset(): void
    {
        $this->chunkCounter = 0;
        $this->currentStatus = 'idle';
    }

    private function transitionStatus(string $newStatus): void
    {
        if ($this->currentStatus === $newStatus) {
            return;
        }

        $previousStatus = $this->currentStatus;
        $this->currentStatus = $newStatus;

        $this->emit('agent.status', [
            'status' => $newStatus,
            'previous_status' => $previousStatus,
        ]);
    }

    private function emit(string $type, array $payload): void
    {
        $this->broadcaster->broadcast(
            channel: "agent.{$this->sessionId}",
            envelope: [
                'type' => $type,
                'session_id' => $this->sessionId,
                'execution_id' => $this->executionId,
                'timestamp' => (new DateTimeImmutable())->format('Y-m-d\TH:i:s.v\Z'),
                'payload' => $payload,
            ],
        );
    }

    private function summarizeArgs(array $args): string
    {
        $parts = [];
        foreach (array_slice($args, 0, 3, true) as $key => $value) {
            $valueStr = is_string($value) ? "'{$value}'" : json_encode($value);
            if ($valueStr === false) {
                $valueStr = 'null';
            }
            if (strlen($valueStr) > 30) {
                $valueStr = substr($valueStr, 0, 27) . '...';
            }
            $parts[] = "{$key}: {$valueStr}";
        }
        return implode(', ', $parts);
    }

    private function truncateArgs(array $args, int $maxLength): array
    {
        $result = [];
        foreach ($args as $key => $value) {
            if (is_string($value) && strlen($value) > $maxLength) {
                $result[$key] = substr($value, 0, $maxLength) . '...';
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    private function durationMs(DateTimeImmutable $startedAt, DateTimeImmutable $endedAt): int
    {
        $diff = $endedAt->getTimestamp() - $startedAt->getTimestamp();
        $microDiff = (int) ($endedAt->format('u')) - (int) ($startedAt->format('u'));
        return ($diff * 1000) + (int) ($microDiff / 1000);
    }
}
