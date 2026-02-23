<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted for each process execution attempt.
 */
final class ExecutionAttempted extends AgentEvent
{
    public string $logLevel = LogLevel::DEBUG;

    public function __construct(
        AgentType $agentType,
        public readonly int $attemptNumber,
        public readonly float $executionDurationMs,
        public readonly ?string $error = null,
    ) {
        parent::__construct($agentType, [
            'attemptNumber' => $attemptNumber,
            'executionDurationMs' => round($executionDurationMs, 2),
            'error' => $error,
        ]);
    }

    #[\Override]
    public function __toString(): string
    {
        $status = $this->error ? 'failed' : 'succeeded';

        return sprintf(
            'Agent %s execution attempt %d %s in %.2fms',
            $this->agentType->value,
            $this->attemptNumber,
            $status,
            $this->executionDurationMs,
        );
    }
}
