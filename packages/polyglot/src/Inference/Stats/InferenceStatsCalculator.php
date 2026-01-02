<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Stats;

use Cognesy\Polyglot\Inference\Data\InferenceAttempt;
use Cognesy\Polyglot\Inference\Data\InferenceAttemptStats;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceExecutionStats;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use DateTimeImmutable;
use Throwable;

/**
 * Calculates statistics for inference executions and attempts.
 *
 * This service computes timing, throughput, and usage metrics from
 * the raw inference data structures. It can be used to generate
 * stats events or for direct metrics collection.
 */
class InferenceStatsCalculator
{
    /**
     * Calculate comprehensive stats for a completed inference execution.
     *
     * @param InferenceExecution $execution The execution to analyze
     * @param DateTimeImmutable $startedAt When the execution started
     * @param float|null $timeToFirstChunkMs TTFC if streaming (null for non-streaming)
     * @param string|null $model The model used
     * @param bool $isStreamed Whether streaming was used
     * @param InferenceAttemptStats[] $attemptStats Pre-calculated attempt stats (optional)
     */
    public function calculateExecutionStats(
        InferenceExecution $execution,
        DateTimeImmutable $startedAt,
        ?float $timeToFirstChunkMs = null,
        ?string $model = null,
        bool $isStreamed = false,
        array $attemptStats = [],
    ): InferenceExecutionStats {
        $completedAt = new DateTimeImmutable();
        $durationMs = $this->calculateDurationMs($startedAt, $completedAt);

        // Aggregate usage across all attempts
        $totalUsage = $this->aggregateUsage($execution);

        // Count attempts
        $attempts = $execution->attempts();
        $attemptCount = max(1, $attempts->count());
        $successfulAttempts = 0;
        $failedAttempts = 0;

        foreach ($attempts->all() as $attempt) {
            if ($attempt->isFailed()) {
                $failedAttempts++;
            } else {
                $successfulAttempts++;
            }
        }

        // If no attempts recorded but we have a response, count as 1 successful
        if ($attemptCount === 0 && $execution->response() !== null) {
            $attemptCount = 1;
            $successfulAttempts = $execution->isSuccessful() ? 1 : 0;
            $failedAttempts = $execution->isFailed() ? 1 : 0;
        }

        $response = $execution->response();
        $finishReason = $response?->finishReason()?->value;

        return new InferenceExecutionStats(
            executionId: $execution->id,
            startedAt: $startedAt,
            completedAt: $completedAt,
            totalDurationMs: $durationMs,
            timeToFirstChunkMs: $timeToFirstChunkMs,
            attemptCount: $attemptCount,
            successfulAttempts: $successfulAttempts,
            failedAttempts: $failedAttempts,
            totalInputTokens: $totalUsage->inputTokens,
            totalOutputTokens: $totalUsage->outputTokens,
            totalCacheReadTokens: $totalUsage->cacheReadTokens,
            totalCacheWriteTokens: $totalUsage->cacheWriteTokens,
            totalReasoningTokens: $totalUsage->reasoningTokens,
            isSuccess: $execution->isSuccessful(),
            finishReason: $finishReason,
            model: $model,
            isStreamed: $isStreamed,
            attemptStats: $attemptStats,
        );
    }

    /**
     * Calculate stats for a single inference attempt.
     *
     * @param InferenceAttempt $attempt The attempt to analyze
     * @param string $executionId Parent execution ID
     * @param int $attemptNumber The attempt number (1-indexed)
     * @param DateTimeImmutable $startedAt When the attempt started
     * @param float|null $timeToFirstChunkMs TTFC if streaming
     * @param string|null $model The model used
     * @param bool $isStreamed Whether streaming was used
     * @param Throwable|null $error Error if the attempt failed
     */
    public function calculateAttemptStats(
        InferenceAttempt $attempt,
        string $executionId,
        int $attemptNumber,
        DateTimeImmutable $startedAt,
        ?float $timeToFirstChunkMs = null,
        ?string $model = null,
        bool $isStreamed = false,
        ?Throwable $error = null,
    ): InferenceAttemptStats {
        $completedAt = new DateTimeImmutable();
        $durationMs = $this->calculateDurationMs($startedAt, $completedAt);

        $usage = $attempt->usage();
        $response = $attempt->response();
        $finishReason = $response?->finishReason()?->value;

        $errorMessage = null;
        $errorType = null;
        if ($error !== null) {
            $errorMessage = $error->getMessage();
            $errorType = get_class($error);
        } elseif ($attempt->hasErrors()) {
            $errors = $attempt->errors();
            $firstError = reset($errors);
            if ($firstError instanceof Throwable) {
                $errorMessage = $firstError->getMessage();
                $errorType = get_class($firstError);
            } elseif (is_string($firstError)) {
                $errorMessage = $firstError;
            }
        }

        return new InferenceAttemptStats(
            executionId: $executionId,
            attemptId: $attempt->id,
            attemptNumber: $attemptNumber,
            startedAt: $startedAt,
            completedAt: $completedAt,
            durationMs: $durationMs,
            timeToFirstChunkMs: $timeToFirstChunkMs,
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            cacheReadTokens: $usage->cacheReadTokens,
            cacheWriteTokens: $usage->cacheWriteTokens,
            reasoningTokens: $usage->reasoningTokens,
            isSuccess: !$attempt->isFailed(),
            finishReason: $finishReason,
            errorMessage: $errorMessage,
            errorType: $errorType,
            model: $model,
            isStreamed: $isStreamed,
        );
    }

    /**
     * Create attempt stats from a successful response (without InferenceAttempt object).
     */
    public function calculateAttemptStatsFromResponse(
        InferenceResponse $response,
        string $executionId,
        string $attemptId,
        int $attemptNumber,
        DateTimeImmutable $startedAt,
        ?float $timeToFirstChunkMs = null,
        ?string $model = null,
        bool $isStreamed = false,
    ): InferenceAttemptStats {
        $completedAt = new DateTimeImmutable();
        $durationMs = $this->calculateDurationMs($startedAt, $completedAt);
        $usage = $response->usage();

        return new InferenceAttemptStats(
            executionId: $executionId,
            attemptId: $attemptId,
            attemptNumber: $attemptNumber,
            startedAt: $startedAt,
            completedAt: $completedAt,
            durationMs: $durationMs,
            timeToFirstChunkMs: $timeToFirstChunkMs,
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            cacheReadTokens: $usage->cacheReadTokens,
            cacheWriteTokens: $usage->cacheWriteTokens,
            reasoningTokens: $usage->reasoningTokens,
            isSuccess: !$response->hasFinishedWithFailure(),
            finishReason: $response->finishReason()->value,
            errorMessage: null,
            errorType: null,
            model: $model,
            isStreamed: $isStreamed,
        );
    }

    /**
     * Create attempt stats for a failed attempt (without InferenceAttempt object).
     */
    public function calculateFailedAttemptStats(
        string $executionId,
        string $attemptId,
        int $attemptNumber,
        DateTimeImmutable $startedAt,
        Throwable $error,
        ?Usage $partialUsage = null,
        ?string $model = null,
        bool $isStreamed = false,
        ?float $timeToFirstChunkMs = null,
    ): InferenceAttemptStats {
        $completedAt = new DateTimeImmutable();
        $durationMs = $this->calculateDurationMs($startedAt, $completedAt);
        $usage = $partialUsage ?? Usage::none();

        return new InferenceAttemptStats(
            executionId: $executionId,
            attemptId: $attemptId,
            attemptNumber: $attemptNumber,
            startedAt: $startedAt,
            completedAt: $completedAt,
            durationMs: $durationMs,
            timeToFirstChunkMs: $timeToFirstChunkMs,
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            cacheReadTokens: $usage->cacheReadTokens,
            cacheWriteTokens: $usage->cacheWriteTokens,
            reasoningTokens: $usage->reasoningTokens,
            isSuccess: false,
            finishReason: 'error',
            errorMessage: $error->getMessage(),
            errorType: get_class($error),
            model: $model,
            isStreamed: $isStreamed,
        );
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function calculateDurationMs(DateTimeImmutable $start, DateTimeImmutable $end): float {
        $interval = $start->diff($end);
        return ($interval->s * 1000) + ($interval->f * 1000);
    }

    private function aggregateUsage(InferenceExecution $execution): Usage {
        return $execution->usage();
    }
}
