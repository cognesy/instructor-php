<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Broadcasting;

use Cognesy\Addons\Agent\Events\AgentStepCompleted;
use Cognesy\Addons\Agent\Events\AgentStepStarted;
use Cognesy\Addons\Agent\Events\ContinuationEvaluated;
use Cognesy\Addons\Agent\Events\ToolCallCompleted;
use Cognesy\Addons\Agent\Events\ToolCallStarted;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use DateTimeImmutable;

final readonly class ReverbAgentEventAdapter
{
    public function __construct(
        private CanBroadcastAgentEvents $broadcaster,
        private string $sessionId,
        private string $executionId,
        private bool $includeContinuationTrace = false,
    ) {}

    public function onAgentStepStarted(AgentStepStarted $event): void {
        $this->emit('agent.step.started', [
            'step_number' => $event->stepNumber,
            'message_count' => $event->messageCount ?? 0,
            'available_tools' => $event->availableTools ?? 0,
        ]);
    }

    public function onAgentStepCompleted(AgentStepCompleted $event): void {
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

    public function onToolCallStarted(ToolCallStarted $event): void {
        $this->emit('agent.tool.started', [
            'tool_name' => $event->tool,
            'tool_call_id' => null,
            'args_summary' => $this->summarizeArgs(is_array($event->args) ? $event->args : []),
        ]);
    }

    public function onToolCallCompleted(ToolCallCompleted $event): void {
        $this->emit('agent.tool.completed', [
            'tool_name' => $event->tool,
            'tool_call_id' => null,
            'success' => $event->success,
            'error' => $event->error,
            'duration_ms' => $this->durationMs($event->startedAt, $event->endedAt),
            'result_summary' => null,
        ]);
    }

    public function onContinuationEvaluated(ContinuationEvaluated $event): void {
        if (!$this->includeContinuationTrace) {
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

    public function onAgentStatusChanged(string $status, ?string $error = null, ?string $lastResponse = null): void {
        $this->emit('agent.status', [
            'status' => $status,
            'error_message' => $error,
            'last_response' => $lastResponse,
        ]);
    }

    private function emit(string $type, array $payload): void {
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

    private function summarizeArgs(array $args): string {
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

    private function durationMs(DateTimeImmutable $startedAt, DateTimeImmutable $endedAt): int {
        $diff = $endedAt->getTimestamp() - $startedAt->getTimestamp();
        $microDiff = (int) ($endedAt->format('u')) - (int) ($startedAt->format('u'));
        return ($diff * 1000) + (int) ($microDiff / 1000);
    }
}
