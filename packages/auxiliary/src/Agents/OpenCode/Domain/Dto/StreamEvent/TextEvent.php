<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\OpenCode\Domain\Dto\StreamEvent;

/**
 * Event emitted when text content is generated
 */
final readonly class TextEvent extends StreamEvent
{
    public function __construct(
        int $timestamp,
        string $sessionId,
        public string $messageId,
        public string $partId,
        public string $text,
        public ?int $startTime = null,
        public ?int $endTime = null,
    ) {
        parent::__construct($timestamp, $sessionId);
    }

    #[\Override]
    public function type(): string
    {
        return 'text';
    }

    public static function fromArray(array $data): self
    {
        $part = $data['part'] ?? [];
        $time = $part['time'] ?? [];

        return new self(
            timestamp: $data['timestamp'] ?? 0,
            sessionId: $data['sessionID'] ?? '',
            messageId: $part['messageID'] ?? '',
            partId: $part['id'] ?? '',
            text: $part['text'] ?? '',
            startTime: $time['start'] ?? null,
            endTime: $time['end'] ?? null,
        );
    }
}
