<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\OpenCode\Domain\Value\TokenUsage;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeMessageId;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodePartId;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeSessionId;

/**
 * Event emitted when a step/turn finishes
 *
 * Contains usage statistics and cost information.
 */
final readonly class StepFinishEvent extends StreamEvent
{
    private ?OpenCodeMessageId $messageId;
    private ?OpenCodePartId $partId;

    public function __construct(
        int $timestamp,
        OpenCodeSessionId|string|null $sessionId,
        OpenCodeMessageId|string|null $messageId,
        OpenCodePartId|string|null $partId,
        public string $reason,
        public string $snapshot,
        public float $cost,
        public ?TokenUsage $tokens = null,
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
        return 'step_finish';
    }

    /**
     * Check if this is the final step (no more tool calls needed)
     */
    public function isFinal(): bool
    {
        return $this->reason === 'stop';
    }

    /**
     * Check if more steps are needed (tool calls pending)
     */
    public function hasMoreSteps(): bool
    {
        return $this->reason === 'tool-calls';
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
        $tokensData = $part['tokens'] ?? null;

        return new self(
            timestamp: $data['timestamp'] ?? 0,
            sessionId: $data['sessionID'] ?? '',
            messageId: $part['messageID'] ?? '',
            partId: $part['id'] ?? '',
            reason: $part['reason'] ?? 'unknown',
            snapshot: $part['snapshot'] ?? '',
            cost: (float) ($part['cost'] ?? 0.0),
            tokens: $tokensData !== null ? TokenUsage::fromArray($tokensData) : null,
        );
    }
}
