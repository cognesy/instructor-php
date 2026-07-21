<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Events\HttpStreamCompleted;
use Cognesy\Http\Telemetry\HttpClientTelemetryProjector;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Exporter\CompositeTelemetryExporter;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Observation\Observation;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;
use Cognesy\Telemetry\Domain\Trace\TraceContext;

it('projects completed streamed requests with terminal stats and without response body', function () {
    $otel = new OtelExporter();
    $telemetry = new Telemetry(new TraceRegistry(), new CompositeTelemetryExporter([$otel]));
    $events = new EventDispatcher('http.telemetry.projector.test');
    (new RuntimeEventBridge(new HttpClientTelemetryProjector($telemetry)))->attachTo($events);

    $context = TraceContext::fresh();

    $events->dispatch(new HttpRequestSent([
        'requestId' => 'http-0',
        'url' => 'https://example.test/stream',
        'method' => 'GET',
        'headers' => ['traceparent' => $context->traceparent()],
    ]));
    $events->dispatch(new HttpResponseReceived([
        'requestId' => 'http-0',
        'statusCode' => 200,
        'isStreamed' => true,
    ]));
    $events->dispatch(new HttpStreamCompleted([
        'requestId' => 'http-0',
        'outcome' => 'completed',
        'bytes' => 512,
        'chunks' => 4,
    ]));

    $observation = httpRequestObservation($otel->observations());
    $attributes = $observation->attributes()->toArray();

    expect($observation->name())->toBe('http.client.request');
    expect($observation->status())->toBe(ObservationStatus::Ok);
    expect($attributes['http.stream.outcome'] ?? null)->toBe('completed');
    expect($attributes['http.stream.bytes'] ?? null)->toBe(512);
    expect($attributes['http.stream.chunks'] ?? null)->toBe(4);
    expect(array_key_exists('http.response.body', $attributes))->toBeFalse();
});

it('projects synchronous response size without attaching the response body', function () {
    $otel = new OtelExporter();
    $telemetry = new Telemetry(new TraceRegistry(), new CompositeTelemetryExporter([$otel]));
    $events = new EventDispatcher('http.telemetry.projector.test');
    (new RuntimeEventBridge(new HttpClientTelemetryProjector($telemetry)))->attachTo($events);

    $context = TraceContext::fresh();
    $events->dispatch(new HttpRequestSent([
        'requestId' => 'http-sync-body',
        'url' => 'https://example.test',
        'method' => 'POST',
        'requestBodyBytes' => 12,
        'headers' => ['traceparent' => $context->traceparent()],
    ]));
    $events->dispatch(new HttpResponseReceived([
        'requestId' => 'http-sync-body',
        'statusCode' => 200,
        'isStreamed' => false,
        'responseBodyBytes' => 14,
        'body' => 'secret-response-body',
    ]));

    $attributes = httpRequestObservation($otel->observations())->attributes()->toArray();

    expect($attributes['http.request.body.size'] ?? null)->toBe(12)
        ->and($attributes['http.response.body.size'] ?? null)->toBe(14)
        ->and(array_key_exists('http.response.body', $attributes))->toBeFalse();
});

it('bounds opt-in streamed chunk capture', function () {
    $otel = new OtelExporter();
    $telemetry = new Telemetry(new TraceRegistry(), new CompositeTelemetryExporter([$otel]));
    $events = new EventDispatcher('http.telemetry.projector.test');
    (new RuntimeEventBridge(new HttpClientTelemetryProjector(
        telemetry: $telemetry,
        captureStreamingChunks: true,
        maxCapturedStreamingBytes: 10,
    )))->attachTo($events);

    $context = TraceContext::fresh();
    $events->dispatch(new HttpRequestSent([
        'requestId' => 'http-capped-chunks',
        'url' => 'https://example.test/stream',
        'method' => 'GET',
        'headers' => ['traceparent' => $context->traceparent()],
    ]));
    $events->dispatch(new HttpResponseReceived([
        'requestId' => 'http-capped-chunks',
        'statusCode' => 200,
        'isStreamed' => true,
    ]));
    $events->dispatch(new HttpResponseChunkReceived([
        'requestId' => 'http-capped-chunks',
        'chunk' => '12345678',
    ]));
    $events->dispatch(new HttpResponseChunkReceived([
        'requestId' => 'http-capped-chunks',
        'chunk' => 'abcdefgh',
    ]));
    $events->dispatch(new HttpStreamCompleted([
        'requestId' => 'http-capped-chunks',
        'outcome' => 'completed',
        'bytes' => 16,
        'chunks' => 2,
    ]));

    $logs = array_values(array_filter(
        $otel->observations(),
        static fn($observation): bool => $observation->name() === 'http.response.chunk',
    ));
    $captured = array_map(
        static fn($observation): string => (string) ($observation->attributes()->toArray()['http.response.body'] ?? ''),
        $logs,
    );

    expect($captured)->toBe(['12345678', 'ab'])
        ->and(array_sum(array_map(strlen(...), $captured)))->toBe(10)
        ->and($logs[1]->attributes()->toArray()['http.response.body.truncated'] ?? null)->toBeTrue();
});

it('redacts opt-in streamed chunk capture', function () {
    $otel = new OtelExporter();
    $telemetry = new Telemetry(new TraceRegistry(), new CompositeTelemetryExporter([$otel]));
    $events = new EventDispatcher('http.telemetry.projector.test');
    (new RuntimeEventBridge(new HttpClientTelemetryProjector(
        telemetry: $telemetry,
        captureStreamingChunks: true,
        maxCapturedStreamingBytes: 128,
    )))->attachTo($events);

    $context = TraceContext::fresh();
    $events->dispatch(new HttpRequestSent([
        'requestId' => 'http-redacted-chunks',
        'url' => 'https://example.test/stream',
        'method' => 'GET',
        'headers' => ['traceparent' => $context->traceparent()],
    ]));
    $events->dispatch(new HttpResponseReceived([
        'requestId' => 'http-redacted-chunks',
        'statusCode' => 200,
        'isStreamed' => true,
    ]));
    $events->dispatch(new HttpResponseChunkReceived([
        'requestId' => 'http-redacted-chunks',
        'chunk' => '{"token":"response-secret"}',
    ]));

    $logs = array_values(array_filter(
        $otel->observations(),
        static fn($observation): bool => $observation->name() === 'http.response.chunk',
    ));
    $body = (string) ($logs[0]->attributes()->toArray()['http.response.body'] ?? '');

    expect($body)->toContain('[REDACTED]')
        ->and($body)->not->toContain('response-secret');
});

it('projects abandoned streamed requests without attaching a response body', function () {
    $otel = new OtelExporter();
    $telemetry = new Telemetry(new TraceRegistry(), new CompositeTelemetryExporter([$otel]));
    $events = new EventDispatcher('http.telemetry.projector.test');
    (new RuntimeEventBridge(new HttpClientTelemetryProjector($telemetry)))->attachTo($events);

    $context = TraceContext::fresh();

    $events->dispatch(new HttpRequestSent([
        'requestId' => 'http-1',
        'url' => 'https://example.test/stream',
        'method' => 'GET',
        'headers' => ['traceparent' => $context->traceparent()],
    ]));
    $events->dispatch(new HttpResponseReceived([
        'requestId' => 'http-1',
        'statusCode' => 200,
        'isStreamed' => true,
    ]));
    $events->dispatch(new HttpStreamCompleted([
        'requestId' => 'http-1',
        'outcome' => 'abandoned',
        'bytes' => 128,
        'chunks' => 1,
    ]));

    $observation = httpRequestObservation($otel->observations());
    $attributes = $observation->attributes()->toArray();

    expect($observation->name())->toBe('http.client.request');
    expect($observation->status())->toBe(ObservationStatus::Ok);
    expect($attributes['http.stream.outcome'] ?? null)->toBe('abandoned');
    expect($attributes['http.stream.bytes'] ?? null)->toBe(128);
    expect($attributes['http.stream.chunks'] ?? null)->toBe(1);
    expect(array_key_exists('http.response.body', $attributes))->toBeFalse();
});

it('projects failed streamed requests as error observations', function () {
    $otel = new OtelExporter();
    $telemetry = new Telemetry(new TraceRegistry(), new CompositeTelemetryExporter([$otel]));
    $events = new EventDispatcher('http.telemetry.projector.test');
    (new RuntimeEventBridge(new HttpClientTelemetryProjector($telemetry)))->attachTo($events);

    $context = TraceContext::fresh();

    $events->dispatch(new HttpRequestSent([
        'requestId' => 'http-2',
        'url' => 'https://example.test/stream',
        'method' => 'GET',
        'headers' => ['traceparent' => $context->traceparent()],
    ]));
    $events->dispatch(new HttpResponseReceived([
        'requestId' => 'http-2',
        'statusCode' => 200,
        'isStreamed' => true,
    ]));
    $events->dispatch(new HttpStreamCompleted([
        'requestId' => 'http-2',
        'outcome' => 'failed',
        'bytes' => 256,
        'chunks' => 2,
        'error' => 'stream lost',
    ]));

    $observation = httpRequestObservation($otel->observations());
    $attributes = $observation->attributes()->toArray();

    expect($observation->name())->toBe('http.client.request');
    expect($observation->status())->toBe(ObservationStatus::Error);
    expect($attributes['http.stream.outcome'] ?? null)->toBe('failed');
    expect($attributes['http.stream.bytes'] ?? null)->toBe(256);
    expect($attributes['http.stream.chunks'] ?? null)->toBe(2);
    expect($attributes['error.message'] ?? null)->toBe('stream lost');
});

/**
 * @param array<int, Observation> $observations
 */
function httpRequestObservation(array $observations): Observation
{
    foreach ($observations as $observation) {
        if ($observation->name() === 'http.client.request') {
            return $observation;
        }
    }

    throw new RuntimeException('Missing http.client.request observation');
}
