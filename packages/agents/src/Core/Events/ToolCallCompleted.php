<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Events;

use DateTimeImmutable;

/**
 * Dispatched when a tool call completes execution.
 * Use for tracking tool execution results, timing, and success/failure status.
 */
final class ToolCallCompleted extends AgentEvent
{
    public function __construct(
        public readonly string $tool,
        public readonly bool $success,
        public readonly ?string $error,
        public readonly DateTimeImmutable $startedAt,
        public readonly DateTimeImmutable $completedAt,
    ) {
        parent::__construct([
            'tool' => $this->tool,
            'success' => $this->success,
            'error' => $this->error,
            'started' => $this->startedAt->format(DateTimeImmutable::ATOM),
            'completed' => $this->completedAt->format(DateTimeImmutable::ATOM),
            'duration_ms' => $this->getDurationMs(),
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
