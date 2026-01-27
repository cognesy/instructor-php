<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Events;

use DateTimeImmutable;

/**
 * Dispatched when a hook finishes execution.
 * Use to track hook processing time and outcomes.
 */
final class HookExecuted extends AgentEvent
{
    public readonly DateTimeImmutable $completedAt;

    public function __construct(
        public readonly string $hookType,
        public readonly string $tool,
        public readonly string $outcome,
        public readonly ?string $reason,
        public readonly DateTimeImmutable $startedAt,
    ) {
        $this->completedAt = new DateTimeImmutable();

        parent::__construct([
            'hookType' => $this->hookType,
            'tool' => $this->tool,
            'outcome' => $this->outcome,
            'reason' => $this->reason,
            'duration_ms' => $this->getDurationMs(),
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $reasonInfo = $this->reason ? " ({$this->reason})" : '';

        return sprintf(
            'Hook %s for tool "%s": %s%s [%dms]',
            $this->hookType,
            $this->tool,
            $this->outcome,
            $reasonInfo,
            $this->getDurationMs()
        );
    }

    private function getDurationMs(): int {
        $diff = $this->completedAt->getTimestamp() - $this->startedAt->getTimestamp();
        $microDiff = (int) ($this->completedAt->format('u')) - (int) ($this->startedAt->format('u'));
        return ($diff * 1000) + (int) ($microDiff / 1000);
    }
}
