<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use DateTimeImmutable;

/**
 * Statistics for a single inference attempt.
 *
 * Captures timing, token usage, and throughput metrics for one attempt
 * within an inference execution. Multiple attempts may occur due to retries.
 */
final readonly class InferenceAttemptStats
{
    public function __construct(
        // Identity
        public string $executionId,
        public string $attemptId,
        public int $attemptNumber,

        // Timing
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $completedAt,
        public float $durationMs,
        public ?float $timeToFirstChunkMs,

        // Token usage
        public int $inputTokens,
        public int $outputTokens,
        public int $cacheReadTokens,
        public int $cacheWriteTokens,
        public int $reasoningTokens,

        // Outcome
        public bool $isSuccess,
        public ?string $finishReason,
        public ?string $errorMessage,
        public ?string $errorType,

        // Context
        public ?string $model,
        public bool $isStreamed,
    ) {}

    /**
     * Total tokens processed in this attempt.
     */
    public function totalTokens(): int {
        return $this->inputTokens
            + $this->outputTokens
            + $this->cacheReadTokens
            + $this->cacheWriteTokens
            + $this->reasoningTokens;
    }

    /**
     * Output tokens per second (throughput metric).
     * Returns 0 if duration is 0 or no output tokens.
     */
    public function outputTokensPerSecond(): float {
        if ($this->durationMs <= 0 || $this->outputTokens <= 0) {
            return 0.0;
        }
        return ($this->outputTokens / $this->durationMs) * 1000;
    }

    /**
     * Total tokens per second.
     */
    public function totalTokensPerSecond(): float {
        $total = $this->totalTokens();
        if ($this->durationMs <= 0 || $total <= 0) {
            return 0.0;
        }
        return ($total / $this->durationMs) * 1000;
    }

    /**
     * Whether this is a retry attempt (attemptNumber > 1).
     */
    public function isRetry(): bool {
        return $this->attemptNumber > 1;
    }

    /**
     * Convert to array for serialization/logging.
     */
    public function toArray(): array {
        return [
            'executionId' => $this->executionId,
            'attemptId' => $this->attemptId,
            'attemptNumber' => $this->attemptNumber,
            'startedAt' => $this->startedAt->format(DATE_ATOM),
            'completedAt' => $this->completedAt->format(DATE_ATOM),
            'durationMs' => $this->durationMs,
            'timeToFirstChunkMs' => $this->timeToFirstChunkMs,
            'inputTokens' => $this->inputTokens,
            'outputTokens' => $this->outputTokens,
            'cacheReadTokens' => $this->cacheReadTokens,
            'cacheWriteTokens' => $this->cacheWriteTokens,
            'reasoningTokens' => $this->reasoningTokens,
            'totalTokens' => $this->totalTokens(),
            'outputTokensPerSecond' => $this->outputTokensPerSecond(),
            'isSuccess' => $this->isSuccess,
            'finishReason' => $this->finishReason,
            'errorMessage' => $this->errorMessage,
            'errorType' => $this->errorType,
            'model' => $this->model,
            'isStreamed' => $this->isStreamed,
            'isRetry' => $this->isRetry(),
        ];
    }

    /**
     * Human-readable summary string.
     */
    public function __toString(): string {
        $status = $this->isSuccess ? 'success' : 'failed';
        $ttfc = $this->timeToFirstChunkMs !== null
            ? sprintf(' ttfc=%.1fms', $this->timeToFirstChunkMs)
            : '';
        return sprintf(
            'Attempt #%d [%s]: %s duration=%.1fms%s tokens=%d (in=%d out=%d) %.1f tok/s',
            $this->attemptNumber,
            $this->attemptId,
            $status,
            $this->durationMs,
            $ttfc,
            $this->totalTokens(),
            $this->inputTokens,
            $this->outputTokens,
            $this->outputTokensPerSecond()
        );
    }
}
