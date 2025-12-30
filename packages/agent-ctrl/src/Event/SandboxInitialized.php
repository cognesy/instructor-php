<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted when sandbox driver is initialized.
 */
final class SandboxInitialized extends AgentEvent
{
    public string $logLevel = LogLevel::DEBUG;

    public function __construct(
        AgentType $agentType,
        public readonly string $driver,
        public readonly float $initializationDurationMs,
    ) {
        parent::__construct($agentType, [
            'driver' => $driver,
            'initializationDurationMs' => round($initializationDurationMs, 2),
        ]);
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s sandbox initialized (%s) in %.2fms',
            $this->agentType->value,
            $this->driver,
            $this->initializationDurationMs
        );
    }
}