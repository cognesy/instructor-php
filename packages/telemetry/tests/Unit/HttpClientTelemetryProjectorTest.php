<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Events\HttpRequestSent;
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
    ]));

    $observation = httpRequestObservation($otel->observations());
    $attributes = $observation->attributes()->toArray();

    expect($observation->name())->toBe('http.client.request');
    expect($observation->status())->toBe(ObservationStatus::Ok);
    expect($attributes['http.stream.outcome'] ?? null)->toBe('abandoned');
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
        'error' => 'stream lost',
    ]));

    $observation = httpRequestObservation($otel->observations());
    $attributes = $observation->attributes()->toArray();

    expect($observation->name())->toBe('http.client.request');
    expect($observation->status())->toBe(ObservationStatus::Error);
    expect($attributes['http.stream.outcome'] ?? null)->toBe('failed');
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
