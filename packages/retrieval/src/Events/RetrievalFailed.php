<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Events;

use Psr\Log\LogLevel;

final class RetrievalFailed extends RetrievalEvent
{
    public string $logLevel = LogLevel::ERROR;

    public function __construct(
        public readonly string $executionId,
        public readonly string $requestId,
        public readonly string $driver,
        public readonly string $errorType,
        public readonly float $durationMs,
    ) {
        parent::__construct([
            'executionId' => $executionId,
            'requestId' => $requestId,
            'driver' => $driver,
            'errorType' => $errorType,
            'durationMs' => $durationMs,
        ]);
    }
}
