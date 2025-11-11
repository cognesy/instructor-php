<?php declare(strict_types=1);

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Aggregation\StreamAggregate;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\PartialFrame;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Enums\Emission;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Events\EventTap;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;

function makeEventCollector(): CanHandleEvents {
    return new class implements CanHandleEvents {
        public array $events = [];

        public function dispatch(object $event): object {
            $this->events[] = $event;
            return $event;
        }

        public function wiretap(callable $callable): void {
        }

        public function getListenersForEvent(object $event): iterable {
            return [];
        }

        public function addListener(string $name, callable $listener, int $priority = 0): void {
        }
    };
}

function makePassThroughReducer(): Reducer {
    return new class implements Reducer {
        public function init(): mixed {
            return null;
        }

        public function step(mixed $accumulator, mixed $reducible): mixed {
            return $reducible;
        }

        public function complete(mixed $accumulator): mixed {
            return $accumulator;
        }
    };
}

test('dispatches ChunkReceived event for every frame', function() {
    $events = makeEventCollector();
    $reducer = new EventTap(
        inner: makePassThroughReducer(),
        events: $events,
    );

    $reducer->init();

    $frame = PartialFrame::fromResponse(
        new PartialInferenceResponse(contentDelta: 'test', usage: Usage::none())
    );

    $reducer->step(null, $frame);

    expect($events->events)->toHaveCount(1)
        ->and($events->events[0])->toBeInstanceOf(\Cognesy\Instructor\Events\PartialsGenerator\ChunkReceived::class);
});

test('dispatches PartialResponseGenerated for ObjectReady emission', function() {
    $events = makeEventCollector();
    $reducer = new EventTap(
        inner: makePassThroughReducer(),
        events: $events,
    );

    $reducer->init();

    $object = (object) ['key' => 'value'];
    $frame = PartialFrame::fromResponse(
        new PartialInferenceResponse(usage: Usage::none())
    )->withObject(Result::success($object))
     ->withEmission(Emission::ObjectReady);

    $reducer->step(null, $frame);

    $partialEvents = array_filter(
        $events->events,
        fn($e) => $e instanceof \Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated
    );

    expect($partialEvents)->toHaveCount(1);
});

test('does not dispatch PartialResponseGenerated for None emission', function() {
    $events = makeEventCollector();
    $reducer = new EventTap(
        inner: makePassThroughReducer(),
        events: $events,
    );

    $reducer->init();

    $frame = PartialFrame::fromResponse(
        new PartialInferenceResponse(usage: Usage::none())
    )->withEmission(Emission::None);

    $reducer->step(null, $frame);

    $partialEvents = array_filter(
        $events->events,
        fn($e) => $e instanceof \Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated
    );

    expect($partialEvents)->toBeEmpty();
});

test('dispatches StreamedToolCallStarted when tool call begins', function() {
    $events = makeEventCollector();
    $reducer = new EventTap(
        inner: makePassThroughReducer(),
        events: $events,
        expectedToolName: 'test_tool',
    );

    $reducer->init();

    // Tool name signal
    $frame = PartialFrame::fromResponse(
        new PartialInferenceResponse(toolName: 'test_tool', usage: Usage::none())
    );

    $reducer->step(null, $frame);

    $toolStartEvents = array_filter(
        $events->events,
        fn($e) => $e instanceof \Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallStarted
    );

    expect($toolStartEvents)->toHaveCount(1);
});

test('dispatches StreamedToolCallUpdated when args accumulate', function() {
    $events = makeEventCollector();
    $reducer = new EventTap(
        inner: makePassThroughReducer(),
        events: $events,
        expectedToolName: 'test_tool',
    );

    $reducer->init();

    // Start tool call
    $frame1 = PartialFrame::fromResponse(
        new PartialInferenceResponse(toolName: 'test_tool', usage: Usage::none())
    );
    $reducer->step(null, $frame1);

    // Add args
    $frame2 = PartialFrame::fromResponse(
        new PartialInferenceResponse(toolArgs: '{"param"', usage: Usage::none())
    );
    $reducer->step(null, $frame2);

    $toolUpdateEvents = array_filter(
        $events->events,
        fn($e) => $e instanceof \Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallUpdated
    );

    expect($toolUpdateEvents)->toHaveCount(1);
});

test('dispatches StreamedToolCallCompleted on finalize', function() {
    $events = makeEventCollector();
    $reducer = new EventTap(
        inner: makePassThroughReducer(),
        events: $events,
        expectedToolName: 'test_tool',
    );

    $reducer->init();

    // Start and add args
    $frame1 = PartialFrame::fromResponse(
        new PartialInferenceResponse(toolName: 'test_tool', usage: Usage::none())
    );
    $reducer->step(null, $frame1);

    $frame2 = PartialFrame::fromResponse(
        new PartialInferenceResponse(toolArgs: '{"param": "value"}', usage: Usage::none())
    );
    $reducer->step(null, $frame2);

    $reducer->complete(null);

    $toolCompleteEvents = array_filter(
        $events->events,
        fn($e) => $e instanceof \Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallCompleted
    );

    expect($toolCompleteEvents)->toHaveCount(1);
});

test('does not track tool calls when expectedToolName is empty', function() {
    $events = makeEventCollector();
    $reducer = new EventTap(
        inner: makePassThroughReducer(),
        events: $events,
        expectedToolName: '',
    );

    $reducer->init();

    $frame = PartialFrame::fromResponse(
        new PartialInferenceResponse(toolName: 'some_tool', usage: Usage::none())
    );

    $reducer->step(null, $frame);

    $toolEvents = array_filter(
        $events->events,
        fn($e) => $e instanceof \Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallStarted
    );

    expect($toolEvents)->toBeEmpty();
});

// NOTE: Sequence event tests require complex Sequenceable mock
// Sequence tracking is tested indirectly through integration tests

test('dispatches StreamedResponseReceived on complete with aggregate', function() {
    $events = makeEventCollector();
    $reducer = new EventTap(
        inner: makePassThroughReducer(),
        events: $events,
    );

    $reducer->init();

    $aggregate = StreamAggregate::empty();
    $reducer->complete($aggregate);

    $streamEvents = array_filter(
        $events->events,
        fn($e) => $e instanceof \Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived
    );

    expect($streamEvents)->toHaveCount(1);
});

test('init resets tracker state', function() {
    $events = makeEventCollector();
    $reducer = new EventTap(
        inner: makePassThroughReducer(),
        events: $events,
        expectedToolName: 'test_tool',
    );

    // First stream
    $reducer->init();
    $frame1 = PartialFrame::fromResponse(
        new PartialInferenceResponse(toolName: 'test_tool', usage: Usage::none())
    );
    $reducer->step(null, $frame1);

    $initialEventCount = count($events->events);

    // Reset
    $reducer->init();

    // Should start fresh
    $frame2 = PartialFrame::fromResponse(
        new PartialInferenceResponse(toolName: 'test_tool', usage: Usage::none())
    );
    $reducer->step(null, $frame2);

    // Should have new tool start event
    $toolStartEvents = array_filter(
        $events->events,
        fn($e) => $e instanceof \Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallStarted
    );

    expect(count($toolStartEvents))->toBe(2); // One from each stream
});

test('forwards frame unchanged to inner reducer', function() {
    $passthrough = makePassThroughReducer();
    $events = makeEventCollector();
    $reducer = new EventTap(
        inner: $passthrough,
        events: $events,
    );

    $reducer->init();

    $frame = PartialFrame::fromResponse(
        new PartialInferenceResponse(usage: Usage::none())
    );

    $result = $reducer->step(null, $frame);

    expect($result)->toBe($frame);
});
