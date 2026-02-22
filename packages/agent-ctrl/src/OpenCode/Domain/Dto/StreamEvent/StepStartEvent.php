<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeMessageId;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodePartId;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeSessionId;

/**
 * Event emitted when a step/turn begins
 */
final readonly class StepStartEvent extends StreamEvent
{
    private ?OpenCodeMessageId $messageId;
    private ?OpenCodePartId $partId;

    public function __construct(
        int $timestamp,
        OpenCodeSessionId|string|null $sessionId,
        OpenCodeMessageId|string|null $messageId,
        OpenCodePartId|string|null $partId,
        public string $snapshot,
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
        return 'step_start';
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
