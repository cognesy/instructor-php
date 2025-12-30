<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted for each processed stream chunk.
 */
final class StreamChunkProcessed extends AgentEvent
{
    public string $logLevel = LogLevel::DEBUG;

    public function __construct(
        AgentType $agentType,
        public readonly int $chunkNumber,
        public readonly int $chunkSize,
        public readonly string $contentType,
        public readonly float $processingDurationMs,
    ) {
        parent::__construct($agentType, [
            'chunkNumber' => $chunkNumber,
            'chunkSize' => $chunkSize,
            'contentType' => $contentType,
            'processingDurationMs' => round($processingDurationMs, 2),
        ]);
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s processed chunk #%d (%s, %d bytes) in %.2fms',
            $this->agentType->value,
            $this->chunkNumber,
            $this->contentType,
            $this->chunkSize,
            $this->processingDurationMs
        );
    }
}