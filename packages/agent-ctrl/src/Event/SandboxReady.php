<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted when sandbox is ready for execution.
 */
final class SandboxReady extends AgentEvent
{
    public string $logLevel = LogLevel::DEBUG;

    public function __construct(
        AgentType $agentType,
        public readonly string $driver,
        public readonly float $totalSetupDurationMs,
    ) {
        parent::__construct($agentType, [
            'driver' => $driver,
            'totalSetupDurationMs' => round($totalSetupDurationMs, 2),
        ]);
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s sandbox ready (%s) after %.2fms total setup',
            $this->agentType->value,
            $this->driver,
            $this->totalSetupDurationMs
        );
    }
}