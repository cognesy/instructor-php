<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;

/**
 * Tests verifying critical sequence behavior for MODULAR response iterator:
 * 1. partials() - yields MULTIPLE updates per sequence item (as chunks arrive)
 * 2. sequence() - yields SINGLE update per COMPLETED sequence item only
 */

if (!class_exists('ModularPerson')) {
    eval('class ModularPerson { public string $name; public int $age; }');
}

it('[modular] partials() yields multiple updates per sequence item as chunks arrive', function () {
    // Carefully crafted chunks that PARTIALLY build each item
    // Item 1: Built across chunks 1-2
    // Item 2: Built across chunks 2-3
    // Item 3: Built across chunks 3-4
    $chunks = [
        // Chunk 1: Start of sequence, partial first item (name only)
        new PartialInferenceDelta(contentDelta: '{"list":[{"name":"Alice"'),
        // Chunk 2: Complete first item, start second item (name only)
        new PartialInferenceDelta(contentDelta: ',"age":25},{"name":"Bob"'),
        // Chunk 3: Complete second item, start third item (name only)
        new PartialInferenceDelta(contentDelta: ',"age":30},{"name":"Carol"'),
        // Chunk 4: Complete third item and close sequence
        new PartialInferenceDelta(contentDelta: ',"age":35}]}', finishReason: 'stop'),
    ];

    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $chunks ]);

    $pending = (new StructuredOutput(makeStructuredRuntime(
        driver: $driver,
        config: new StructuredOutputConfig(),
        outputMode: OutputMode::Json,
    )))
        ->with(
            messages: 'test',
            responseModel: Sequence::of('ModularPerson'),
        );

    // Collect all partial updates
    $partialUpdates = [];
    foreach ($pending->stream()->partials() as $partial) {
        $partialUpdates[] = [
            'count' => $partial->count(),
            'items' => array_map(fn($item) => $item->name ?? 'incomplete', $partial->toArray()),
        ];
    }

    // EXPECTED BEHAVIOR: Multiple updates per item
    // - Chunk 1: Sequence(Alice incomplete)
    // - Chunk 2: Sequence(Alice complete, Bob incomplete)
    // - Chunk 3: Sequence(Alice, Bob complete, Carol incomplete)
    // - Chunk 4: Sequence(Alice, Bob, Carol complete)

    // We should see at least 4 updates (one per chunk minimum)
    expect(count($partialUpdates))->toBeGreaterThanOrEqual(4);

    // Verify we see partial states of items being built
    $allCounts = array_map(fn($u) => $u['count'], $partialUpdates);

    // Should see progression: growing sequence counts
    expect($allCounts)->toContain(2);
    expect($allCounts)->toContain(3);

    // Final update should have all 3 items
    $finalUpdate = end($partialUpdates);
    expect($finalUpdate['count'])->toBe(3);
    expect($finalUpdate['items'])->toBe(['Alice', 'Bob', 'Carol']);
})->group('sequence', 'streaming', 'critical', 'modular');

it('[modular] sequence() yields single update per COMPLETED sequence item only', function () {
    // Same chunks as previous test - items built across multiple chunks
    $chunks = [
        new PartialInferenceDelta(contentDelta: '{"list":[{"name":"Alice"'),
        new PartialInferenceDelta(contentDelta: ',"age":25},{"name":"Bob"'),
        new PartialInferenceDelta(contentDelta: ',"age":30},{"name":"Carol"'),
        new PartialInferenceDelta(contentDelta: ',"age":35}]}', finishReason: 'stop'),
    ];

    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $chunks ]);

    $pending = (new StructuredOutput(makeStructuredRuntime(
        driver: $driver,
        config: new StructuredOutputConfig(),
        outputMode: OutputMode::Json,
    )))
        ->with(
            messages: 'test',
            responseModel: Sequence::of('ModularPerson'),
        );

    // Collect individual completed items
    $completedItems = [];
    foreach ($pending->stream()->sequence() as $item) {
        $completedItems[] = $item->name;
    }

    // EXPECTED BEHAVIOR: One item yielded per COMPLETED entry
    // - Alice, Bob, Carol — each yielded individually when the next item starts

    expect(count($completedItems))->toBe(3);

    expect($completedItems[0])->toBe('Alice');
    expect($completedItems[1])->toBe('Bob');
    expect($completedItems[2])->toBe('Carol');
})->group('sequence', 'streaming', 'critical', 'modular');

it('[modular] sequence() does not yield incomplete items even if many chunks arrive', function () {
    // Extreme case: Many chunks for a single item, then another item starts
    $chunks = [
        new PartialInferenceDelta(contentDelta: '{"list":[{"na'),
        new PartialInferenceDelta(contentDelta: 'me":"Da'),
        new PartialInferenceDelta(contentDelta: 've","a'),
        new PartialInferenceDelta(contentDelta: 'ge":40}'),
        new PartialInferenceDelta(contentDelta: ',{"name":"Eve","age":45}]}', finishReason: 'stop'),
    ];

    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $chunks ]);

    $pending = (new StructuredOutput(makeStructuredRuntime(
        driver: $driver,
        config: new StructuredOutputConfig(),
        outputMode: OutputMode::Json,
    )))
        ->with(
            messages: 'test',
            responseModel: Sequence::of('ModularPerson'),
        );

    $completedItems = iterator_to_array($pending->stream()->sequence());

    // Despite 5 chunks, we should only get 2 completed items
    expect(count($completedItems))->toBe(2);

    // Individual completed items
    expect($completedItems[0]->name)->toBe('Dave');
    expect($completedItems[1]->name)->toBe('Eve');
})->group('sequence', 'streaming', 'critical', 'modular');

it('[modular] partials() yields MORE updates than sequence() for same stream', function () {
    // Realistic scenario: LLM streams JSON with varying chunk sizes
    $chunks = [
        new PartialInferenceDelta(contentDelta: '{"list":['),
        new PartialInferenceDelta(contentDelta: '{"name":"John",'),
        new PartialInferenceDelta(contentDelta: '"age":28},'),
        new PartialInferenceDelta(contentDelta: '{"name":"Jane"'),
        new PartialInferenceDelta(contentDelta: ',"age":32}'),
        new PartialInferenceDelta(contentDelta: ',{"name":"Jake","age":29}]}', finishReason: 'stop'),
    ];

    // Test partials()
    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $chunks ]);
    $pending = (new StructuredOutput(makeStructuredRuntime(
        driver: $driver,
        config: new StructuredOutputConfig(),
        outputMode: OutputMode::Json,
    )))
        ->with(
            messages: 'test',
            responseModel: Sequence::of('ModularPerson'),
        );

    $partialCount = 0;
    foreach ($pending->stream()->partials() as $partial) {
        $partialCount++;
    }

    // Test sequence()
    $driver2 = new FakeInferenceDriver(responses: [], streamBatches: [ $chunks ]);
    $pending2 = (new StructuredOutput(makeStructuredRuntime(
        driver: $driver2,
        config: new StructuredOutputConfig(),
        outputMode: OutputMode::Json,
    )))
        ->with(
            messages: 'test',
            responseModel: Sequence::of('ModularPerson'),
        );

    $sequenceCount = 0;
    foreach ($pending2->stream()->sequence() as $seq) {
        $sequenceCount++;
    }

    // Critical assertion: partials yields MORE updates than sequence
    expect($partialCount)->toBeGreaterThan($sequenceCount);

    // sequence() should yield exactly 3 (one per completed item)
    expect($sequenceCount)->toBe(3);

    // partials() should yield at least as many as chunks that produce parseable sequences
    expect($partialCount)->toBeGreaterThanOrEqual(3);
})->group('sequence', 'streaming', 'critical', 'modular');
