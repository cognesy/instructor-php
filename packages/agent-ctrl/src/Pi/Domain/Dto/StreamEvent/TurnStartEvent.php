<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent;

/**
 * Event emitted when a new turn begins
 *
 * Example: {"type":"turn_start"}
 */
final readonly class TurnStartEvent extends StreamEvent
{
    #[\Override]
    public function type(): string
    {
        return 'turn_start';
    }

    public static function fromArray(array $data): self
    {
        return new self(rawData: $data);
    }
}
