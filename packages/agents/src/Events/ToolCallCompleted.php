<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use DateTimeImmutable;
use Psr\Log\LogLevel;

/**
 * Dispatched when a tool call completes execution.
 * Use for tracking tool execution results, timing, and success/failure status.
 */
final class ToolCallCompleted extends AgentEvent
{
    public string $logLevel = LogLevel::INFO;

    public function __construct(
        public readonly string $agentId,
        public readonly string $executionId,
        public readonly ?string $parentAgentId,
        public readonly int $stepNumber,
        public readonly string $tool,
        public readonly bool $success,
        public readonly ?string $error,
        public readonly DateTimeImmutable $startedAt,
        public readonly DateTimeImmutable $completedAt,
        public readonly mixed $result = null,
        public readonly string $toolCallId = '',
    ) {
        parent::__construct([
            'agentId' => $this->agentId,
            'executionId' => $this->executionId,
            'parentAgentId' => $this->parentAgentId,
            'step' => $this->stepNumber,
            'tool' => $this->tool,
            'success' => $this->success,
            'error' => $this->error,
            'result' => $this->result,
            'started' => $this->startedAt->format(DateTimeImmutable::ATOM),
            'completed' => $this->completedAt->format(DateTimeImmutable::ATOM),
            'duration_ms' => $this->getDurationMs(),
            'toolCallId' => $this->toolCallId,
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $status = $this->success ? 'succeeded' : 'failed';
        $error = $this->error ? " ({$this->error})" : '';

        return sprintf(
            'Tool call %s: %s [%dms]%s',
            $status,
            $this->tool,
            $this->getDurationMs(),
            $error
        );
    }

    private function getDurationMs(): int {
        $diff = $this->completedAt->getTimestamp() - $this->startedAt->getTimestamp();
        $microDiff = (int) ($this->completedAt->format('u')) - (int) ($this->startedAt->format('u'));
        return ($diff * 1000) + (int) ($microDiff / 1000);
    }
}
