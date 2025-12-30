<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted when stream processing begins.
 */
final class StreamProcessingStarted extends AgentEvent
{
    public string $logLevel = LogLevel::DEBUG;

    public function __construct(
        AgentType $agentType,
    ) {
        parent::__construct($agentType, []);
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s stream processing started',
            $this->agentType->value
        );
    }
}