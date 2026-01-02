<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use DateTimeImmutable;

/**
 * Dispatched when an inference attempt begins.
 * First attempt has attemptNumber=1. Retries have attemptNumber > 1.
 */
final class InferenceAttemptStarted extends InferenceEvent
{
    public readonly DateTimeImmutable $startedAt;

    public function __construct(
        public readonly string $executionId,
        public readonly string $attemptId,
        public readonly int $attemptNumber,
        public readonly ?string $model = null,
    ) {
        parent::__construct([
            'executionId' => $this->executionId,
            'attemptId' => $this->attemptId,
            'attemptNumber' => $this->attemptNumber,
            'model' => $this->model,
            'isRetry' => $this->attemptNumber > 1,
        ]);
        $this->startedAt = new DateTimeImmutable();
    }

    public function isRetry(): bool {
        return $this->attemptNumber > 1;
    }

    #[\Override]
    public function __toString(): string {
        $retryInfo = $this->isRetry() ? ' (retry)' : '';
        return sprintf(
            'Attempt #%d started [%s]%s',
            $this->attemptNumber,
            $this->attemptId,
            $retryInfo
        );
    }
}
