<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use DateTimeImmutable;

/**
 * Dispatched when an inference request completes (success or failure).
 * Contains timing information for latency measurement.
 */
final class InferenceCompleted extends InferenceEvent
{
    public readonly DateTimeImmutable $completedAt;
    public readonly float $durationMs;

    public function __construct(
        public readonly string $executionId,
        public readonly bool $isSuccess,
        public readonly InferenceFinishReason $finishReason,
        public readonly Usage $usage,
        public readonly int $attemptCount,
        DateTimeImmutable $startedAt,
        public readonly ?InferenceResponse $response = null,
    ) {
        $this->completedAt = new DateTimeImmutable();
        $this->durationMs = $this->calculateDurationMs($startedAt, $this->completedAt);

        parent::__construct([
            'executionId' => $this->executionId,
            'isSuccess' => $this->isSuccess,
            'finishReason' => $this->finishReason->value,
            'durationMs' => $this->durationMs,
            'attemptCount' => $this->attemptCount,
            'usage' => $this->usage->toArray(),
        ]);
    }

    private function calculateDurationMs(DateTimeImmutable $start, DateTimeImmutable $end): float {
        $interval = $start->diff($end);
        return ($interval->s * 1000) + ($interval->f * 1000);
    }

    #[\Override]
    public function __toString(): string {
        return sprintf(
            'Inference completed [%s] success=%s duration=%.2fms tokens=%d attempts=%d',
            $this->executionId,
            $this->isSuccess ? 'true' : 'false',
            $this->durationMs,
            $this->usage->total(),
            $this->attemptCount
        );
    }
}
