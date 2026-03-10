<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\Common\Value\Normalize;
use Cognesy\AgentCtrl\Pi\Domain\ValueObject\PiSessionId;

/**
 * Session header event — first event in the JSONL stream
 *
 * Example: {"type":"session","version":3,"id":"uuid","timestamp":"...","cwd":"/path"}
 */
final readonly class SessionEvent extends StreamEvent
{
    private ?PiSessionId $sessionId;

    public function __construct(
        array $rawData,
        PiSessionId|string|null $sessionId,
        public int $version,
        public string $cwd,
        public string $timestamp,
    ) {
        parent::__construct($rawData);
        $this->sessionId = match (true) {
            $sessionId instanceof PiSessionId => $sessionId,
            is_string($sessionId) && $sessionId !== '' => PiSessionId::fromString($sessionId),
            default => null,
        };
    }

    #[\Override]
    public function type(): string
    {
        return 'session';
    }

    public function sessionId(): ?PiSessionId
    {
        return $this->sessionId;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            rawData: $data,
            sessionId: Normalize::toString($data['id'] ?? ''),
            version: Normalize::toInt($data['version'] ?? 0),
            cwd: Normalize::toString($data['cwd'] ?? ''),
            timestamp: Normalize::toString($data['timestamp'] ?? ''),
        );
    }
}
