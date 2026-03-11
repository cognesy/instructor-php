<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Streaming\Sequence\SequenceTracker;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

if (!class_exists('FluctuationPerson')) {
    eval('class FluctuationPerson { public string $name; public int $age; }');
}

it('does not re-emit items when sequence count fluctuates due to partial JSON reparsing', function () {
    // Simulate chunks where partial JSON causes the item count to fluctuate.
    // Chunk 1: partial JSON with 1 complete item
    // Chunk 2: the buffer grows but partial parse drops the second item temporarily
    // Chunk 3: second item reappears, third starts
    // This triggers the SequenceTracker regression where emittedCount could go backwards.
    $chunks = [
        new PartialInferenceDelta(contentDelta: '{"list":[{"name":"Jason","age":25}'),
        new PartialInferenceDelta(contentDelta: ',{"name":"Ja'),
        new PartialInferenceDelta(contentDelta: 'ne","age":18}'),
        new PartialInferenceDelta(contentDelta: ',{"name":"John","age":30}'),
        new PartialInferenceDelta(contentDelta: ']}', finishReason: 'stop'),
    ];

    $driver = new FakeInferenceDriver(responses: [], streamBatches: [$chunks]);

    $stream = (new StructuredOutput(makeStructuredRuntime(
        driver: $driver,
        config: new StructuredOutputConfig(),
        outputMode: OutputMode::Json,
    )))
        ->with(
            messages: 'Extract people',
            responseModel: Sequence::of('FluctuationPerson'),
        )
        ->stream();

    $items = [];
    foreach ($stream->sequence() as $item) {
        $items[] = $item->name;
    }

    // Each person must appear exactly once
    expect($items)->toEqualCanonicalizing(['Jason', 'Jane', 'John']);
    expect(count($items))->toBe(3);
});

it('SequenceTracker never regresses emittedCount when count decreases', function () {
    // Unit-level test: simulate count fluctuation directly on the tracker
    $tracker = SequenceTracker::empty();

    // Sequence grows to 2 items: emit item 0 (confirmed)
    $seq2 = new Sequence();
    $seq2->push((object) ['name' => 'Jason']);
    $seq2->push((object) ['name' => 'Jane']);
    $result = $tracker->consume($seq2);
    expect($result->updates)->toHaveCount(1);
    expect($result->updates[0]->name)->toBe('Jason');
    $tracker = $result->tracker;

    // Sequence shrinks to 1 item (partial reparse): must NOT re-emit Jason
    $seq1 = new Sequence();
    $seq1->push((object) ['name' => 'Jason']);
    $result = $tracker->consume($seq1);
    expect($result->updates)->toHaveCount(0);
    $tracker = $result->tracker;

    // Sequence grows back to 2 items: must NOT re-emit Jason
    $seq2b = new Sequence();
    $seq2b->push((object) ['name' => 'Jason']);
    $seq2b->push((object) ['name' => 'Jane']);
    $result = $tracker->consume($seq2b);
    expect($result->updates)->toHaveCount(0); // Jason already emitted
    $tracker = $result->tracker;

    // Sequence grows to 3: emit Jane (confirmed), hold back John
    $seq3 = new Sequence();
    $seq3->push((object) ['name' => 'Jason']);
    $seq3->push((object) ['name' => 'Jane']);
    $seq3->push((object) ['name' => 'John']);
    $result = $tracker->consume($seq3);
    expect($result->updates)->toHaveCount(1);
    expect($result->updates[0]->name)->toBe('Jane');
    $tracker = $result->tracker;

    // Finalize: emit John
    $remaining = $tracker->finalize($seq3);
    expect($remaining)->toHaveCount(1);
    expect($remaining[0]->name)->toBe('John');
});
