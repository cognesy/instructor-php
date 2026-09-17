<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Events;

use Psr\Log\LogLevel;

final class DecisionFailed extends DecisionEvent
{
    public string $logLevel = LogLevel::ERROR;

    /** @param array<string,mixed> $data */
    public function __construct(
        public readonly string $executionId,
        public readonly string $requestId,
        public readonly string $model,
        public readonly string $driver,
        public readonly int $primitiveCount,
        public readonly int $attemptCount,
        public readonly string $errorType,
        public readonly ?int $httpStatusCode,
        public readonly float $durationMs,
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
            'errorType' => $this->errorType,
            'httpStatusCode' => $this->httpStatusCode,
            'durationMs' => $this->durationMs,
        ]);
    }
}
