<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Events;

use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;

/**
 * Dispatched when a subagent completes execution.
 * Use to track subagent results, token usage, and completion status.
 */
final class SubagentCompleted extends AgentEvent
{
    public readonly DateTimeImmutable $completedAt;

    public function __construct(
        public readonly string $parentAgentId,
        public readonly string $subagentId,
        public readonly string $subagentName,
        public readonly AgentStatus $status,
        public readonly int $steps,
        public readonly ?Usage $usage,
        public readonly DateTimeImmutable $startedAt,
    ) {
        $this->completedAt = new DateTimeImmutable();

        parent::__construct([
            'parentAgentId' => $this->parentAgentId,
            'subagentId' => $this->subagentId,
            'subagent' => $this->subagentName,
            'status' => $this->status->value,
            'steps' => $this->steps,
            'tokens' => $this->usage?->total(),
            'duration_ms' => $this->getDurationMs(),
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $tokens = $this->usage?->total() ?? 0;

        return sprintf(
            'Agent [%s] subagent "%s" completed [status=%s, steps=%d, tokens=%d, duration=%dms]',
            substr($this->parentAgentId, 0, 8),
            $this->subagentName,
            $this->status->value,
            $this->steps,
            $tokens,
            $this->getDurationMs()
        );
    }

    private function getDurationMs(): int {
        $diff = $this->completedAt->getTimestamp() - $this->startedAt->getTimestamp();
        $microDiff = (int) ($this->completedAt->format('u')) - (int) ($this->startedAt->format('u'));
        return ($diff * 1000) + (int) ($microDiff / 1000);
    }
}
