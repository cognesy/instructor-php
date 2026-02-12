<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use DateTimeImmutable;

/**
 * Dispatched when a hook finishes execution.
 * Use to track hook processing time.
 */
final class HookExecuted extends AgentEvent
{
    public readonly DateTimeImmutable $completedAt;

    public function __construct(
        public readonly string $triggerType,
        public readonly ?string $hookName,
        public readonly DateTimeImmutable $startedAt,
    ) {
        $this->completedAt = new DateTimeImmutable();

        parent::__construct([
            'triggerType' => $this->triggerType,
            'hookName' => $this->hookName,
            'duration_ms' => $this->getDurationMs(),
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $name = $this->hookName ?? 'anonymous';

        return sprintf(
            'Hook "%s" executed on %s [%dms]',
            $name,
            $this->triggerType,
            $this->getDurationMs()
        );
    }

    public function getDurationMs(): int {
        $diff = $this->completedAt->getTimestamp() - $this->startedAt->getTimestamp();
        $microDiff = (int) ($this->completedAt->format('u')) - (int) ($this->startedAt->format('u'));
        return ($diff * 1000) + (int) ($microDiff / 1000);
    }
}
