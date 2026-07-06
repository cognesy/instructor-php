<?php declare(strict_types=1);

use Cognesy\Events\Contracts\CanCheckListeners;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Events\Streaming\ChunkReceived;
use Cognesy\Instructor\Events\Streaming\PartialResponseGenerated;
use Cognesy\Instructor\Events\Streaming\StreamedResponseReceived;
use Cognesy\Instructor\Streaming\Pipeline\DispatchStreamingEvents;
use Cognesy\Stream\Transformation;
use Cognesy\Stream\TransformationStream;

/**
 * Deterministic coverage for streaming event gating: when the dispatcher
 * reports no listeners for an event class (CanCheckListeners), the reducer
 * skips dispatching that event family entirely; dispatchers without
 * listener introspection get the previous always-dispatch behavior.
 */

/** Records every dispatch; introspection result is fixed at construction. */
function gatingRecordingDispatcher(bool $hasListeners, array &$recorded): CanHandleEvents {
    return new class($hasListeners, $recorded) implements CanHandleEvents, CanCheckListeners {
        public function __construct(
            private bool $hasListeners,
            private array &$recorded,
        ) {}

        public function dispatch(object $event): object {
            $this->recorded[] = $event;
            return $event;
        }

        public function hasListenersFor(string $eventClass): bool {
            return $this->hasListeners;
        }

        public function addListener(string $name, callable $listener, int $priority = 0): void {}
        public function wiretap(callable $listener): void {}
        public function getListenersForEvent(object $event): iterable { return []; }
    };
}

/** A dispatcher WITHOUT CanCheckListeners — gating must not apply. */
function gatingBlindDispatcher(array &$recorded): CanHandleEvents {
    return new class($recorded) implements CanHandleEvents {
        public function __construct(private array &$recorded) {}

        public function dispatch(object $event): object {
            $this->recorded[] = $event;
            return $event;
        }

        public function addListener(string $name, callable $listener, int $priority = 0): void {}
        public function wiretap(callable $listener): void {}
        public function getListenersForEvent(object $event): iterable { return []; }
    };
}

function runGatedStream(CanHandleEvents $events): void {
    $responses = [
        stateSnapshot(value: (object) ['name' => 'Ann']),
        stateSnapshot(value: (object) ['name' => 'Ann Lee'], finishReason: 'stop'),
    ];

    iterator_to_array(
        TransformationStream::from($responses)->using(Transformation::define(
            new DispatchStreamingEvents($events),
        )),
        false,
    );
}

it('skips all streaming events when the dispatcher reports no listeners', function () {
    $recorded = [];
    runGatedStream(gatingRecordingDispatcher(hasListeners: false, recorded: $recorded));

    expect($recorded)->toBe([]);
});

it('dispatches all streaming events when the dispatcher reports listeners', function () {
    $recorded = [];
    runGatedStream(gatingRecordingDispatcher(hasListeners: true, recorded: $recorded));

    expect(eventCount($recorded, ChunkReceived::class))->toBe(2);
    expect(eventCount($recorded, PartialResponseGenerated::class))->toBe(2);
    expect(eventCount($recorded, StreamedResponseReceived::class))->toBe(1);
});

it('dispatches all streaming events when the dispatcher has no listener introspection', function () {
    $recorded = [];
    runGatedStream(gatingBlindDispatcher($recorded));

    expect(eventCount($recorded, ChunkReceived::class))->toBe(2);
    expect(eventCount($recorded, PartialResponseGenerated::class))->toBe(2);
    expect(eventCount($recorded, StreamedResponseReceived::class))->toBe(1);
});

it('gates event families independently by registered listener class', function () {
    $events = new EventDispatcher();
    $partials = [];
    $events->addListener(PartialResponseGenerated::class, static function (object $event) use (&$partials): void {
        $partials[] = $event;
    });

    runGatedStream($events);

    // listener-backed family flows; nothing else was silently required for it
    expect($partials)->toHaveCount(2);
});
