<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Telemetry\Application\Projector\Support\EventData;
use Cognesy\Instructor\Laravel\HttpClient\LaravelHttpResponseAdapter;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Response as LaravelResponse;

/**
 * The Laravel adapter used to dispatch `new HttpResponseChunkReceived($chunk)` with a
 * raw string, while the curl and PSR adapters dispatch
 * ['requestId' => ..., 'chunk' => ...].
 *
 * HttpClientTelemetryProjector::onChunkReceived() reads both keys off the payload and
 * returns early when either is null, so under the Laravel driver every streamed chunk
 * was silently invisible to telemetry and carried no correlation id.
 */

function laravelStreamingAdapter(EventDispatcher $events, string $body, string $requestId): LaravelHttpResponseAdapter {
    return new LaravelHttpResponseAdapter(
        response: new LaravelResponse(new PsrResponse(200, [], Utils::streamFor($body))),
        events: $events,
        streaming: true,
        streamChunkSize: 8,
        requestId: $requestId,
    );
}

it('dispatches chunk events with requestId and chunk keys, not a raw string', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(static function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $chunks = iterator_to_array(
        laravelStreamingAdapter($events, 'abcdefghijklmnop', 'req-laravel-1')->toHttpResponse()->stream(),
        false,
    );

    $chunkEvents = array_values(array_filter(
        $captured,
        static fn(object $event): bool => $event instanceof HttpResponseChunkReceived,
    ));

    expect(implode('', $chunks))->toBe('abcdefghijklmnop')
        ->and($chunkEvents)->not->toBeEmpty();

    foreach ($chunkEvents as $event) {
        expect($event->data)->toBeArray()
            ->and($event->data)->toHaveKeys(['requestId', 'chunk'])
            ->and($event->data['requestId'])->toBe('req-laravel-1');
    }

    expect(array_map(static fn(HttpResponseChunkReceived $e): string => $e->data['chunk'], $chunkEvents))
        ->toBe(['abcdefgh', 'ijklmnop', '']);
});

it('produces a payload the telemetry projector can read', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(static function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    iterator_to_array(
        laravelStreamingAdapter($events, 'telemetry-visible', 'req-laravel-2')->toHttpResponse()->stream(),
        false,
    );

    $chunkEvent = array_values(array_filter(
        $captured,
        static fn(object $event): bool => $event instanceof HttpResponseChunkReceived,
    ))[0];

    // These are the exact reads onChunkReceived() performs before deciding whether the
    // chunk is worth recording; both were null under the old raw-string payload.
    $data = EventData::of($chunkEvent);

    expect(EventData::string($data, 'requestId'))->toBe('req-laravel-2')
        ->and(EventData::string($data, 'chunk'))->toBe('telemetr');
});

it('skips chunk event construction when nothing listens', function () {
    $events = new EventDispatcher();

    expect($events->hasListenersFor(HttpResponseChunkReceived::class))->toBeFalse();

    $chunks = iterator_to_array(
        laravelStreamingAdapter($events, 'abcdefghijklmnop', 'req-laravel-3')->toHttpResponse()->stream(),
        false,
    );

    // Guarding must not change what the consumer receives.
    expect(implode('', $chunks))->toBe('abcdefghijklmnop');
});
