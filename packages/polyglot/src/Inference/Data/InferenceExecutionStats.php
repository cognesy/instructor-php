<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use DateTimeImmutable;

/**
 * Aggregate statistics for a complete inference execution.
 *
 * Captures cumulative timing, token usage, and throughput metrics
 * across all attempts in an inference execution.
 */
final readonly class InferenceExecutionStats
{
    /**
     * @param InferenceAttemptStats[] $attemptStats
     */
    public function __construct(
        // Identity
        public string $executionId,

        // Timing
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $completedAt,
        public float $totalDurationMs,
        public ?float $timeToFirstChunkMs,

        // Attempts
        public int $attemptCount,
        public int $successfulAttempts,
        public int $failedAttempts,

        // Cumulative token usage (across all attempts)
        public int $totalInputTokens,
        public int $totalOutputTokens,
        public int $totalCacheReadTokens,
        public int $totalCacheWriteTokens,
        public int $totalReasoningTokens,

        // Outcome
        public bool $isSuccess,
        public ?string $finishReason,

        // Context
        public ?string $model,
        public bool $isStreamed,

        // Per-attempt breakdown
        public array $attemptStats = [],
    ) {}

    /**
     * Total tokens across all attempts.
     */
    public function totalTokens(): int {
        return $this->totalInputTokens
            + $this->totalOutputTokens
            + $this->totalCacheReadTokens
            + $this->totalCacheWriteTokens
            + $this->totalReasoningTokens;
    }

    /**
     * Output tokens per second (throughput metric for successful attempt).
     */
    public function outputTokensPerSecond(): float {
        if ($this->totalDurationMs <= 0 || $this->totalOutputTokens <= 0) {
            return 0.0;
        }
        return ($this->totalOutputTokens / $this->totalDurationMs) * 1000;
    }

    /**
     * Total tokens per second.
     */
    public function totalTokensPerSecond(): float {
        $total = $this->totalTokens();
        if ($this->totalDurationMs <= 0 || $total <= 0) {
            return 0.0;
        }
        return ($total / $this->totalDurationMs) * 1000;
    }

    /**
     * Whether retries occurred (more than one attempt).
     */
    public function hadRetries(): bool {
        return $this->attemptCount > 1;
    }

    /**
     * Average duration per attempt in milliseconds.
     */
    public function averageAttemptDurationMs(): float {
        if ($this->attemptCount <= 0) {
            return 0.0;
        }
        return $this->totalDurationMs / $this->attemptCount;
    }

    /**
     * Get stats for a specific attempt by number (1-indexed).
     */
    public function getAttemptStats(int $attemptNumber): ?InferenceAttemptStats {
        foreach ($this->attemptStats as $stats) {
            if ($stats->attemptNumber === $attemptNumber) {
                return $stats;
            }
        }
        return null;
    }

    /**
     * Get the last (final) attempt stats.
     */
    public function lastAttemptStats(): ?InferenceAttemptStats {
        if (empty($this->attemptStats)) {
            return null;
        }
        return $this->attemptStats[array_key_last($this->attemptStats)];
    }

    /**
     * Convert to array for serialization/logging.
     */
    public function toArray(): array {
        return [
            'executionId' => $this->executionId,
            'startedAt' => $this->startedAt->format(DATE_ATOM),
            'completedAt' => $this->completedAt->format(DATE_ATOM),
            'totalDurationMs' => $this->totalDurationMs,
            'timeToFirstChunkMs' => $this->timeToFirstChunkMs,
            'attemptCount' => $this->attemptCount,
            'successfulAttempts' => $this->successfulAttempts,
            'failedAttempts' => $this->failedAttempts,
            'totalInputTokens' => $this->totalInputTokens,
            'totalOutputTokens' => $this->totalOutputTokens,
            'totalCacheReadTokens' => $this->totalCacheReadTokens,
            'totalCacheWriteTokens' => $this->totalCacheWriteTokens,
            'totalReasoningTokens' => $this->totalReasoningTokens,
            'totalTokens' => $this->totalTokens(),
            'outputTokensPerSecond' => $this->outputTokensPerSecond(),
            'isSuccess' => $this->isSuccess,
            'finishReason' => $this->finishReason,
            'model' => $this->model,
            'isStreamed' => $this->isStreamed,
            'hadRetries' => $this->hadRetries(),
            'attempts' => array_map(fn($s) => $s->toArray(), $this->attemptStats),
        ];
    }

    /**
     * Human-readable summary string.
     */
    public function __toString(): string {
        $status = $this->isSuccess ? 'success' : 'failed';
        $retries = $this->hadRetries() ? " ({$this->failedAttempts} retries)" : '';
        $ttfc = $this->timeToFirstChunkMs !== null
            ? sprintf(' ttfc=%.1fms', $this->timeToFirstChunkMs)
            : '';
        return sprintf(
            'Execution [%s]: %s%s duration=%.1fms%s tokens=%d (in=%d out=%d) %.1f tok/s',
            $this->executionId,
            $status,
            $retries,
            $this->totalDurationMs,
            $ttfc,
            $this->totalTokens(),
            $this->totalInputTokens,
            $this->totalOutputTokens,
            $this->outputTokensPerSecond()
        );
    }
}
