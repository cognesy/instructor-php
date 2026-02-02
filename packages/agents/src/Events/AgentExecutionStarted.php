<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use DateTimeImmutable;

/**
 * Dispatched when an agent begins execution.
 */
final class AgentExecutionStarted extends AgentEvent
{
    public readonly DateTimeImmutable $startedAt;

    public function __construct(
        public readonly string $agentId,
        public readonly ?string $parentAgentId,
        public readonly int $messageCount,
        public readonly int $availableTools,
    ) {
        $this->startedAt = new DateTimeImmutable();

        parent::__construct([
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'messages' => $this->messageCount,
            'tools' => $this->availableTools,
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $parentInfo = $this->parentAgentId ? sprintf(' [parent=%s]', substr($this->parentAgentId, 0, 8)) : '';

        return sprintf(
            'Agent [%s]%s execution started (messages=%d, tools=%d)',
            substr($this->agentId, 0, 8),
            $parentInfo,
            $this->messageCount,
            $this->availableTools
        );
    }
}
