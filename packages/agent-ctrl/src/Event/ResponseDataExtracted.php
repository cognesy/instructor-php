<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted when response data is extracted and structured.
 */
final class ResponseDataExtracted extends AgentEvent
{
    public string $logLevel = LogLevel::DEBUG;

    public function __construct(
        AgentType $agentType,
        public readonly int $eventCount,
        public readonly int $toolUseCount,
        public readonly int $textLength,
        public readonly float $extractDurationMs,
    ) {
        parent::__construct($agentType, [
            'eventCount' => $eventCount,
            'toolUseCount' => $toolUseCount,
            'textLength' => $textLength,
            'extractDurationMs' => round($extractDurationMs, 2),
        ]);
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s response data extracted (%d events, %d tools, %d chars) in %.2fms',
            $this->agentType->value,
            $this->eventCount,
            $this->toolUseCount,
            $this->textLength,
            $this->extractDurationMs
        );
    }
}