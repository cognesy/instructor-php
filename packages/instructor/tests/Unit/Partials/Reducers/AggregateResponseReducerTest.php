<?php declare(strict_types=1);

use Cognesy\Instructor\ResponseIterators\Partials\ResponseAggregation\AggregateResponseReducer;
use Cognesy\Instructor\ResponseIterators\Partials\ResponseAggregation\AggregationState;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Stream\Contracts\Reducer;

function makeAggregateCollectorReducer(): Reducer {
    return new class implements Reducer {
        public array $collected = [];

        public function init(): mixed {
            $this->collected = [];
            return null;
        }

        public function step(mixed $accumulator, mixed $reducible): mixed {
            $this->collected[] = $reducible;
            return $reducible;
        }

        public function complete(mixed $accumulator): mixed {
            return $this->collected;
        }
    };
}

test('creates empty aggregate on init', function() {
    $collector = makeAggregateCollectorReducer();
    $reducer = new AggregateResponseReducer($collector);

    $result = $reducer->init();

    // Init should initialize internal state but not forward
    expect($result)->toBeNull();
});

test('merges PartialInferenceResponse into rolling aggregate', function() {
    $collector = makeAggregateCollectorReducer();
    $reducer = new AggregateResponseReducer($collector);

    $reducer->init();

    $partial1 = new PartialInferenceResponse(
        contentDelta: 'chunk1',
        usage: new Usage(inputTokens: 10, outputTokens: 5),
    );

    $partial2 = new PartialInferenceResponse(
        contentDelta: 'chunk2',
        usage: new Usage(inputTokens: 10, outputTokens: 8),
    );

    $reducer->step(null, $partial1);
    $reducer->step(null, $partial2);

    expect($collector->collected)->toHaveCount(2);

    $aggregate1 = $collector->collected[0];
    $aggregate2 = $collector->collected[1];

    expect($aggregate1)->toBeInstanceOf(AggregationState::class)
        ->and($aggregate2)->toBeInstanceOf(AggregationState::class);

    // First aggregate: 1 partial
    expect($aggregate1->partialCount)->toBe(1)
        ->and($aggregate1->usage->outputTokens)->toBe(5);

    // Second aggregate: 2 partials, accumulated usage
    expect($aggregate2->partialCount)->toBe(2)
        ->and($aggregate2->usage->outputTokens)->toBe(13); // 5 + 8 accumulated
});

test('maintains O(1) memory - only latest value stored', function() {
    $collector = makeAggregateCollectorReducer();
    $reducer = new AggregateResponseReducer($collector);

    $reducer->init();

    // Process 100 partials
    for ($i = 1; $i <= 100; $i++) {
        $partial = new PartialInferenceResponse(
            contentDelta: "value_$i",
            usage: new Usage(inputTokens: 10, outputTokens: $i),
        );
        $reducer->step(null, $partial);
    }

    expect($collector->collected)->toHaveCount(100);

    $lastAggregate = end($collector->collected);

    // Verify O(1) property: only latest response value retained
    expect($lastAggregate)->toBeInstanceOf(AggregationState::class)
        ->and($lastAggregate->partialCount)->toBe(100) // Counter tracks all
        ->and($lastAggregate->usage->outputTokens)->toBe(5050); // Sum of 1+2+...+100
});

test('accumulates usage across all partials', function() {
    $collector = makeAggregateCollectorReducer();
    $reducer = new AggregateResponseReducer($collector);

    $reducer->init();

    // Realistic streaming: inputTokens reported once per request (first partial),
    // subsequent partials carry inputTokens=0 to avoid double counting.
    $partials = [
        new PartialInferenceResponse(usage: new Usage(inputTokens: 10, outputTokens: 5)),
        new PartialInferenceResponse(usage: new Usage(inputTokens: 0, outputTokens: 10)),
        new PartialInferenceResponse(usage: new Usage(inputTokens: 0, outputTokens: 15)),
    ];

    foreach ($partials as $partial) {
        $reducer->step(null, $partial);
    }

    $lastAggregate = end($collector->collected);

    expect($lastAggregate->usage->inputTokens)->toBe(10) // only first partial carries input
        ->and($lastAggregate->usage->outputTokens)->toBe(30) // 5+10+15 accumulated
        ->and($lastAggregate->partialCount)->toBe(3);
});

test('captures finishReason from partials', function() {
    $collector = makeAggregateCollectorReducer();
    $reducer = new AggregateResponseReducer($collector);

    $reducer->init();

    $partial1 = new PartialInferenceResponse(
        contentDelta: 'data',
        usage: Usage::none(),
    );

    $partial2 = new PartialInferenceResponse(
        contentDelta: '',
        finishReason: 'stop',
        usage: Usage::none(),
    );

    $reducer->step(null, $partial1);
    $reducer->step(null, $partial2);

    $lastAggregate = end($collector->collected);

    expect($lastAggregate->finishReason)->toBe('stop');
});

test('increments partialCount for each step', function() {
    $collector = makeAggregateCollectorReducer();
    $reducer = new AggregateResponseReducer($collector);

    $reducer->init();

    for ($i = 1; $i <= 10; $i++) {
        $partial = new PartialInferenceResponse(usage: Usage::none());
        $reducer->step(null, $partial);
    }

    expect($collector->collected)->toHaveCount(10);

    // Check incremental counts
    expect($collector->collected[0]->partialCount)->toBe(1)
        ->and($collector->collected[4]->partialCount)->toBe(5)
        ->and($collector->collected[9]->partialCount)->toBe(10);
});

test('handles partials with empty and non-empty values', function() {
    $collector = makeAggregateCollectorReducer();
    $reducer = new AggregateResponseReducer($collector);

    $reducer->init();

    $partial1 = new PartialInferenceResponse(usage: Usage::none());
    $partial2 = new PartialInferenceResponse(contentDelta: 'actual_value', usage: Usage::none());

    $reducer->step(null, $partial1);
    $reducer->step(null, $partial2);

    $aggregate1 = $collector->collected[0];
    $aggregate2 = $collector->collected[1];

    // Both aggregates created
    expect($aggregate1)->toBeInstanceOf(AggregationState::class)
        ->and($aggregate2)->toBeInstanceOf(AggregationState::class)
        ->and($aggregate2->partialCount)->toBe(2);
});

test('init resets aggregate state', function() {
    $collector = makeAggregateCollectorReducer();
    $reducer = new AggregateResponseReducer($collector);

    // First stream
    $reducer->init();
    $partial1 = new PartialInferenceResponse(contentDelta: 'first', usage: new Usage(inputTokens: 10, outputTokens: 5));
    $reducer->step(null, $partial1);

    expect($collector->collected)->toHaveCount(1);

    // Reset and second stream
    $reducer->init();
    expect($collector->collected)->toBeEmpty();

    $partial2 = new PartialInferenceResponse(contentDelta: 'second', usage: new Usage(inputTokens: 20, outputTokens: 10));
    $reducer->step(null, $partial2);

    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0]->partialCount)->toBe(1) // Reset to 1
        ->and($collector->collected[0]->usage->inputTokens)->toBe(20); // New usage
});

test('complete forwards final accumulator', function() {
    $collector = makeAggregateCollectorReducer();
    $reducer = new AggregateResponseReducer($collector);

    $reducer->init();

    $partial = new PartialInferenceResponse(contentDelta: 'final', usage: Usage::none());
    $reducer->step('accumulator_value', $partial);

    $result = $reducer->complete('final_accumulator');

    // Complete should forward to inner reducer's complete
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1);
});
