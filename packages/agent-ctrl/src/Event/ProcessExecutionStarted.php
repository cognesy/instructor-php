<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted when process execution starts.
 */
final class ProcessExecutionStarted extends AgentEvent
{
    public string $logLevel = LogLevel::INFO;

    public function __construct(
        AgentType $agentType,
        public readonly int $commandCount,
    ) {
        parent::__construct($agentType, [
            'commandCount' => $commandCount,
        ]);
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s process execution started (%d commands)',
            $this->agentType->value,
            $this->commandCount
        );
    }
}