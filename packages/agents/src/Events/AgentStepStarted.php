<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use DateTimeImmutable;
use Psr\Log\LogLevel;

/**
 * Dispatched when an agent step begins execution.
 * Use for timing individual step latency and observing agent iteration progress.
 */
final class AgentStepStarted extends AgentEvent
{
    public string $logLevel = LogLevel::INFO;
    public readonly DateTimeImmutable $startedAt;

    public function __construct(
        public readonly string $agentId,
        public readonly string $executionId,
        public readonly ?string $parentAgentId,
        public readonly int $stepNumber,
        public readonly int $messageCount,
        public readonly int $availableTools,
        public readonly array $messages = [],
    ) {
        $this->startedAt = new DateTimeImmutable();

        parent::__construct([
            'agentId' => $this->agentId,
            'executionId' => $this->executionId,
            'parentAgentId' => $this->parentAgentId,
            'step' => $this->stepNumber,
            'messages' => $this->messageCount,
            'tools' => $this->availableTools,
            'messagePayload' => $this->messages,
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $parentInfo = $this->parentAgentId ? sprintf(' [parent=%s]', substr($this->parentAgentId, 0, 8)) : '';

        return sprintf(
            'Agent [%s]%s step %d started (messages=%d, tools=%d)',
            substr($this->agentId, 0, 8),
            $parentInfo,
            $this->stepNumber,
            $this->messageCount,
            $this->availableTools
        );
    }
}
