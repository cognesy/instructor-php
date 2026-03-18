<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\ValueObject\AgentCtrlExecutionId;
use Psr\Log\LogLevel;

/**
 * Emitted when response parsing starts.
 */
final class ResponseParsingStarted extends AgentEvent
{
    public string $logLevel = LogLevel::DEBUG;

    public function __construct(
        AgentType $agentType,
        AgentCtrlExecutionId $executionId,
        public readonly int $responseSize,
        public readonly string $format,
    ) {
        parent::__construct($agentType, $executionId, [
            'responseSize' => $responseSize,
            'format' => $format,
        ]);
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s response parsing started (%s format, %d bytes)',
            $this->agentType->value,
            $this->format,
            $this->responseSize
        );
    }
}
