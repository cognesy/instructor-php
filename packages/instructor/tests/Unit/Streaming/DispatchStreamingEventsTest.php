<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Events\PartialsGenerator\ChunkReceived;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallStarted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallUpdated;
use Cognesy\Instructor\Events\Request\SequenceUpdated;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Streaming\StructuredOutputStreamState;
use Cognesy\Instructor\Streaming\Pipeline\DispatchStreamingEvents;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Stream\Transformation;
use Cognesy\Stream\TransformationStream;

it('dispatches sequence events from parsed partial values and final completion', function () {
    $events = new EventDispatcher();
    $recorded = [];
    $events->wiretap(static function (object $event) use (&$recorded): void {
        $recorded[] = $event;
    });

    $first = new Sequence();
    $first->push((object) ['name' => 'Ann']);

    $second = new Sequence();
    $second->push((object) ['name' => 'Ann']);
    $second->push((object) ['name' => 'Bob']);

    $responses = [
        stateSnapshot(value: $first),
        stateSnapshot(value: $second, finishReason: 'stop'),
    ];

    iterator_to_array(
        TransformationStream::from($responses)->using(Transformation::define(
            new DispatchStreamingEvents($events),
        )),
        false,
    );

    expect(eventCount($recorded, ChunkReceived::class))->toBe(2);
    expect(eventCount($recorded, PartialResponseGenerated::class))->toBe(2);
    expect(eventCount($recorded, SequenceUpdated::class))->toBe(2);
    expect(eventCount($recorded, StreamedResponseReceived::class))->toBe(1);

    $sequenceEvents = array_values(array_filter(
        $recorded,
        static fn(object $event): bool => $event instanceof SequenceUpdated,
    ));

    expect($sequenceEvents[0]->completedIndex)->toBe(0);
    expect(count($sequenceEvents[1]->sequence))->toBe(2);
});

it('dispatches tool lifecycle events for streamed tool calls', function () {
    $events = new EventDispatcher();
    $recorded = [];
    $events->wiretap(static function (object $event) use (&$recorded): void {
        $recorded[] = $event;
    });

    $responses = [
        stateSnapshot(toolId: 'tool-1', toolName: 'extract_data', toolArgs: '{"name":"Ann"'),
        stateSnapshot(
            toolId: 'tool-1',
            toolName: 'extract_data',
            prior: [new PartialInferenceDelta(toolId: 'tool-1', toolName: 'extract_data', toolArgs: '{"name":"Ann"')],
            toolArgs: ',"age":30}',
        ),
    ];

    iterator_to_array(
        TransformationStream::from($responses)->using(Transformation::define(
            new DispatchStreamingEvents($events, 'extract_data'),
        )),
        false,
    );

    expect(eventCount($recorded, StreamedToolCallStarted::class))->toBe(1);
    expect(eventCount($recorded, StreamedToolCallUpdated::class))->toBe(2);
    expect(eventCount($recorded, StreamedToolCallCompleted::class))->toBe(1);
    expect(eventCount($recorded, StreamedResponseReceived::class))->toBe(1);
});

function eventCount(array $events, string $class): int {
    return count(array_filter(
        $events,
        static fn(object $event): bool => $event instanceof $class,
    ));
}

/**
 * @param list<PartialInferenceDelta> $prior
 */
function stateSnapshot(
    mixed $value = null,
    string $finishReason = '',
    string $toolId = '',
    string $toolName = '',
    string $toolArgs = '',
    array $prior = [],
): StructuredOutputStreamState {
    $state = StructuredOutputStreamState::empty();

    foreach ($prior as $delta) {
        $state->applyDelta($delta);
    }

    if ($toolId !== '' || $toolName !== '' || $toolArgs !== '' || $finishReason !== '') {
        $state->applyDelta(new PartialInferenceDelta(
            toolId: $toolId,
            toolName: $toolName,
            toolArgs: $toolArgs,
            finishReason: $finishReason,
        ));
    }

    if ($value !== null) {
        $state->setValue($value);
    }

    return $state;
}
