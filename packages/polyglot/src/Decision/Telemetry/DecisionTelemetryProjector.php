<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Telemetry;

use Cognesy\Metrics\Data\Counter;
use Cognesy\Metrics\Data\Timer;
use Cognesy\Polyglot\Decision\Events\DecisionAttemptFailed;
use Cognesy\Polyglot\Decision\Events\DecisionAttemptStarted;
use Cognesy\Polyglot\Decision\Events\DecisionAttemptSucceeded;
use Cognesy\Polyglot\Decision\Events\DecisionCompleted;
use Cognesy\Polyglot\Decision\Events\DecisionFailed;
use Cognesy\Polyglot\Decision\Events\DecisionStarted;
use Cognesy\Polyglot\Telemetry\TelemetryEnvelopeProjector;
use Cognesy\Telemetry\Application\Projector\CanProjectTelemetry;
use Cognesy\Telemetry\Application\Projector\Support\EventData;
use Cognesy\Telemetry\Application\Telemetry;

final readonly class DecisionTelemetryProjector implements CanProjectTelemetry
{
    private TelemetryEnvelopeProjector $envelopes;

    public function __construct(private Telemetry $telemetry)
    {
        $this->envelopes = new TelemetryEnvelopeProjector($telemetry);
    }

    #[\Override]
    public function project(object $event): void
    {
        match (true) {
            $event instanceof DecisionStarted => $this->onStarted($event),
            $event instanceof DecisionCompleted => $this->onCompleted($event),
            $event instanceof DecisionFailed => $this->onFailed($event),
            $event instanceof DecisionAttemptStarted => $this->onAttemptStarted($event),
            $event instanceof DecisionAttemptSucceeded => $this->onAttemptSucceeded($event),
            $event instanceof DecisionAttemptFailed => $this->onAttemptFailed($event),
            default => null,
        };
    }

    private function onStarted(DecisionStarted $event): void
    {
        $envelope = EventData::telemetry(EventData::of($event));
        if ($envelope === null) {
            return;
        }
        $this->envelopes->open($envelope, $this->envelopes->attributes([
            'sdm.decision.execution.id' => $event->executionId,
            'sdm.decision.request.id' => $event->requestId,
            'sdm.decision.model' => $event->model,
            'sdm.decision.driver' => $event->driver,
            'sdm.decision.primitive_count' => $event->primitiveCount,
        ]));
    }

    private function onCompleted(DecisionCompleted $event): void
    {
        $this->metrics('operation', 'success', $event->durationMs, [
            'sdm.model' => $event->model,
            'sdm.driver' => $event->driver,
        ]);
        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);
        if ($envelope === null) {
            return;
        }
        $this->envelopes->complete($envelope, $this->envelopes->attributes([
            'sdm.decision.model' => $event->model,
            'sdm.decision.attempt_count' => $event->attemptCount,
            'sdm.decision.duration_ms' => $event->durationMs,
            'sdm.decision.tokens.input' => EventData::int($data, 'inputTokens'),
            'sdm.decision.tokens.output' => EventData::int($data, 'outputTokens'),
            'sdm.decision.tokens.total' => EventData::int($data, 'totalTokens'),
        ]));
    }

    private function onFailed(DecisionFailed $event): void
    {
        $this->metrics('operation', 'failure', $event->durationMs, [
            'sdm.model' => $event->model,
            'sdm.driver' => $event->driver,
            'error.type' => $event->errorType,
            'http.response.status_code' => $event->httpStatusCode,
        ]);
        $envelope = EventData::telemetry(EventData::of($event));
        if ($envelope === null) {
            return;
        }
        $this->envelopes->fail($envelope, $this->envelopes->attributes([
            'sdm.decision.model' => $event->model,
            'sdm.decision.attempt_count' => $event->attemptCount,
            'sdm.decision.duration_ms' => $event->durationMs,
            'error.type' => $event->errorType,
            'http.response.status_code' => $event->httpStatusCode,
        ]));
    }

    private function onAttemptStarted(DecisionAttemptStarted $event): void
    {
        $envelope = EventData::telemetry(EventData::of($event));
        if ($envelope === null) {
            return;
        }
        $this->envelopes->open($envelope, $this->envelopes->attributes([
            'sdm.decision.execution.id' => $event->executionId,
            'sdm.decision.attempt.id' => $event->attemptId,
            'sdm.decision.attempt_number' => $event->attemptNumber,
            'sdm.decision.model' => $event->model,
            'sdm.decision.driver' => $event->driver,
            'sdm.decision.retry' => $event->isRetry(),
        ]));
    }

    private function onAttemptSucceeded(DecisionAttemptSucceeded $event): void
    {
        $this->metrics('attempt', 'success', $event->durationMs, []);
        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);
        if ($envelope === null) {
            return;
        }
        $this->envelopes->complete($envelope, $this->envelopes->attributes([
            'sdm.decision.duration_ms' => $event->durationMs,
            'sdm.decision.tokens.input' => EventData::int($data, 'inputTokens'),
            'sdm.decision.tokens.output' => EventData::int($data, 'outputTokens'),
            'sdm.decision.tokens.total' => EventData::int($data, 'totalTokens'),
        ]));
    }

    private function onAttemptFailed(DecisionAttemptFailed $event): void
    {
        $this->metrics('attempt', 'failure', $event->durationMs, [
            'error.type' => $event->errorType,
            'http.response.status_code' => $event->httpStatusCode,
            'sdm.retry' => $event->willRetry,
        ]);
        $envelope = EventData::telemetry(EventData::of($event));
        if ($envelope === null) {
            return;
        }
        $this->envelopes->fail($envelope, $this->envelopes->attributes([
            'sdm.decision.duration_ms' => $event->durationMs,
            'error.type' => $event->errorType,
            'http.response.status_code' => $event->httpStatusCode,
            'sdm.decision.retry' => $event->willRetry,
        ]));
    }

    /** @param array<string, string|int|float|bool|null> $tags */
    private function metrics(string $scope, string $outcome, float $durationMs, array $tags): void
    {
        $resolved = array_filter([...$tags, 'sdm.outcome' => $outcome], static fn ($value): bool => $value !== null);
        $this->telemetry->metric(Counter::create("sdm.decision.{$scope}.count", 1, $resolved));
        if ($durationMs >= 0) {
            $this->telemetry->metric(Timer::create("sdm.decision.{$scope}.duration", $durationMs, $resolved));
        }
    }
}
