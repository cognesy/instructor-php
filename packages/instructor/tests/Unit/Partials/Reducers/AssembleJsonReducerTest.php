<?php declare(strict_types=1);

use Cognesy\Instructor\Executors\Partials\DeltaExtraction\PartialProcessingState;
use Cognesy\Instructor\Executors\Partials\JsonMode\AssembleJsonReducer;
use Cognesy\Instructor\Executors\Partials\JsonMode\PartialJson;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Stream\Contracts\Reducer;

function makeJsonCollectorReducer(): Reducer {
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

test('accumulates JSON fragments progressively', function() {
    $collector = makeJsonCollectorReducer();
    $reducer = new AssembleJsonReducer($collector);

    $reducer->init();

    // Feed JSON in parts
    $ctx1 = PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta('{"key"');
    $ctx2 = PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta(': "va');
    $ctx3 = PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta('lue"}');

    $reducer->step(null, $ctx1);
    $reducer->step(null, $ctx2);
    $reducer->step(null, $ctx3);

    expect($collector->collected)->toHaveCount(3);

    // Each should have accumulated JSON
    $json1 = $collector->collected[0]->json;
    $json2 = $collector->collected[1]->json;
    $json3 = $collector->collected[2]->json;

    expect($json1)->toBeInstanceOf(PartialJson::class)
        ->and($json2)->toBeInstanceOf(PartialJson::class)
        ->and($json3)->toBeInstanceOf(PartialJson::class);

    // Last one should have complete JSON
    expect($json3->normalized())->toContain('"key"')
        ->and($json3->normalized())->toContain('"value"');
});

test('skips when JSON is empty - does not forward', function() {
    $collector = makeJsonCollectorReducer();
    $reducer = new AssembleJsonReducer($collector);

    $reducer->init();

    // Feed delta that produces empty JSON (e.g., whitespace before JSON starts)
    $ctx = PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta('   ');

    $result = $reducer->step([], $ctx);

    // Should not forward if JSON is empty
    expect($collector->collected)->toBeEmpty()
        ->and($result)->toBe([]);
});

test('attaches PartialJson to PartialContext', function() {
    $collector = makeJsonCollectorReducer();
    $reducer = new AssembleJsonReducer($collector);

    $reducer->init();

    $ctx = PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta('{"test": 1}');

    $reducer->step(null, $ctx);

    $result = $collector->collected[0];
    expect($result)->toBeInstanceOf(PartialProcessingState::class)
        ->and($result->json)->toBeInstanceOf(PartialJson::class)
        ->and($result->delta)->toBe('{"test": 1}');
});

test('handles markdown-wrapped JSON extraction', function() {
    $collector = makeJsonCollectorReducer();
    $reducer = new AssembleJsonReducer($collector);

    $reducer->init();

    // Feed markdown with JSON in parts
    $ctx1 = PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta('Here is data:\n\n```json\n');
    $ctx2 = PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta('{"value": 42}');
    $ctx3 = PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta('\n```\n');

    $reducer->step(null, $ctx1);
    $reducer->step(null, $ctx2);
    $reducer->step(null, $ctx3);

    // PartialJson should extract JSON from markdown
    $lastJson = end($collector->collected)->json;
    expect($lastJson)->toBeInstanceOf(PartialJson::class);

    $jsonString = $lastJson->normalized();
    expect($jsonString)->toContain('"value"')
        ->and($jsonString)->toContain('42');
});

test('state persists across multiple step calls', function() {
    $collector = makeJsonCollectorReducer();
    $reducer = new AssembleJsonReducer($collector);

    $reducer->init();

    // Feed multiple deltas
    $contexts = [
        PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta('{"a":'),
        PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta('1,'),
        PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta('"b":2}'),
    ];

    foreach ($contexts as $ctx) {
        $reducer->step(null, $ctx);
    }

    expect($collector->collected)->toHaveCount(3);

    // Last JSON should have accumulated all parts
    $finalJson = $collector->collected[2]->json->normalized();
    expect($finalJson)->toContain('"a"')
        ->and($finalJson)->toContain('"b"');
});

test('init resets JSON accumulation state', function() {
    $collector = makeJsonCollectorReducer();
    $reducer = new AssembleJsonReducer($collector);

    // First stream
    $reducer->init();
    $ctx1 = PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta('{"first": 1}');
    $reducer->step(null, $ctx1);

    expect($collector->collected)->toHaveCount(1);

    // Reset for second stream
    $reducer->init();
    expect($collector->collected)->toBeEmpty();

    $ctx2 = PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta('{"second": 2}');
    $reducer->step(null, $ctx2);

    expect($collector->collected)->toHaveCount(1);
    $json = $collector->collected[0]->json->normalized();
    expect($json)->toContain('"second"')
        ->and($json)->not()->toContain('"first"'); // First stream data not retained
});

test('handles empty deltas gracefully', function() {
    $collector = makeJsonCollectorReducer();
    $reducer = new AssembleJsonReducer($collector);

    $reducer->init();

    $reducer->step(null, PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta('{"key"'));
    $reducer->step(null, PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta('')); // Empty
    $reducer->step(null, PartialProcessingState::fromResponse(new PartialInferenceResponse(usage: Usage::none()))->withDelta(': 1}'));

    // Empty delta still accumulates into JSON state but may or may not forward
    expect($collector->collected)->toBeGreaterThanOrEqual(2); // At least non-empty forwarded
});

test('preserves original PartialContext properties', function() {
    $collector = makeJsonCollectorReducer();
    $reducer = new AssembleJsonReducer($collector);

    $reducer->init();

    $original = PartialProcessingState::fromResponse(
        new PartialInferenceResponse(
            contentDelta: 'test',
            finishReason: 'stop',
            usage: new Usage(inputTokens: 10, outputTokens: 5),
        )
    )->withDelta('{"data": true}');

    $reducer->step(null, $original);

    $result = $collector->collected[0];
    expect($result)->toBeInstanceOf(PartialProcessingState::class)
        ->and($result->delta)->toBe('{"data": true}')
        ->and($result->response->finishReason)->toBe('stop')
        ->and($result->json)->toBeInstanceOf(PartialJson::class);
});
