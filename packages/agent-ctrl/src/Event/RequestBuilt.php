<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted when agent request DTO is built.
 */
final class RequestBuilt extends AgentEvent
{
    public string $logLevel = LogLevel::DEBUG;

    public function __construct(
        AgentType $agentType,
        public readonly string $requestType,
        public readonly float $buildDurationMs,
    ) {
        parent::__construct($agentType, [
            'requestType' => $requestType,
            'buildDurationMs' => round($buildDurationMs, 2),
        ]);
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s request built (%s) in %.2fms',
            $this->agentType->value,
            $this->requestType,
            $this->buildDurationMs
        );
    }
}