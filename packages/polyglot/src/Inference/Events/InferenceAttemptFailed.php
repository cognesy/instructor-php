<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;
use Throwable;

/**
 * Dispatched when an inference attempt fails.
 * May be followed by a retry (InferenceAttemptStarted with higher attemptNumber).
 */
final class InferenceAttemptFailed extends InferenceEvent
{
    public readonly DateTimeImmutable $failedAt;
    public readonly float $durationMs;

    public function __construct(
        public readonly string $executionId,
        public readonly string $attemptId,
        public readonly int $attemptNumber,
        public readonly string $errorMessage,
        public readonly ?string $errorType = null,
        public readonly ?int $httpStatusCode = null,
        public readonly bool $willRetry = false,
        public readonly ?Usage $partialUsage = null,
        ?DateTimeImmutable $startedAt = null,
    ) {
        $this->failedAt = new DateTimeImmutable();
        $this->durationMs = $startedAt !== null
            ? $this->calculateDurationMs($startedAt, $this->failedAt)
            : 0.0;

        parent::__construct([
            'executionId' => $this->executionId,
            'attemptId' => $this->attemptId,
            'attemptNumber' => $this->attemptNumber,
            'errorMessage' => $this->errorMessage,
            'errorType' => $this->errorType,
            'httpStatusCode' => $this->httpStatusCode,
            'willRetry' => $this->willRetry,
            'durationMs' => $this->durationMs,
            'partialUsage' => $this->partialUsage?->toArray(),
        ]);
    }

    public static function fromThrowable(
        string $executionId,
        string $attemptId,
        int $attemptNumber,
        Throwable $error,
        bool $willRetry = false,
        ?int $httpStatusCode = null,
        ?Usage $partialUsage = null,
        ?DateTimeImmutable $startedAt = null,
    ): self {
        return new self(
            executionId: $executionId,
            attemptId: $attemptId,
            attemptNumber: $attemptNumber,
            errorMessage: $error->getMessage(),
            errorType: get_class($error),
            httpStatusCode: $httpStatusCode,
            willRetry: $willRetry,
            partialUsage: $partialUsage,
            startedAt: $startedAt,
        );
    }

    private function calculateDurationMs(DateTimeImmutable $start, DateTimeImmutable $end): float {
        $interval = $start->diff($end);
        return ($interval->s * 1000) + ($interval->f * 1000);
    }

    #[\Override]
    public function __toString(): string {
        $retryInfo = $this->willRetry ? ' (will retry)' : ' (final)';
        $statusInfo = $this->httpStatusCode !== null ? " status={$this->httpStatusCode}" : '';
        return sprintf(
            'Attempt #%d failed [%s]%s: %s%s',
            $this->attemptNumber,
            $this->attemptId,
            $statusInfo,
            $this->errorMessage,
            $retryInfo
        );
    }
}
