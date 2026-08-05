<?php declare(strict_types=1);

use Cognesy\Events\Contracts\CanCheckListeners;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\Events\PartialInferenceDeltaCreated;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * InferenceStream resolves its event gates once in the constructor. This covers the
 * per-delta PartialInferenceDeltaCreated gate and the once-per-stream
 * InferenceResponseCreated gate -- the latter was the last unguarded dispatch of that
 * event in the package.
 *
 * Dispatchers are anonymous so these helpers cannot collide with the identically
 * shaped ones in DriverEventListenerGateTest, which Pest loads into the same process.
 */

function streamGateSilentDispatcher(): object
{
    return new class implements EventDispatcherInterface, CanCheckListeners {
        /** @var list<object> */
        public array $dispatched = [];

        public function dispatch(object $event): object {
            $this->dispatched[] = $event;
            return $event;
        }

        #[\Override]
        public function hasListenersFor(string $eventClass): bool {
            return false;
        }
    };
}

function streamGateOpaqueDispatcher(): object
{
    return new class implements EventDispatcherInterface {
        /** @var list<object> */
        public array $dispatched = [];

        public function dispatch(object $event): object {
            $this->dispatched[] = $event;
            return $event;
        }
    };
}

function drainStreamWith(EventDispatcherInterface $events): void
{
    $stream = new InferenceStream(
        execution: InferenceExecution::fromRequest(new InferenceRequest(
            messages: Messages::fromString('hello'),
            model: 'gpt-stream-gate',
        )),
        driver: new FakeInferenceDriver(
            streamBatches: [[
                new PartialInferenceDelta(contentDelta: 'He', usage: new InferenceUsage(outputTokens: 1)),
                new PartialInferenceDelta(contentDelta: 'llo', finishReason: 'stop', usage: new InferenceUsage(outputTokens: 2)),
            ]],
        ),
        eventDispatcher: $events,
    );

    foreach ($stream->deltas() as $_delta) {
    }
}

/** @param list<object> $dispatched */
function dispatchedClasses(array $dispatched): array
{
    return array_map(static fn(object $e): string => $e::class, $dispatched);
}

it('emits neither the delta event nor the response event when nothing listens', function () {
    $events = streamGateSilentDispatcher();

    drainStreamWith($events);

    expect(dispatchedClasses($events->dispatched))
        ->not->toContain(PartialInferenceDeltaCreated::class)
        ->not->toContain(InferenceResponseCreated::class);
});

it('emits both to a dispatcher that cannot report its listeners', function () {
    $events = streamGateOpaqueDispatcher();

    drainStreamWith($events);

    expect(dispatchedClasses($events->dispatched))
        ->toContain(PartialInferenceDeltaCreated::class)
        ->toContain(InferenceResponseCreated::class);
});

it('emits both when listeners are actually registered', function () {
    $events = new EventDispatcher();
    $deltas = 0;
    $responses = 0;
    $events->addListener(PartialInferenceDeltaCreated::class, function () use (&$deltas): void { $deltas++; });
    $events->addListener(InferenceResponseCreated::class, function () use (&$responses): void { $responses++; });

    drainStreamWith($events);

    expect($deltas)->toBeGreaterThan(0)
        ->and($responses)->toBe(1);
});

it('carries the memoized execution id on the streamed response event', function () {
    $events = new EventDispatcher();
    $captured = null;
    $events->addListener(InferenceResponseCreated::class, function (InferenceResponseCreated $e) use (&$captured): void {
        $captured = $e;
    });

    $execution = InferenceExecution::fromRequest(new InferenceRequest(
        messages: Messages::fromString('hello'),
        model: 'gpt-stream-gate',
    ));

    $stream = new InferenceStream(
        execution: $execution,
        driver: new FakeInferenceDriver(
            streamBatches: [[
                new PartialInferenceDelta(contentDelta: 'Hello', finishReason: 'stop', usage: new InferenceUsage(outputTokens: 2)),
            ]],
        ),
        eventDispatcher: $events,
    );

    foreach ($stream->deltas() as $_delta) {
    }

    expect($captured)->not->toBeNull()
        ->and($captured->data['executionId'])->toBe($execution->id->toString());
});
