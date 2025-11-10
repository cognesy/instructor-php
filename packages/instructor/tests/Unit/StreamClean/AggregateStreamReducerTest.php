<?php declare(strict_types=1);

use Cognesy\Instructor\ResponseIterators\Clean\Aggregation\AggregateStreamReducer;
use Cognesy\Instructor\ResponseIterators\Clean\Aggregation\StreamAggregate;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;

test('initializes with empty aggregate', function() {
    $reducer = new AggregateStreamReducer(accumulatePartials: true);

    $initial = $reducer->init();

    expect($initial)->toBeInstanceOf(StreamAggregate::class)
        ->and($initial->content)->toBe('')
        ->and($initial->latestValue)->toBeNull()
        ->and($initial->frameCount)->toBe(0);
});

test('accumulates partial content', function() {
    $reducer = new AggregateStreamReducer(accumulatePartials: true);

    $aggregate = $reducer->init();

    $partial = (new PartialInferenceResponse(
        contentDelta: 'test',
        usage: Usage::none()
    ))->withContent('test'); // Set accumulated content

    $result = $reducer->step($aggregate, $partial);

    expect($result)->toBeInstanceOf(StreamAggregate::class)
        ->and($result->content)->toBe('test')
        ->and($result->frameCount)->toBe(1);
});

test('updates latest value when partial has value', function() {
    $reducer = new AggregateStreamReducer(accumulatePartials: true);

    $aggregate = $reducer->init();

    $object = (object) ['key' => 'value'];
    $partial = (new PartialInferenceResponse(
        usage: Usage::none()
    ))->withValue($object); // Set value using mutator

    $result = $reducer->step($aggregate, $partial);

    expect($result->latestValue)->toBe($object);
});

test('accumulates multiple partials', function() {
    $reducer = new AggregateStreamReducer(accumulatePartials: true);

    $aggregate = $reducer->init();

    $partial1 = (new PartialInferenceResponse(contentDelta: 'part1', usage: Usage::none()))->withContent('part1');
    $partial2 = (new PartialInferenceResponse(contentDelta: 'part2', usage: Usage::none()))->withContent('part1part2');
    $partial3 = (new PartialInferenceResponse(contentDelta: 'part3', usage: Usage::none()))->withContent('part1part2part3');

    $aggregate = $reducer->step($aggregate, $partial1);
    $aggregate = $reducer->step($aggregate, $partial2);
    $aggregate = $reducer->step($aggregate, $partial3);

    expect($aggregate->content)->toBe('part1part2part3')
        ->and($aggregate->frameCount)->toBe(3);
});

test('accumulates usage across partials', function() {
    $reducer = new AggregateStreamReducer(accumulatePartials: true);

    $aggregate = $reducer->init();

    $partial1 = new PartialInferenceResponse(usage: new Usage(inputTokens: 10, outputTokens: 5));
    $partial2 = new PartialInferenceResponse(usage: new Usage(inputTokens: 15, outputTokens: 8));

    $aggregate = $reducer->step($aggregate, $partial1);
    $aggregate = $reducer->step($aggregate, $partial2);

    // Usage accumulation uses withAccumulated which sums both
    expect($aggregate->usage->inputTokens)->toBe(25) // Sum of inputs
        ->and($aggregate->usage->outputTokens)->toBe(13); // Sum of outputs
});

test('captures finish reason', function() {
    $reducer = new AggregateStreamReducer(accumulatePartials: true);

    $aggregate = $reducer->init();

    $partial = new PartialInferenceResponse(finishReason: 'stop', usage: Usage::none());

    $result = $reducer->step($aggregate, $partial);

    expect($result->finishReason)->toBe('stop');
});

test('accumulates partials when enabled', function() {
    $reducer = new AggregateStreamReducer(accumulatePartials: true);

    $aggregate = $reducer->init();

    $partial1 = new PartialInferenceResponse(usage: Usage::none());
    $partial2 = new PartialInferenceResponse(usage: Usage::none());

    $aggregate = $reducer->step($aggregate, $partial1);
    $aggregate = $reducer->step($aggregate, $partial2);

    expect($aggregate->partials)->not()->toBeNull()
        ->and($aggregate->partials->count())->toBe(2);
});

test('does not accumulate partials when disabled', function() {
    $reducer = new AggregateStreamReducer(accumulatePartials: false);

    $aggregate = $reducer->init();

    $partial = new PartialInferenceResponse(usage: Usage::none());

    $result = $reducer->step($aggregate, $partial);

    expect($result->partials)->toBeNull();
});

test('adds all partials to collection when enabled', function() {
    $reducer = new AggregateStreamReducer(accumulatePartials: true);

    $aggregate = $reducer->init();

    $partial1 = new PartialInferenceResponse(usage: Usage::none());
    $partial2 = new PartialInferenceResponse(usage: Usage::none());
    $partial3 = new PartialInferenceResponse(usage: Usage::none());

    $aggregate = $reducer->step($aggregate, $partial1);
    $aggregate = $reducer->step($aggregate, $partial2);
    $aggregate = $reducer->step($aggregate, $partial3);

    expect($aggregate->partials->count())->toBe(3);
});

test('complete returns final aggregate', function() {
    $reducer = new AggregateStreamReducer(accumulatePartials: true);

    $aggregate = $reducer->init();

    $partial = (new PartialInferenceResponse(contentDelta: 'test', usage: Usage::none()))->withContent('test');

    $aggregate = $reducer->step($aggregate, $partial);
    $result = $reducer->complete($aggregate);

    expect($result)->toBe($aggregate);
});

test('init resets aggregate for new stream', function() {
    $reducer = new AggregateStreamReducer(accumulatePartials: true);

    // First stream
    $aggregate = $reducer->init();
    $partial = (new PartialInferenceResponse(contentDelta: 'first', usage: Usage::none()))->withContent('first');
    $aggregate = $reducer->step($aggregate, $partial);

    expect($aggregate->content)->toBe('first')
        ->and($aggregate->frameCount)->toBe(1);

    // Second stream
    $aggregate = $reducer->init();
    expect($aggregate->content)->toBe('')
        ->and($aggregate->frameCount)->toBe(0);
});

test('handles partials with both content and value', function() {
    $reducer = new AggregateStreamReducer(accumulatePartials: true);

    $aggregate = $reducer->init();

    $object = (object) ['data' => 'test'];
    $partial = (new PartialInferenceResponse(
        contentDelta: 'content',
        usage: Usage::none()
    ))->withContent('content')->withValue($object);

    $result = $reducer->step($aggregate, $partial);

    expect($result->content)->toBe('content')
        ->and($result->latestValue)->toBe($object);
});

test('preserves last non-null finish reason', function() {
    $reducer = new AggregateStreamReducer(accumulatePartials: true);

    $aggregate = $reducer->init();

    $partial1 = new PartialInferenceResponse(finishReason: null, usage: Usage::none());
    $partial2 = new PartialInferenceResponse(finishReason: 'stop', usage: Usage::none());
    $partial3 = new PartialInferenceResponse(finishReason: null, usage: Usage::none());

    $aggregate = $reducer->step($aggregate, $partial1);
    $aggregate = $reducer->step($aggregate, $partial2);
    $aggregate = $reducer->step($aggregate, $partial3);

    expect($aggregate->finishReason)->toBe('stop');
});
