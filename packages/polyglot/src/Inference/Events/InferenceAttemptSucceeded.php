<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use DateTimeImmutable;

/**
 * Dispatched when an inference attempt completes successfully.
 */
final class InferenceAttemptSucceeded extends InferenceEvent
{
    public readonly DateTimeImmutable $succeededAt;
    public readonly float $durationMs;

    public function __construct(
        public readonly string $executionId,
        public readonly string $attemptId,
        public readonly int $attemptNumber,
        public readonly InferenceFinishReason $finishReason,
        public readonly Usage $usage,
        ?DateTimeImmutable $startedAt = null,
    ) {
        $this->succeededAt = new DateTimeImmutable();
        $this->durationMs = $startedAt !== null
            ? $this->calculateDurationMs($startedAt, $this->succeededAt)
            : 0.0;

        parent::__construct([
            'executionId' => $this->executionId,
            'attemptId' => $this->attemptId,
            'attemptNumber' => $this->attemptNumber,
            'finishReason' => $this->finishReason->value,
            'durationMs' => $this->durationMs,
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
            'Attempt #%d succeeded [%s] duration=%.2fms tokens=%d',
            $this->attemptNumber,
            $this->attemptId,
            $this->durationMs,
            $this->usage->total()
        );
    }
}
