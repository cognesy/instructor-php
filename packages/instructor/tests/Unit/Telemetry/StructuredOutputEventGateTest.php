<?php declare(strict_types=1);

use Cognesy\Events\Contracts\CanCheckListeners;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputRequestReceived;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseUpdated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

/**
 * The structured-output lifecycle emitters must not assemble event payloads the dispatcher
 * reports nobody wants, and must still emit everything when the dispatcher cannot report its
 * listeners at all.
 *
 * The payload builders are private, so they cannot be overridden to count calls. What is
 * observable is the dispatch itself: the payload is the event's constructor argument, so an
 * event that was never dispatched is a payload that was never built. These dispatchers
 * therefore record what reaches them and answer `hasListenersFor()` from a fixed set.
 */

/** Reports listeners only for the classes it was given, and records every dispatch. */
final class GateProbeDispatcher implements CanHandleEvents, CanCheckListeners
{
    /** @var list<class-string> */
    public array $dispatched = [];

    /** @param list<class-string> $wanted */
    public function __construct(private readonly array $wanted = []) {}

    public function dispatch(object $event): object {
        $this->dispatched[] = $event::class;
        return $event;
    }

    #[\Override]
    public function hasListenersFor(string $eventClass): bool {
        return in_array($eventClass, $this->wanted, true);
    }

    public function addListener(string $name, callable $listener, int $priority = 0): void {}
    public function wiretap(callable $listener): void {}
    public function getListenersForEvent(object $event): iterable { return []; }

    /** @param class-string $eventClass */
    public function sawAny(string $eventClass): bool {
        return in_array($eventClass, $this->dispatched, true);
    }
}

/**
 * A dispatcher that cannot report its listeners. Fail-open is contractual: it must be
 * assumed to listen and must receive every event.
 */
final class OpaqueGateProbeDispatcher implements CanHandleEvents
{
    /** @var list<class-string> */
    public array $dispatched = [];

    public function dispatch(object $event): object {
        $this->dispatched[] = $event::class;
        return $event;
    }

    public function addListener(string $name, callable $listener, int $priority = 0): void {}
    public function wiretap(callable $listener): void {}
    public function getListenersForEvent(object $event): iterable { return []; }

    /** @param class-string $eventClass */
    public function sawAny(string $eventClass): bool {
        return in_array($eventClass, $this->dispatched, true);
    }
}

final class GateProbeDto
{
    public int $count = 0;

    public static function of(int $count): self {
        $dto = new self();
        $dto->count = $count;
        return $dto;
    }
}

function runSoLifecycleRequest(CanHandleEvents $events): void {
    $runtime = makeStructuredRuntime(
        driver: new FakeInferenceDriver([new InferenceResponse(content: '{"count":7}')]),
        events: $events,
        outputMode: OutputMode::Json,
    );
    (new StructuredOutput($runtime))
        ->with(messages: 'Extract a count.', responseModel: GateProbeDto::class)
        ->get();
}

function runSoLifecycleStream(CanHandleEvents $events): void {
    $runtime = makeStructuredRuntime(
        driver: new FakeInferenceDriver(
            responses: [],
            streamBatches: [[
                new PartialInferenceDelta(value: GateProbeDto::of(1)),
                new PartialInferenceDelta(value: GateProbeDto::of(2)),
                new PartialInferenceDelta(value: GateProbeDto::of(3)),
            ]],
        ),
        events: $events,
        outputMode: OutputMode::Json,
    );
    $stream = (new StructuredOutput($runtime))
        ->with(messages: 'Extract a count.', responseModel: GateProbeDto::class)
        ->withStreaming()
        ->stream();
    foreach ($stream->partials() as $_) {
        // drain
    }
}

it('builds no structured-output lifecycle payload when nothing listens', function () {
    $events = new GateProbeDispatcher(wanted: []);

    runSoLifecycleRequest($events);

    expect($events->sawAny(StructuredOutputRequestReceived::class))->toBeFalse()
        ->and($events->sawAny(StructuredOutputStarted::class))->toBeFalse()
        ->and($events->sawAny(StructuredOutputResponseGenerated::class))->toBeFalse();
})->group('telemetry');

it('gates each lifecycle event independently', function (string $wanted) {
    $events = new GateProbeDispatcher(wanted: [$wanted]);

    runSoLifecycleRequest($events);

    // The requested one arrives; its two siblings do not. This is what proves the gates are
    // per-event rather than one shared "is anyone listening at all" flag.
    expect($events->sawAny($wanted))->toBeTrue();
    foreach ([
        StructuredOutputRequestReceived::class,
        StructuredOutputStarted::class,
        StructuredOutputResponseGenerated::class,
    ] as $other) {
        if ($other !== $wanted) {
            expect($events->sawAny($other))->toBeFalse();
        }
    }
})->with([
    StructuredOutputRequestReceived::class,
    StructuredOutputStarted::class,
    StructuredOutputResponseGenerated::class,
])->group('telemetry');

it('builds no per-emission update payload when nothing listens for it', function () {
    // The hot one: updated() runs once per delta, and its payload walks usage, tool calls
    // and two strlen() over the accumulated content.
    $events = new GateProbeDispatcher(wanted: [StructuredOutputStarted::class]);

    runSoLifecycleStream($events);

    expect($events->sawAny(StructuredOutputStarted::class))->toBeTrue()
        ->and($events->sawAny(StructuredOutputResponseUpdated::class))->toBeFalse();
})->group('telemetry');

it('emits every per-emission update when someone does listen', function () {
    $events = new GateProbeDispatcher(wanted: [StructuredOutputResponseUpdated::class]);

    runSoLifecycleStream($events);

    $updates = array_filter(
        $events->dispatched,
        static fn(string $c): bool => $c === StructuredOutputResponseUpdated::class,
    );
    // One per yielded partial. The gate must not swallow any of them.
    expect(count($updates))->toBe(3)
        ->and($events->sawAny(StructuredOutputStarted::class))->toBeFalse();
})->group('telemetry');

it('fails open for a dispatcher that cannot report its listeners', function () {
    // Contractual: a dispatcher without CanCheckListeners never loses an event to this
    // optimisation, even though it has no listeners registered at all.
    $events = new OpaqueGateProbeDispatcher();

    runSoLifecycleRequest($events);

    expect($events->sawAny(StructuredOutputRequestReceived::class))->toBeTrue()
        ->and($events->sawAny(StructuredOutputStarted::class))->toBeTrue()
        ->and($events->sawAny(StructuredOutputResponseGenerated::class))->toBeTrue();
})->group('telemetry');

it('fails open on the streaming path too', function () {
    $events = new OpaqueGateProbeDispatcher();

    runSoLifecycleStream($events);

    expect($events->sawAny(StructuredOutputStarted::class))->toBeTrue()
        ->and($events->sawAny(StructuredOutputResponseUpdated::class))->toBeTrue();
})->group('telemetry');

it('observes listeners registered on the runtime after it was constructed', function () {
    // StructuredOutputRuntime is long-lived and onEvent() mutates it, so its gate is
    // resolved per request rather than at construction. A constructor-resolved bool would
    // silently drop this event for every caller who uses the API as documented.
    $events = new Cognesy\Events\Dispatchers\EventDispatcher();
    $runtime = makeStructuredRuntime(
        driver: new FakeInferenceDriver([new InferenceResponse(content: '{"count":7}')]),
        events: $events,
        outputMode: OutputMode::Json,
    );

    $seen = 0;
    $runtime->onEvent(StructuredOutputRequestReceived::class, function () use (&$seen): void { $seen++; });

    (new StructuredOutput($runtime))
        ->with(messages: 'Extract a count.', responseModel: GateProbeDto::class)
        ->get();

    expect($seen)->toBe(1);
})->group('telemetry');
