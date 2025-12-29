<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent;

/**
 * Event emitted when a step/turn begins
 */
final readonly class StepStartEvent extends StreamEvent
{
    public function __construct(
        int $timestamp,
        string $sessionId,
        public string $messageId,
        public string $partId,
        public string $snapshot,
    ) {
        parent::__construct($timestamp, $sessionId);
    }

    #[\Override]
    public function type(): string
    {
        return 'step_start';
    }

    public static function fromArray(array $data): self
    {
        $part = $data['part'] ?? [];

        return new self(
            timestamp: $data['timestamp'] ?? 0,
            sessionId: $data['sessionID'] ?? '',
            messageId: $part['messageID'] ?? '',
            partId: $part['id'] ?? '',
            snapshot: $part['snapshot'] ?? '',
        );
    }
}
