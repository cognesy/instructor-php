<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Events;

use DateTimeImmutable;

/**
 * Dispatched when a tool call completes execution.
 * Use for tracking tool execution results, timing, and success/failure status.
 */
final class ToolCallCompleted extends AgentEvent
{
    public function __construct(
        public readonly string $toolName,
        public readonly bool $isSuccess,
        public readonly ?string $errorMessage,
        public readonly DateTimeImmutable $startedAt,
        public readonly DateTimeImmutable $endedAt,
    ) {
        parent::__construct([
            'tool' => $this->toolName,
            'success' => $this->isSuccess,
            'error' => $this->errorMessage,
            'started' => $this->startedAt->format(DATE_ATOM),
            'ended' => $this->endedAt->format(DATE_ATOM),
            'duration_ms' => $this->getDurationMs(),
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $status = $this->isSuccess ? 'succeeded' : 'failed';
        $error = $this->errorMessage ? " ({$this->errorMessage})" : '';

        return sprintf(
            'Tool call %s: %s [%dms]%s',
            $status,
            $this->toolName,
            $this->getDurationMs(),
            $error
        );
    }

    private function getDurationMs(): int {
        $diff = $this->endedAt->getTimestamp() - $this->startedAt->getTimestamp();
        $microDiff = (int) ($this->endedAt->format('u')) - (int) ($this->startedAt->format('u'));
        return ($diff * 1000) + (int) ($microDiff / 1000);
    }
}

