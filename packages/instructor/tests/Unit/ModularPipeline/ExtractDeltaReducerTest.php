<?php declare(strict_types=1);

use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\PartialFrame;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Pipeline\ExtractDeltaReducer;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Stream\Contracts\Reducer;

function makeFrameCollector(): Reducer {
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

test('extracts contentDelta in JsonSchema mode', function() {
    $collector = makeFrameCollector();
    $reducer = new ExtractDeltaReducer(inner: $collector, mode: OutputMode::JsonSchema);

    $reducer->init();

    $partial = new PartialInferenceResponse(
        contentDelta: '{"key": "value"}',
        usage: Usage::none(),
    );

    $reducer->step(null, $partial);

    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0])->toBeInstanceOf(PartialFrame::class)
        ->and($collector->collected[0]->buffer->raw())->toBe('{"key": "value"}');
});

test('extracts contentDelta in MdJson mode', function() {
    $collector = makeFrameCollector();
    $reducer = new ExtractDeltaReducer(inner: $collector, mode: OutputMode::MdJson);

    $reducer->init();

    $partial = new PartialInferenceResponse(
        contentDelta: '```json\n{"data": 1}\n```',
        usage: Usage::none(),
    );

    $reducer->step(null, $partial);

    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0])->toBeInstanceOf(PartialFrame::class)
        ->and($collector->collected[0]->buffer->raw())->toBe('```json\n{"data": 1}\n```');
});

test('extracts toolArgs in Tools mode', function() {
    $collector = makeFrameCollector();
    $reducer = new ExtractDeltaReducer(inner: $collector, mode: OutputMode::Tools);

    $reducer->init();

    $partial = new PartialInferenceResponse(
        toolArgs: '{"param": "value"}',
        usage: Usage::none(),
    );

    $reducer->step(null, $partial);

    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0])->toBeInstanceOf(PartialFrame::class)
        ->and($collector->collected[0]->buffer->raw())->toBe('{"param": "value"}');
});

test('falls back to contentDelta when toolArgs empty in Tools mode', function() {
    $collector = makeFrameCollector();
    $reducer = new ExtractDeltaReducer(inner: $collector, mode: OutputMode::Tools);

    $reducer->init();

    $partial = new PartialInferenceResponse(
        contentDelta: 'fallback content',
        toolArgs: '', // Empty
        usage: Usage::none(),
    );

    $reducer->step(null, $partial);

    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0]->buffer->raw())->toBe('fallback content');
});

test('skips empty deltas without finish reason or value', function() {
    $collector = makeFrameCollector();
    $reducer = new ExtractDeltaReducer(inner: $collector, mode: OutputMode::JsonSchema);

    $reducer->init();

    $partial = new PartialInferenceResponse(
        contentDelta: '',
        usage: Usage::none(),
    );

    $result = $reducer->step([], $partial);

    // Should skip - not forward
    expect($collector->collected)->toBeEmpty()
        ->and($result)->toBe([]);
});

test('forwards empty delta when finish reason present', function() {
    $collector = makeFrameCollector();
    $reducer = new ExtractDeltaReducer(inner: $collector, mode: OutputMode::JsonSchema);

    $reducer->init();

    $partial = new PartialInferenceResponse(
        contentDelta: '',
        finishReason: 'stop',
        usage: Usage::none(),
    );

    $reducer->step(null, $partial);

    // Should forward because finish reason present
    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0]->buffer->isEmpty())->toBeTrue();
});

test('preserves PartialInferenceResponse in frame', function() {
    $collector = makeFrameCollector();
    $reducer = new ExtractDeltaReducer(inner: $collector, mode: OutputMode::JsonSchema);

    $reducer->init();

    $partial = new PartialInferenceResponse(
        contentDelta: 'test',
        finishReason: 'stop',
        usage: new Usage(inputTokens: 10, outputTokens: 5),
    );

    $reducer->step(null, $partial);

    $frame = $collector->collected[0];
    expect($frame)->toBeInstanceOf(PartialFrame::class)
        ->and($frame->source)->toBe($partial)
        ->and($frame->source->finishReason)->toBe('stop');
});

test('handles multiple consecutive deltas', function() {
    $collector = makeFrameCollector();
    $reducer = new ExtractDeltaReducer(inner: $collector, mode: OutputMode::JsonSchema);

    $reducer->init();

    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'chunk1', usage: Usage::none()));
    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'chunk2', usage: Usage::none()));
    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'chunk3', usage: Usage::none()));

    // Buffer now accumulates across all frames - single source of truth
    expect($collector->collected)->toHaveCount(3)
        ->and($collector->collected[0]->buffer->raw())->toBe('chunk1')
        ->and($collector->collected[1]->buffer->raw())->toBe('chunk1chunk2')
        ->and($collector->collected[2]->buffer->raw())->toBe('chunk1chunk2chunk3');
});

test('increments frame index for each partial', function() {
    $collector = makeFrameCollector();
    $reducer = new ExtractDeltaReducer(inner: $collector, mode: OutputMode::JsonSchema);

    $reducer->init();

    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'a', usage: Usage::none()));
    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'b', usage: Usage::none()));
    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'c', usage: Usage::none()));

    expect($collector->collected[0]->metadata->index)->toBe(0)
        ->and($collector->collected[1]->metadata->index)->toBe(1)
        ->and($collector->collected[2]->metadata->index)->toBe(2);
});

test('init resets frame index for next stream', function() {
    $collector = makeFrameCollector();
    $reducer = new ExtractDeltaReducer(inner: $collector, mode: OutputMode::JsonSchema);

    // First stream
    $reducer->init();
    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'first', usage: Usage::none()));

    expect($collector->collected[0]->metadata->index)->toBe(0);

    // Reset and second stream
    $reducer->init();
    expect($collector->collected)->toBeEmpty(); // Collector was reset via init

    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'second', usage: Usage::none()));

    expect($collector->collected[0]->metadata->index)->toBe(0); // Index reset
});

test('accumulates buffer content across frames', function() {
    $collector = makeFrameCollector();
    $reducer = new ExtractDeltaReducer(inner: $collector, mode: OutputMode::JsonSchema);

    $reducer->init();

    // Feed JSON in parts
    $reducer->step(null, new PartialInferenceResponse(contentDelta: '{"key"', usage: Usage::none()));
    $reducer->step(null, new PartialInferenceResponse(contentDelta: ': "val', usage: Usage::none()));
    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'ue"}', usage: Usage::none()));

    // Buffer accumulates content across all frames - single source of truth
    expect($collector->collected[0]->buffer->raw())->toBe('{"key"')
        ->and($collector->collected[1]->buffer->raw())->toBe('{"key": "val')
        ->and($collector->collected[2]->buffer->raw())->toBe('{"key": "value"}');
});
