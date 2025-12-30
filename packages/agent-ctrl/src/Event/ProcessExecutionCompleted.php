<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted when process execution completes (after all retries).
 */
final class ProcessExecutionCompleted extends AgentEvent
{
    public string $logLevel = LogLevel::INFO;

    public function __construct(
        AgentType $agentType,
        public readonly int $totalAttempts,
        public readonly float $totalExecutionDurationMs,
        public readonly int $successAttempt,
    ) {
        parent::__construct($agentType, [
            'totalAttempts' => $totalAttempts,
            'totalExecutionDurationMs' => round($totalExecutionDurationMs, 2),
            'successAttempt' => $successAttempt,
        ]);
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s process execution completed in %.2fms (%d attempts, success on #%d)',
            $this->agentType->value,
            $this->totalExecutionDurationMs,
            $this->totalAttempts,
            $this->successAttempt
        );
    }
}