<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted when command specification (argv) is created.
 */
final class CommandSpecCreated extends AgentEvent
{
    public string $logLevel = LogLevel::DEBUG;

    public function __construct(
        AgentType $agentType,
        public readonly int $argvCount,
        public readonly float $commandDurationMs,
    ) {
        parent::__construct($agentType, [
            'argvCount' => $argvCount,
            'commandDurationMs' => round($commandDurationMs, 2),
        ]);
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s command spec created (%d args) in %.2fms',
            $this->agentType->value,
            $this->argvCount,
            $this->commandDurationMs
        );
    }
}