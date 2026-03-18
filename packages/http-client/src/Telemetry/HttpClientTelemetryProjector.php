<?php declare(strict_types=1);

namespace Cognesy\Http\Telemetry;

use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Events\HttpStreamCompleted;
use Cognesy\Telemetry\Application\Projector\CanProjectTelemetry;
use Cognesy\Telemetry\Application\Projector\Support\EventData;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Envelope\OperationKind;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelopeAttributes;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;
use Cognesy\Telemetry\Domain\Trace\TraceContext;
use Cognesy\Telemetry\Domain\Value\AttributeBag;

final readonly class HttpClientTelemetryProjector implements CanProjectTelemetry
{
    public function __construct(
        private Telemetry $telemetry,
        private bool $captureStreamingChunks = false,
    ) {}

    #[\Override]
    public function project(object $event): void
    {
        match (true) {
            $event instanceof HttpRequestSent => $this->onRequestSent($event),
            $event instanceof HttpResponseReceived => $this->onResponseReceived($event),
            $event instanceof HttpResponseChunkReceived => $this->onChunkReceived($event),
            $event instanceof HttpStreamCompleted => $this->onStreamCompleted($event),
            $event instanceof HttpRequestFailed => $this->onRequestFailed($event),
            default => null,
        };
    }

    private function onRequestSent(HttpRequestSent $event): void
    {
        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);
        if ($envelope !== null) {
            $this->openEnvelope($envelope, $this->requestAttributes($data));
            return;
        }

        $requestId = EventData::string($data, 'requestId');
        if ($requestId === null || $this->telemetry->spanReference($requestId) !== null) {
            return;
        }

        $headers = EventData::array($data, 'headers');
        $traceparent = is_string($headers['traceparent'] ?? null) ? $headers['traceparent'] : null;
        $tracestate = is_string($headers['tracestate'] ?? null) ? $headers['tracestate'] : null;
        $context = match ($traceparent) {
            null => null,
            default => TraceContext::fromTraceparent($traceparent, $tracestate),
        };

        $this->telemetry->openRoot(
            key: $requestId,
            name: 'http.client.request',
            context: $context,
            attributes: $this->requestAttributes($data),
        );
    }

    private function onResponseReceived(HttpResponseReceived $event): void
    {
        $data = EventData::of($event);
        $isStreamed = EventData::bool($data, 'isStreamed') ?? false;

        if ($isStreamed) {
            // Keep span open — HttpStreamCompleted will close it.
            // Log the status code as an event so it's visible during streaming.
            $requestId = EventData::string($data, 'requestId');
            if ($requestId === null || $this->telemetry->spanReference($requestId) === null) {
                return;
            }
            $statusCode = EventData::int($data, 'statusCode');
            if ($statusCode !== null) {
                $this->telemetry->log(
                    $requestId,
                    'http.response.status',
                    $this->attributes(['http.response.status_code' => $statusCode]),
                );
            }
            return;
        }

        // Non-streaming: close span now with status + body.
        $envelope = EventData::telemetry($data);
        if ($envelope !== null) {
            $this->telemetry->complete(
                $envelope->operation()->id(),
                $this->envelopeAttributes($envelope)->merge($this->responseAttributes($data)),
            );
            return;
        }

        $requestId = EventData::string($data, 'requestId');
        if ($requestId === null) {
            return;
        }
        $this->telemetry->complete($requestId, $this->responseAttributes($data));
    }

    private function onChunkReceived(HttpResponseChunkReceived $event): void
    {
        if (!$this->captureStreamingChunks) {
            return;
        }

        $data = EventData::of($event);
        $requestId = EventData::string($data, 'requestId');
        $chunk = EventData::string($data, 'chunk');

        if ($requestId === null || $chunk === null || $chunk === '') {
            return;
        }
        if ($this->telemetry->spanReference($requestId) === null) {
            return;
        }

        $this->telemetry->log(
            $requestId,
            'http.response.chunk',
            $this->attributes(['http.response.body' => $chunk]),
        );
    }

    private function onStreamCompleted(HttpStreamCompleted $event): void
    {
        $data = EventData::of($event);
        $requestId = EventData::string($data, 'requestId');

        if ($requestId === null || $this->telemetry->spanReference($requestId) === null) {
            return;
        }

        $body = EventData::string($data, 'body');
        $outcome = EventData::string($data, 'outcome') ?? 'completed';
        $attributes = match ($outcome) {
            'failed' => $this->attributes([
                'http.stream.outcome' => 'failed',
                'error.message' => EventData::string($data, 'error'),
            ]),
            'abandoned' => $this->attributes(['http.stream.outcome' => 'abandoned']),
            default => match ($body) {
                null => AttributeBag::empty(),
                '' => AttributeBag::empty(),
                default => $this->attributes(['http.response.body' => $body]),
            },
        };

        match ($outcome) {
            'failed' => $this->telemetry->fail($requestId, $attributes),
            default => $this->telemetry->complete($requestId, $attributes),
        };
    }

    private function onRequestFailed(HttpRequestFailed $event): void
    {
        $data = EventData::of($event);
        $envelope = EventData::telemetry($data);
        if ($envelope !== null) {
            $this->telemetry->fail(
                $envelope->operation()->id(),
                $this->envelopeAttributes($envelope)->merge($this->failureAttributes($data)),
            );
            return;
        }

        $requestId = EventData::string($data, 'requestId');
        if ($requestId === null) {
            return;
        }

        $this->telemetry->fail($requestId, $this->failureAttributes($data));
    }

    private function requestAttributes(array $data): AttributeBag
    {
        $attributes = AttributeBag::empty();
        $method = EventData::string($data, 'method');
        $url = EventData::string($data, 'url');

        $attributes = match ($method) {
            null => $attributes,
            default => $attributes->with('http.request.method', $method),
        };

        return match ($url) {
            null => $attributes,
            default => $attributes->with('url.full', $url),
        };
    }

    private function responseAttributes(array $data): AttributeBag
    {
        $statusCode = EventData::int($data, 'statusCode');
        $body = EventData::string($data, 'body');

        $attributes = AttributeBag::empty();

        if ($statusCode !== null) {
            $attributes = $attributes->with('http.response.status_code', $statusCode);
        }
        if ($body !== null) {
            $attributes = $attributes->with('http.response.body', $body);
        }

        return $attributes;
    }

    private function failureAttributes(array $data): AttributeBag
    {
        $attributes = $this->requestAttributes($data);
        $statusCode = EventData::int($data, 'statusCode');
        $error = EventData::string($data, 'errors') ?? EventData::string($data, 'error');

        $attributes = match ($statusCode) {
            null => $attributes,
            default => $attributes->with('http.response.status_code', $statusCode),
        };

        return match ($error) {
            null => $attributes,
            default => $attributes->with('error.message', $error),
        };
    }

    /** @param array<string, scalar|array<array-key, scalar>|null> $items */
    private function attributes(array $items): AttributeBag
    {
        return AttributeBag::fromArray(array_filter($items, static fn(mixed $v): bool => $v !== null));
    }

    private function openEnvelope(TelemetryEnvelope $envelope, AttributeBag $attributes): void
    {
        if ($this->telemetry->spanReference($envelope->operation()->id()) !== null) {
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

        match ($envelope->operation()->kind()) {
            OperationKind::RootSpan => $this->telemetry->openRoot(
                key: $envelope->operation()->id(),
                name: $envelope->operation()->name(),
                context: $envelope->trace(),
                attributes: $this->envelopeAttributes($envelope)->merge($attributes),
            ),
            OperationKind::Span => match ($resolvedParent) {
                null => $this->telemetry->openRoot(
                    key: $envelope->operation()->id(),
                    name: $envelope->operation()->name(),
                    context: $envelope->trace(),
                    attributes: $this->envelopeAttributes($envelope)->merge($attributes),
                ),
                default => $this->telemetry->openChild(
                    key: $envelope->operation()->id(),
                    parentKey: $resolvedParent,
                    name: $envelope->operation()->name(),
                    attributes: $this->envelopeAttributes($envelope)->merge($attributes),
                ),
            },
            default => null,
        };
    }

    private function envelopeAttributes(TelemetryEnvelope $envelope): AttributeBag
    {
        return TelemetryEnvelopeAttributes::fromEnvelope($envelope);
    }
}
