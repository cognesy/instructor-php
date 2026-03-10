<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Gemini\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;
use Cognesy\AgentCtrl\Gemini\Domain\ValueObject\GeminiSessionId;

/**
 * Session init event — first event in the stream-json stream
 *
 * Example: {"type":"init","timestamp":"2025-01-01T00:00:00Z","session_id":"uuid","model":"gemini-2.5-pro"}
 */
final readonly class InitEvent extends StreamEvent
{
    private ?GeminiSessionId $sessionId;

    public function __construct(
        array $rawData,
        GeminiSessionId|string|null $sessionId,
        public string $model,
        public string $timestamp,
    ) {
        parent::__construct($rawData);
        $this->sessionId = match (true) {
            $sessionId instanceof GeminiSessionId => $sessionId,
            is_string($sessionId) && $sessionId !== '' => GeminiSessionId::fromString($sessionId),
            default => null,
        };
    }

    #[\Override]
    public function type(): string
    {
        return 'init';
    }

    public function sessionId(): ?GeminiSessionId
    {
        return $this->sessionId;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            rawData: $data,
            sessionId: Normalize::toString($data['session_id'] ?? ''),
            model: Normalize::toString($data['model'] ?? ''),
            timestamp: Normalize::toString($data['timestamp'] ?? ''),
        );
    }
}
