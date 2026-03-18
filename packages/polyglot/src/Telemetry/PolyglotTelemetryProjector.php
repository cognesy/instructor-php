<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Telemetry;

use Cognesy\Polyglot\Embeddings\Events\EmbeddingsFailed;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsRequested;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsResponseReceived;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStarted;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceFailed;
use Cognesy\Polyglot\Inference\Events\InferenceStarted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;
use Cognesy\Telemetry\Application\Projector\CanProjectTelemetry;
use Cognesy\Telemetry\Application\Projector\Support\EventData;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelopeAttributes;
use Cognesy\Metrics\Data\Histogram;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;
use Cognesy\Telemetry\Domain\Value\AttributeBag;

final readonly class PolyglotTelemetryProjector implements CanProjectTelemetry
{
    public function __construct(
        private Telemetry $telemetry,
    ) {}

    #[\Override]
    public function project(object $event): void
    {
        match (true) {
            $event instanceof InferenceStarted => $this->onInferenceStarted($event),
            $event instanceof InferenceCompleted => $this->onInferenceCompleted($event),
            $event instanceof InferenceFailed => $this->onInferenceFailed($event),
            $event instanceof InferenceAttemptStarted => $this->onInferenceAttemptStarted($event),
            $event instanceof InferenceAttemptSucceeded => $this->onInferenceAttemptSucceeded($event),
            $event instanceof InferenceAttemptFailed => $this->onInferenceAttemptFailed($event),
            $event instanceof InferenceUsageReported => $this->onInferenceUsageReported($event),
            $event instanceof EmbeddingsRequested => $this->onEmbeddingsRequested($event),
            $event instanceof EmbeddingsResponseReceived => $this->onEmbeddingsResponseReceived($event),
            $event instanceof EmbeddingsFailed => $this->onEmbeddingsFailed($event),
            default => null,
        };
    }

    private function onInferenceStarted(InferenceStarted $event): void
    {
        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);

        if ($envelope !== null) {
            $this->openEnvelope($envelope, $this->attributes([
                'inference.request.id' => EventData::string($data, 'requestId'),
                'inference.response.model' => EventData::string($data, 'model'),
                'inference.request.is_streamed' => EventData::bool($data, 'isStreamed'),
                'inference.request.message_count' => EventData::int($data, 'messageCount'),
            ]));

            return;
        }

        $executionId = EventData::string($data, 'executionId');
        if ($executionId === null || $this->telemetry->spanReference($executionId) !== null) {
            return;
        }

        $this->telemetry->openRoot(
            key: $executionId,
            name: 'llm.inference',
            attributes: $this->attributes([
                'inference.execution.id' => $executionId,
                'inference.request.id' => EventData::string($data, 'requestId'),
                'inference.response.model' => EventData::string($data, 'model'),
                'inference.request.is_streamed' => EventData::bool($data, 'isStreamed'),
                'inference.request.message_count' => EventData::int($data, 'messageCount'),
            ]),
        );
    }

    private function onInferenceCompleted(InferenceCompleted $event): void
    {
        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);

        if ($envelope !== null) {
            $this->completeEnvelope($envelope, $this->attributes([
                'inference.finish_reason' => EventData::string($data, 'finishReason'),
                'inference.attempt_count' => EventData::int($data, 'attemptCount'),
                'inference.duration_ms' => EventData::float($data, 'durationMs'),
                'inference.tokens.total' => EventData::int($data, 'totalTokens'),
            ]));

            return;
        }

        $executionId = EventData::string($data, 'executionId');
        if ($executionId === null) {
            return;
        }

        $attributes = $this->attributes([
            'inference.finish_reason' => EventData::string($data, 'finishReason'),
            'inference.attempt_count' => EventData::int($data, 'attemptCount'),
            'inference.duration_ms' => EventData::float($data, 'durationMs'),
            'inference.tokens.total' => EventData::int($data, 'totalTokens'),
        ]);

        $this->telemetry->complete($executionId, $attributes);
    }

    private function onInferenceFailed(InferenceFailed $event): void
    {
        $data = EventData::of($event);
        $executionId = EventData::string($data, 'executionId');

        match ($executionId) {
            null => $this->telemetry->log(
                key: 'polyglot.inference.failure',
                name: 'llm.inference.failure',
                attributes: $this->attributes([
                    'error.message' => EventData::string($data, 'exception'),
                    'error.context' => EventData::string($data, 'context'),
                    'http.response.status_code' => EventData::int($data, 'statusCode'),
                ]),
                status: ObservationStatus::Error,
            ),
            default => $this->telemetry->fail(
                $executionId,
                $this->attributes([
                    'error.message' => EventData::string($data, 'exception'),
                    'error.context' => EventData::string($data, 'context'),
                    'http.response.status_code' => EventData::int($data, 'statusCode'),
                ]),
            ),
        };
    }

    private function onInferenceAttemptStarted(InferenceAttemptStarted $event): void
    {
        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);

        if ($envelope !== null) {
            $this->openEnvelope($envelope, $this->attributes([
                'inference.attempt_number' => EventData::int($data, 'attemptNumber'),
                'inference.response.model' => EventData::string($data, 'model'),
                'inference.retry' => EventData::bool($data, 'isRetry'),
            ]));

            return;
        }

        $executionId = EventData::string($data, 'executionId');
        $attemptId = EventData::string($data, 'attemptId');

        if ($executionId === null || $attemptId === null) {
            return;
        }

        $this->telemetry->openChild(
            key: $attemptId,
            parentKey: $executionId,
            name: 'llm.inference.attempt',
            attributes: $this->attributes([
                'inference.execution.id' => $executionId,
                'inference.attempt_number' => EventData::int($data, 'attemptNumber'),
                'inference.response.model' => EventData::string($data, 'model'),
                'inference.retry' => EventData::bool($data, 'isRetry'),
            ]),
        );
    }

    private function onInferenceAttemptSucceeded(InferenceAttemptSucceeded $event): void
    {
        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);

        if ($envelope !== null) {
            $this->completeEnvelope($envelope, $this->attributes([
                'inference.finish_reason' => EventData::string($data, 'finishReason'),
                'inference.duration_ms' => EventData::float($data, 'durationMs'),
                'inference.tokens.total' => EventData::int($data, 'totalTokens'),
            ]));

            return;
        }

        $attemptId = EventData::string($data, 'attemptId');
        if ($attemptId === null) {
            return;
        }

        $this->telemetry->complete($attemptId, $this->attributes([
            'inference.finish_reason' => EventData::string($data, 'finishReason'),
            'inference.duration_ms' => EventData::float($data, 'durationMs'),
            'inference.tokens.total' => EventData::int($data, 'totalTokens'),
        ]));
    }

    private function onInferenceAttemptFailed(InferenceAttemptFailed $event): void
    {
        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);

        if ($envelope !== null) {
            $this->failEnvelope($envelope, $this->attributes([
                'error.message' => EventData::string($data, 'exception'),
                'http.response.status_code' => EventData::int($data, 'httpStatusCode'),
                'inference.retry' => EventData::bool($data, 'willRetry'),
            ]));

            return;
        }

        $attemptId = EventData::string($data, 'attemptId');
        if ($attemptId === null) {
            return;
        }

        $this->telemetry->fail($attemptId, $this->attributes([
            'error.message' => EventData::string($data, 'exception'),
            'http.response.status_code' => EventData::int($data, 'httpStatusCode'),
            'inference.retry' => EventData::bool($data, 'willRetry'),
        ]));
    }

    private function onInferenceUsageReported(InferenceUsageReported $event): void
    {
        $data = EventData::of($event);
        $attributes = $this->attributes([
            'inference.execution.id' => EventData::string($data, 'executionId'),
            'inference.response.model' => EventData::string($data, 'model'),
            'inference.usage.final' => EventData::bool($data, 'isFinal'),
        ]);

        $this->emitMetric('inference.client.token.usage.input', EventData::int($data, 'inputTokens'), $attributes);
        $this->emitMetric('inference.client.token.usage.output', EventData::int($data, 'outputTokens'), $attributes);
        $this->emitMetric('inference.client.token.usage.total', EventData::int($data, 'totalTokens'), $attributes);
    }

    private function onEmbeddingsRequested(EmbeddingsRequested $event): void
    {
        $data = EventData::of($event);
        $request = EventData::array($data, 'request');
        $requestId = 'inference.embeddings:' . (EventData::string($request, 'model') ?? 'default');

        if ($this->telemetry->spanReference($requestId) !== null) {
            return;
        }

        $this->telemetry->openRoot(
            key: $requestId,
            name: 'inference.embeddings',
            attributes: $this->attributes([
                'inference.request.id' => $requestId,
                'inference.response.model' => EventData::string($request, 'model'),
                'inference.input_count' => is_array($request['inputs'] ?? null) ? count($request['inputs']) : null,
            ]),
        );
    }

    private function onEmbeddingsResponseReceived(EmbeddingsResponseReceived $event): void
    {
        $data = EventData::of($event);
        $model = EventData::string($data, 'model');
        $attributes = $this->attributes([
            'inference.execution.id' => EventData::string($data, 'executionId'),
            'inference.response.model' => $model,
            'inference.vector_count' => EventData::int($data, 'vectorCount'),
            'inference.vector_dimensions' => EventData::int($data, 'dimensions'),
        ]);

        $requestKey = $model === null ? 'inference.embeddings' : 'inference.embeddings:' . $model;
        if ($this->telemetry->spanReference($requestKey) !== null) {
            $this->telemetry->complete($requestKey, $attributes);
        }

        $usage = EventData::array($data, 'usage');
        $this->emitMetric('inference.client.token.usage.total', EventData::int($usage, 'total'), $attributes);
    }

    private function onEmbeddingsFailed(EmbeddingsFailed $event): void
    {
        $data = EventData::of($event);

        $this->telemetry->log(
            key: 'inference.embeddings.failure',
            name: 'inference.embeddings.failure',
            attributes: $this->attributes([
                'error.message' => EventData::string($data, 'exception'),
                'http.response.status_code' => EventData::int($data, 'statusCode'),
            ]),
            status: ObservationStatus::Error,
        );
    }

    /** @param array<string, scalar|array<array-key, scalar>|null> $items */
    private function attributes(array $items): AttributeBag
    {
        return AttributeBag::fromArray(array_filter($items, static fn(mixed $value): bool => $value !== null));
    }

    private function emitMetric(string $name, ?int $value, AttributeBag $attributes): void
    {
        if ($value === null) {
            return;
        }

        $this->telemetry->metric(Histogram::create($name, $value, $attributes->toArray()));
    }

    private function openEnvelope(TelemetryEnvelope $envelope, AttributeBag $attributes): void
    {
        $operation = $envelope->operation();

        if ($this->telemetry->spanReference($operation->id()) !== null) {
            return;
        }

        $correlation = $envelope->correlation();
        $parentKey = $correlation->parentOperationId() ?? $correlation->rootOperationId();
        $rootKey = $correlation->rootOperationId();
        $resolvedParent = match (true) {
            $this->telemetry->spanReference($parentKey) !== null => $parentKey,
            $parentKey !== $rootKey && $this->telemetry->spanReference($rootKey) !== null => $rootKey,
            default => null,
        };

        match ($operation->kind()) {
            OperationKind::RootSpan => $this->telemetry->openRoot(
                key: $operation->id(),
                name: $operation->name(),
                context: $envelope->trace(),
                attributes: $this->envelopeAttributes($envelope)->merge($attributes),
            ),
            OperationKind::Span => match ($resolvedParent) {
                null => $this->telemetry->openRoot(
                    key: $operation->id(),
                    name: $operation->name(),
                    context: $envelope->trace(),
                    attributes: $this->envelopeAttributes($envelope)->merge($attributes),
                ),
                default => $this->telemetry->openChild(
                    key: $operation->id(),
                    parentKey: $resolvedParent,
                    name: $operation->name(),
                    attributes: $this->envelopeAttributes($envelope)->merge($attributes),
                ),
            },
            OperationKind::Event => match ($resolvedParent) {
                null => null,
                default => $this->telemetry->log(
                    key: $resolvedParent,
                    name: $operation->name(),
                    attributes: $this->envelopeAttributes($envelope)->merge($attributes),
                ),
            },
            default => null,
        };
    }

    private function completeEnvelope(TelemetryEnvelope $envelope, AttributeBag $attributes): void
    {
        $this->telemetry->complete(
            $envelope->operation()->id(),
            $this->envelopeAttributes($envelope)->merge($attributes),
        );
    }

    private function failEnvelope(TelemetryEnvelope $envelope, AttributeBag $attributes): void
    {
        $this->telemetry->fail(
            $envelope->operation()->id(),
            $this->envelopeAttributes($envelope)->merge($attributes),
        );
    }

    private function envelopeAttributes(TelemetryEnvelope $envelope): AttributeBag
    {
        return TelemetryEnvelopeAttributes::fromEnvelope($envelope);
    }
}
