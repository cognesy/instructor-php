<?php declare(strict_types=1);

use Cognesy\Instructor\ResponseIterators\DecoratedPipeline\DeltaExtraction\ExtractDeltaReducer;
use Cognesy\Instructor\ResponseIterators\DecoratedPipeline\DeltaExtraction\PartialProcessingState;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Stream\Contracts\Reducer;

function makeCollectorReducer(): Reducer {
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
    $collector = makeCollectorReducer();
    $reducer = new ExtractDeltaReducer($collector, OutputMode::JsonSchema);

    $reducer->init();

    $partial = new PartialInferenceResponse(
        contentDelta: '{"key": "value"}',
        usage: Usage::none(),
    );

    $reducer->step(null, $partial);

    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0])->toBeInstanceOf(PartialProcessingState::class)
        ->and($collector->collected[0]->delta)->toBe('{"key": "value"}');
});

test('extracts contentDelta in MdJson mode', function() {
    $collector = makeCollectorReducer();
    $reducer = new ExtractDeltaReducer($collector, OutputMode::MdJson);

    $reducer->init();

    $partial = new PartialInferenceResponse(
        contentDelta: '```json\n{"data": 1}\n```',
        usage: Usage::none(),
    );

    $reducer->step(null, $partial);

    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0])->toBeInstanceOf(PartialProcessingState::class)
        ->and($collector->collected[0]->delta)->toBe('```json\n{"data": 1}\n```');
});

test('extracts toolArgs in Tools mode', function() {
    $collector = makeCollectorReducer();
    $reducer = new ExtractDeltaReducer($collector, OutputMode::Tools);

    $reducer->init();

    $partial = new PartialInferenceResponse(
        toolArgs: '{"param": "value"}',
        usage: Usage::none(),
    );

    $reducer->step(null, $partial);

    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0])->toBeInstanceOf(PartialProcessingState::class)
        ->and($collector->collected[0]->delta)->toBe('{"param": "value"}');
});

test('falls back to contentDelta when toolArgs empty in Tools mode', function() {
    $collector = makeCollectorReducer();
    $reducer = new ExtractDeltaReducer($collector, OutputMode::Tools);

    $reducer->init();

    $partial = new PartialInferenceResponse(
        contentDelta: 'fallback content',
        toolArgs: '', // Empty
        usage: Usage::none(),
    );

    $reducer->step(null, $partial);

    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0]->delta)->toBe('fallback content');
});

test('skips empty deltas - does not forward to next reducer', function() {
    $collector = makeCollectorReducer();
    $reducer = new ExtractDeltaReducer($collector, OutputMode::JsonSchema);

    $reducer->init();

    $partial = new PartialInferenceResponse(
        contentDelta: '',
        usage: Usage::none(),
    );

    $result = $reducer->step([], $partial);

    // Should not forward, accumulator unchanged
    expect($collector->collected)->toBeEmpty()
        ->and($result)->toBe([]);
});

test('preserves PartialInferenceResponse in PartialContext', function() {
    $collector = makeCollectorReducer();
    $reducer = new ExtractDeltaReducer($collector, OutputMode::JsonSchema);

    $reducer->init();

    $partial = new PartialInferenceResponse(
        contentDelta: 'test',
        finishReason: 'stop',
        usage: new Usage(inputTokens: 10, outputTokens: 5),
    );

    $reducer->step(null, $partial);

    $context = $collector->collected[0];
    expect($context)->toBeInstanceOf(PartialProcessingState::class)
        ->and($context->response)->toBe($partial)
        ->and($context->response->finishReason())->toBe('stop');
});

test('handles multiple consecutive deltas', function() {
    $collector = makeCollectorReducer();
    $reducer = new ExtractDeltaReducer($collector, OutputMode::JsonSchema);

    $reducer->init();

    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'chunk1', usage: Usage::none()));
    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'chunk2', usage: Usage::none()));
    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'chunk3', usage: Usage::none()));

    expect($collector->collected)->toHaveCount(3)
        ->and($collector->collected[0]->delta)->toBe('chunk1')
        ->and($collector->collected[1]->delta)->toBe('chunk2')
        ->and($collector->collected[2]->delta)->toBe('chunk3');
});

test('handles mixed empty and non-empty deltas', function() {
    $collector = makeCollectorReducer();
    $reducer = new ExtractDeltaReducer($collector, OutputMode::JsonSchema);

    $reducer->init();

    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'data', usage: Usage::none()));
    $reducer->step(null, new PartialInferenceResponse(contentDelta: '', usage: Usage::none()));
    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'more', usage: Usage::none()));

    // Should only collect non-empty
    expect($collector->collected)->toHaveCount(2)
        ->and($collector->collected[0]->delta)->toBe('data')
        ->and($collector->collected[1]->delta)->toBe('more');
});

test('init resets state for next stream', function() {
    $collector = makeCollectorReducer();
    $reducer = new ExtractDeltaReducer($collector, OutputMode::JsonSchema);

    // First stream
    $reducer->init();
    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'first', usage: Usage::none()));

    expect($collector->collected)->toHaveCount(1);

    // Reset and second stream
    $reducer->init();
    expect($collector->collected)->toBeEmpty(); // Collector was reset via init

    $reducer->step(null, new PartialInferenceResponse(contentDelta: 'second', usage: Usage::none()));

    expect($collector->collected)->toHaveCount(1)
        ->and($collector->collected[0]->delta)->toBe('second');
});
