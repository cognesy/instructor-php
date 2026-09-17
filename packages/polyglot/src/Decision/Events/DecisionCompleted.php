<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Events;

use Cognesy\Polyglot\Decision\Data\DecisionUsage;
use Psr\Log\LogLevel;

final class DecisionCompleted extends DecisionEvent
{
    public string $logLevel = LogLevel::INFO;

    /** @param array<string,mixed> $data */
    public function __construct(
        public readonly string $executionId,
        public readonly string $requestId,
        public readonly string $model,
        public readonly string $driver,
        public readonly int $primitiveCount,
        public readonly int $attemptCount,
        public readonly float $durationMs,
        DecisionUsage $usage,
        array $data = [],
    ) {
        parent::__construct([
            ...$data,
            'executionId' => $this->executionId,
            'requestId' => $this->requestId,
            'model' => $this->model,
            'driver' => $this->driver,
            'primitiveCount' => $this->primitiveCount,
            'attemptCount' => $this->attemptCount,
            'durationMs' => $this->durationMs,
            'inputTokens' => $usage->inputTokens(),
            'outputTokens' => $usage->outputTokens(),
            'totalTokens' => $usage->totalTokens(),
        ]);
    }
}
