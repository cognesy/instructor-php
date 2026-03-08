<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeMessageId;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodePartId;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeSessionId;

/**
 * Event emitted when text content is generated
 */
final readonly class TextEvent extends StreamEvent
{
    private ?OpenCodeMessageId $messageId;
    private ?OpenCodePartId $partId;

    public function __construct(
        int $timestamp,
        OpenCodeSessionId|string|null $sessionId,
        OpenCodeMessageId|string|null $messageId,
        OpenCodePartId|string|null $partId,
        public string $text,
        public ?int $startTime = null,
        public ?int $endTime = null,
    ) {
        parent::__construct($timestamp, $sessionId);
        $this->messageId = match (true) {
            $messageId instanceof OpenCodeMessageId => $messageId,
            is_string($messageId) && $messageId !== '' => OpenCodeMessageId::fromString($messageId),
            default => null,
        };
        $this->partId = match (true) {
            $partId instanceof OpenCodePartId => $partId,
            is_string($partId) && $partId !== '' => OpenCodePartId::fromString($partId),
            default => null,
        };
    }

    #[\Override]
    public function type(): string
    {
        return 'text';
    }

    public function messageId(): ?OpenCodeMessageId
    {
        return $this->messageId;
    }

    public function partId(): ?OpenCodePartId
    {
        return $this->partId;
    }

    public static function fromArray(array $data): self
    {
        $part = Normalize::toArray($data['part'] ?? []);
        $time = Normalize::toArray($part['time'] ?? []);

        return new self(
            timestamp: Normalize::toInt($data['timestamp'] ?? 0),
            sessionId: Normalize::toString($data['sessionID'] ?? ''),
            messageId: Normalize::toString($part['messageID'] ?? ''),
            partId: Normalize::toString($part['id'] ?? ''),
            text: Normalize::toString($part['text'] ?? ''),
            startTime: Normalize::toNullableInt($time['start'] ?? null),
            endTime: Normalize::toNullableInt($time['end'] ?? null),
        );
    }
}
