<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\OpenCode\Domain\Value\TokenUsage;

/**
 * Event emitted when a step/turn finishes
 *
 * Contains usage statistics and cost information.
 */
final readonly class StepFinishEvent extends StreamEvent
{
    public function __construct(
        int $timestamp,
        string $sessionId,
        public string $messageId,
        public string $partId,
        public string $reason,
        public string $snapshot,
        public float $cost,
        public ?TokenUsage $tokens = null,
    ) {
        parent::__construct($timestamp, $sessionId);
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
