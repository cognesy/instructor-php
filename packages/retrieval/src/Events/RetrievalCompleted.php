<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Events;

use Psr\Log\LogLevel;

final class RetrievalCompleted extends RetrievalEvent
{
    public string $logLevel = LogLevel::INFO;

    public function __construct(
        public readonly string $executionId,
        public readonly string $requestId,
        public readonly string $driver,
        public readonly int $hitCount,
        public readonly float $durationMs,
    ) {
        parent::__construct([
            'executionId' => $executionId,
            'requestId' => $requestId,
            'driver' => $driver,
            'hitCount' => $hitCount,
            'durationMs' => $durationMs,
        ]);
    }
}
