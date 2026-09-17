<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Events;

use Psr\Log\LogLevel;

final class DecisionAttemptFailed extends DecisionEvent
{
    public string $logLevel = LogLevel::WARNING;

    /** @param array<string,mixed> $data */
    public function __construct(
        public readonly string $executionId,
        public readonly string $requestId,
        public readonly string $attemptId,
        public readonly int $attemptNumber,
        public readonly string $errorType,
        public readonly ?int $httpStatusCode,
        public readonly bool $willRetry,
        public readonly float $durationMs,
        array $data = [],
    ) {
        parent::__construct([
            ...$data,
            'executionId' => $this->executionId,
            'requestId' => $this->requestId,
            'attemptId' => $this->attemptId,
            'attemptNumber' => $this->attemptNumber,
            'errorType' => $this->errorType,
            'httpStatusCode' => $this->httpStatusCode,
            'willRetry' => $this->willRetry,
            'durationMs' => $this->durationMs,
        ]);
    }
}
