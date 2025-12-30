<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted when stream processing completes.
 */
final class StreamProcessingCompleted extends AgentEvent
{
    public string $logLevel = LogLevel::DEBUG;

    public function __construct(
        AgentType $agentType,
        public readonly int $totalChunks,
        public readonly float $totalDurationMs,
        public readonly int $bytesProcessed,
    ) {
        parent::__construct($agentType, [
            'totalChunks' => $totalChunks,
            'totalDurationMs' => round($totalDurationMs, 2),
            'bytesProcessed' => $bytesProcessed,
        ]);
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s stream processing completed (%d chunks, %d bytes) in %.2fms',
            $this->agentType->value,
            $this->totalChunks,
            $this->bytesProcessed,
            $this->totalDurationMs
        );
    }
}