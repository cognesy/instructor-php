<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeMessageId;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodePartId;

/**
 * Event emitted when text content is generated
 */
final readonly class TextEvent extends StreamEvent
{
    public ?OpenCodeMessageId $messageIdValue;
    public ?OpenCodePartId $partIdValue;

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
        $this->messageIdValue = $messageId !== ''
            ? OpenCodeMessageId::fromString($messageId)
            : null;
        $this->partIdValue = $partId !== ''
            ? OpenCodePartId::fromString($partId)
            : null;
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
