<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Events;

final class DecisionAttemptStarted extends DecisionEvent
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public readonly string $executionId,
        public readonly string $requestId,
        public readonly string $attemptId,
        public readonly int $attemptNumber,
        public readonly string $model,
        public readonly string $driver,
        array $data = [],
    ) {
        parent::__construct([
            ...$data,
            'executionId' => $this->executionId,
            'requestId' => $this->requestId,
            'attemptId' => $this->attemptId,
            'attemptNumber' => $this->attemptNumber,
            'isRetry' => $this->isRetry(),
            'model' => $this->model,
            'driver' => $this->driver,
        ]);
    }

    public function isRetry(): bool
    {
        return $this->attemptNumber > 1;
    }
}
