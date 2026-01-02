<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use DateTimeImmutable;

/**
 * Dispatched when the first chunk of a streaming response is received.
 *
 * Use for measuring Time-To-First-Chunk (TTFC) latency - the duration
 * from when the request was sent until the first data arrives.
 */
final class StreamFirstChunkReceived extends InferenceEvent
{
    public readonly DateTimeImmutable $receivedAt;
    public readonly float $timeToFirstChunkMs;

    public function __construct(
        public readonly string $executionId,
        DateTimeImmutable $requestStartedAt,
        public readonly ?string $model = null,
        public readonly ?string $initialContent = null,
    ) {
        $this->receivedAt = new DateTimeImmutable();
        $this->timeToFirstChunkMs = $this->calculateDurationMs($requestStartedAt, $this->receivedAt);

        parent::__construct([
            'executionId' => $this->executionId,
            'model' => $this->model,
            'timeToFirstChunkMs' => $this->timeToFirstChunkMs,
            'hasInitialContent' => $this->initialContent !== null && $this->initialContent !== '',
        ]);
    }

    private function calculateDurationMs(DateTimeImmutable $start, DateTimeImmutable $end): float {
        $interval = $start->diff($end);
        return ($interval->s * 1000) + ($interval->f * 1000);
    }

    #[\Override]
    public function __toString(): string {
        return sprintf(
            'First chunk received [%s] TTFC=%.2fms model=%s',
            $this->executionId,
            $this->timeToFirstChunkMs,
            $this->model ?? 'unknown'
        );
    }
}
