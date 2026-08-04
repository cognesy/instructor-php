<?php declare(strict_types=1);

use Cognesy\Events\Contracts\CanCheckListeners;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Drivers\Guzzle\PsrHttpResponseAdapter;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Http\Events\HttpStreamCompleted;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * HttpResponseChunkReceived is the argument to dispatch(), so it used to be built for
 * every chunk whether or not anything consumed it — 822 objects and 994 KB per 205 KB
 * SSE response, two thirds of it Uuid::uuid4()'s CSPRNG draw. Emitters now ask
 * CanCheckListeners once per stream.
 *
 * The guard may only suppress construction when the dispatcher positively answers
 * "nobody listens". A dispatcher that cannot answer must still receive every event.
 */

/** Records everything dispatched, and can answer the listener question either way. */
final class GuardProbeDispatcher implements EventDispatcherInterface, CanCheckListeners
{
    /** @var list<object> */
    public array $dispatched = [];
    public int $listenerChecks = 0;

    public function __construct(
        private readonly bool $hasListeners,
    ) {}

    #[\Override]
    public function dispatch(object $event): object {
        $this->dispatched[] = $event;

        return $event;
    }

    #[\Override]
    public function hasListenersFor(string $eventClass): bool {
        $this->listenerChecks++;

        return $this->hasListeners;
    }
}

/** Deliberately does NOT implement CanCheckListeners — the degradation path. */
final class GuardBlindDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    #[\Override]
    public function dispatch(object $event): object {
        $this->dispatched[] = $event;

        return $event;
    }
}

/** @param non-empty-string $body */
function psrAdapterFor(EventDispatcherInterface $events, string $body, int $chunkSize = 8): PsrHttpResponseAdapter {
    $stream = Utils::streamFor($body);

    return new PsrHttpResponseAdapter(
        response: new Response(200, [], $stream),
        stream: $stream,
        events: $events,
        isStreamed: true,
        requestId: 'req-guard',
        streamChunkSize: $chunkSize,
    );
}

/** @return list<HttpResponseChunkReceived> */
function chunkEventsIn(array $dispatched): array {
    return array_values(array_filter(
        $dispatched,
        static fn(object $event): bool => $event instanceof HttpResponseChunkReceived,
    ));
}

it('does not construct chunk events when the dispatcher reports no listeners', function () {
    $events = new GuardProbeDispatcher(hasListeners: false);
    $chunks = iterator_to_array(psrAdapterFor($events, 'abcdefghijklmnop')->toHttpResponse()->stream(), false);

    expect(implode('', $chunks))->toBe('abcdefghijklmnop')
        ->and(chunkEventsIn($events->dispatched))->toBe([])
        // Asked once for the whole stream, not once per chunk.
        ->and($events->listenerChecks)->toBe(1)
        // The completion event is not guarded — it fires once and is cheap.
        ->and(array_filter($events->dispatched, static fn(object $e): bool => $e instanceof HttpStreamCompleted))
        ->toHaveCount(1);
});

// 16 bytes read 8 at a time: neither read is short, so the underlying EOF flag never
// gets set and the adapter makes a third read that returns ''. The trailing empty
// chunk is existing behaviour (instructor-f7g5), asserted here so the guard is not
// blamed for it later.
const GUARD_EXPECTED_CHUNKS = ['abcdefgh', 'ijklmnop', ''];

it('still emits chunk events when the dispatcher reports listeners', function () {
    $events = new GuardProbeDispatcher(hasListeners: true);
    iterator_to_array(psrAdapterFor($events, 'abcdefghijklmnop')->toHttpResponse()->stream(), false);

    $chunkEvents = chunkEventsIn($events->dispatched);

    expect(array_map(static fn(HttpResponseChunkReceived $e): string => $e->data['chunk'], $chunkEvents))
        ->toBe(GUARD_EXPECTED_CHUNKS)
        ->and($chunkEvents[0]->data['requestId'])->toBe('req-guard');
});

it('emits chunk events when the dispatcher cannot answer the listener question', function () {
    $events = new GuardBlindDispatcher();
    iterator_to_array(psrAdapterFor($events, 'abcdefghijklmnop')->toHttpResponse()->stream(), false);

    expect(array_map(
        static fn(HttpResponseChunkReceived $e): string => $e->data['chunk'],
        chunkEventsIn($events->dispatched),
    ))->toBe(GUARD_EXPECTED_CHUNKS);
});

it('emits chunk events for a real dispatcher once a wiretap is attached', function () {
    $events = new EventDispatcher();
    $captured = [];

    expect($events->hasListenersFor(HttpResponseChunkReceived::class))->toBeFalse();

    $events->wiretap(static function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    expect($events->hasListenersFor(HttpResponseChunkReceived::class))->toBeTrue();

    iterator_to_array(psrAdapterFor($events, 'abcdefghijklmnop')->toHttpResponse()->stream(), false);

    expect(chunkEventsIn($captured))->toHaveCount(count(GUARD_EXPECTED_CHUNKS));
});
