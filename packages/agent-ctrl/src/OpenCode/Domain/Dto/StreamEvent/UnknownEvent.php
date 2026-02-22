<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeSessionId;

/**
 * Fallback event for unrecognized event types
 */
final readonly class UnknownEvent extends StreamEvent
{
    public function __construct(
        int $timestamp,
        OpenCodeSessionId|string|null $sessionId,
        public string $rawType,
        public array $rawData,
    ) {
        parent::__construct($timestamp, $sessionId);
    }

    #[\Override]
    public function type(): string
    {
        return $this->rawType;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            timestamp: $data['timestamp'] ?? 0,
            sessionId: $data['sessionID'] ?? '',
            rawType: $data['type'] ?? 'unknown',
            rawData: $data,
        );
    }
}
