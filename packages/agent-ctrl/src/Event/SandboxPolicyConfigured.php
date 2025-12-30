<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted when sandbox execution policy is configured.
 */
final class SandboxPolicyConfigured extends AgentEvent
{
    public string $logLevel = LogLevel::DEBUG;

    public function __construct(
        AgentType $agentType,
        public readonly string $driver,
        public readonly int $timeout,
        public readonly bool $networkEnabled,
        public readonly float $configureDurationMs,
    ) {
        parent::__construct($agentType, [
            'driver' => $driver,
            'timeout' => $timeout,
            'networkEnabled' => $networkEnabled,
            'configureDurationMs' => round($configureDurationMs, 2),
        ]);
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s sandbox policy configured (%s, %ds timeout) in %.2fms',
            $this->agentType->value,
            $this->driver,
            $this->timeout,
            $this->configureDurationMs
        );
    }
}