<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent;

/**
 * Event emitted when a turn starts
 *
 * Example: {"type":"turn.started"}
 */
final readonly class TurnStartedEvent extends StreamEvent
{
    public function type(): string
    {
        return 'turn.started';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self();
    }
}
