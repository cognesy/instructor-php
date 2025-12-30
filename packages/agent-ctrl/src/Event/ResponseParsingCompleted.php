<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted when response parsing completes.
 */
final class ResponseParsingCompleted extends AgentEvent
{
    public string $logLevel = LogLevel::DEBUG;

    public function __construct(
        AgentType $agentType,
        public readonly float $totalDurationMs,
        public readonly ?string $sessionId = null,
    ) {
        parent::__construct($agentType, [
            'totalDurationMs' => round($totalDurationMs, 2),
            'sessionId' => $sessionId,
        ]);
    }

    #[\Override]
    public function __toString(): string
    {
        $sessionInfo = $this->sessionId ? " (session: {$this->sessionId})" : '';

        return sprintf(
            'Agent %s response parsing completed in %.2fms%s',
            $this->agentType->value,
            $this->totalDurationMs,
            $sessionInfo
        );
    }
}