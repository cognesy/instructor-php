<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Telemetry;

use Cognesy\Retrieval\Events\RetrievalCompleted;
use Cognesy\Retrieval\Events\RetrievalFailed;
use Cognesy\Retrieval\Events\RetrievalStarted;
use Cognesy\Telemetry\Application\Projector\CanProjectTelemetry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Value\AttributeBag;
use Override;

final readonly class RetrievalTelemetryProjector implements CanProjectTelemetry
{
    public function __construct(private Telemetry $telemetry) {}

    #[Override]
    public function project(object $event): void
    {
        match (true) {
            $event instanceof RetrievalStarted => $this->started($event),
            $event instanceof RetrievalCompleted => $this->completed($event),
            $event instanceof RetrievalFailed => $this->failed($event),
            default => null,
        };
    }

    private function started(RetrievalStarted $event): void
    {
        if ($this->telemetry->spanReference($event->executionId) !== null) {
            return;
        }
        $this->telemetry->openRoot(
            key: $event->executionId,
            name: 'retrieval.query',
            attributes: AttributeBag::fromArray([
                'retrieval.execution.id' => $event->executionId,
                'retrieval.request.id' => $event->requestId,
                'retrieval.driver' => $event->driver,
                'retrieval.query.type' => $event->queryType,
                'retrieval.limit' => $event->limit,
            ]),
        );
    }

    private function completed(RetrievalCompleted $event): void
    {
        $this->telemetry->complete($event->executionId, AttributeBag::fromArray([
            'retrieval.hit_count' => $event->hitCount,
            'retrieval.duration_ms' => $event->durationMs,
        ]));
    }

    private function failed(RetrievalFailed $event): void
    {
        $this->telemetry->fail($event->executionId, AttributeBag::fromArray([
            'retrieval.error.type' => $event->errorType,
            'retrieval.duration_ms' => $event->durationMs,
        ]));
    }
}
