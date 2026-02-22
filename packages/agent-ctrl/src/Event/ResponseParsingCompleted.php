<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\ValueObject\AgentSessionId;
use Psr\Log\LogLevel;

/**
 * Emitted when response parsing completes.
 */
final class ResponseParsingCompleted extends AgentEvent
{
    public string $logLevel = LogLevel::DEBUG;
    private ?AgentSessionId $sessionId;

    public function __construct(
        AgentType $agentType,
        public readonly float $totalDurationMs,
        AgentSessionId|string|null $sessionId = null,
    ) {
        $this->sessionId = match (true) {
            $sessionId instanceof AgentSessionId => $sessionId,
            is_string($sessionId) && $sessionId !== '' => AgentSessionId::fromString($sessionId),
            default => null,
        };

        parent::__construct($agentType, [
            'totalDurationMs' => round($totalDurationMs, 2),
            'sessionId' => $this->sessionId !== null ? (string) $this->sessionId : null,
        ]);
    }

    public function sessionId(): ?AgentSessionId
    {
        return $this->sessionId;
    }

    #[\Override]
    public function __toString(): string
    {
        $sessionInfo = $this->sessionId !== null
            ? " (session: {$this->sessionId})"
            : '';

        return sprintf(
            'Agent %s response parsing completed in %.2fms%s',
            $this->agentType->value,
            $this->totalDurationMs,
            $sessionInfo
        );
    }
}
