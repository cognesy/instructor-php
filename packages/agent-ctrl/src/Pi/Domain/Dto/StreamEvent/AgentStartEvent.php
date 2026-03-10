<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent;

/**
 * Event emitted when the agent starts processing
 *
 * Example: {"type":"agent_start"}
 */
final readonly class AgentStartEvent extends StreamEvent
{
    #[\Override]
    public function type(): string
    {
        return 'agent_start';
    }

    public static function fromArray(array $data): self
    {
        return new self(rawData: $data);
    }
}
