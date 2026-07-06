<?php declare(strict_types=1);

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Streaming\Pipeline\AccumulatePartialResponses;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Stream\Transformation;
use Cognesy\Stream\TransformationStream;
use Cognesy\Utils\Result\Result;

/**
 * Deterministic coverage for the adaptive materialization throttle:
 * with the default interval (1), materialization requires the snapshot
 * to grow by max(8, len/32) bytes since the last materialization.
 * All thresholds below are exact byte arithmetic — no timing involved.
 */

final class ThrottledDoc
{
    public function __construct(public string $t) {}
}

function throttleCountingDeserializer(int &$calls): CanDeserializeResponse {
    return new class($calls) implements CanDeserializeResponse {
        public function __construct(private int &$calls) {}

        public function deserialize(array $data, ResponseModel $responseModel): Result {
            $this->calls++;
            return Result::success($data);
        }
    };
}

function throttleTransformer(): CanTransformResponse {
    return new class implements CanTransformResponse {
        public function transform(mixed $data, ResponseModel $responseModel): Result {
            return Result::success(new ThrottledDoc(t: (string) ($data['t'] ?? '')));
        }
    };
}

/** @param list<PartialInferenceDelta> $deltas */
function runThrottled(array $deltas, int &$calls, int $interval = 1): array {
    $states = TransformationStream::from($deltas)->using(Transformation::define(
        new AccumulatePartialResponses(
            mode: OutputMode::Json,
            hydrator: makeTestHydrator(throttleCountingDeserializer($calls), throttleTransformer()),
            responseModel: makeAnyResponseModel(ThrottledDoc::class),
            materializationInterval: $interval,
        ),
    ));

    $out = [];
    foreach ($states as $state) {
        $out[] = $state->partialResponse();
    }
    return $out;
}

it('materializes the first parseable snapshot immediately (time-to-first-value)', function () {
    $calls = 0;
    runThrottled([new PartialInferenceDelta(contentDelta: '{"t":"a')], $calls);

    expect($calls)->toBe(1);
});

it('skips re-materialization while growth stays below the 8-byte floor', function () {
    $calls = 0;
    $result = runThrottled([
        new PartialInferenceDelta(contentDelta: '{"t":"a'), // len 7 -> first value, calls=1
        new PartialInferenceDelta(contentDelta: 'b'),       // growth 1 < 8 -> skip
        new PartialInferenceDelta(contentDelta: 'c'),       // growth 2 < 8 -> skip
        new PartialInferenceDelta(contentDelta: 'defg'),    // growth 6 < 8 -> skip
    ], $calls);

    expect($calls)->toBe(1);
    // the value visible downstream is still the first materialized one
    expect($result[3]->hasValue())->toBeFalse();
});

it('materializes once accumulated growth reaches the 8-byte floor', function () {
    $calls = 0;
    $result = runThrottled([
        new PartialInferenceDelta(contentDelta: '{"t":"a'), // len 7 -> calls=1
        new PartialInferenceDelta(contentDelta: 'bcd'),     // growth 3 -> skip
        new PartialInferenceDelta(contentDelta: 'efghi'),   // growth 8 >= 8 -> calls=2
    ], $calls);

    expect($calls)->toBe(2);
    expect($result[2]->hasValue())->toBeTrue();
    expect($result[2]->value()->t)->toBe('abcdefghi');
});

it('scales the required growth with buffer size (len/32)', function () {
    $calls = 0;
    $big = '{"t":"' . str_repeat('x', 3193) . 'a'; // len 3200 -> calls=1
    runThrottled([
        new PartialInferenceDelta(contentDelta: $big),
        // growth 50, required max(8, intdiv(3250,32))=101 -> skip
        new PartialInferenceDelta(contentDelta: str_repeat('y', 50)),
        // growth 110, required max(8, intdiv(3310,32))=103 -> materialize
        new PartialInferenceDelta(contentDelta: str_repeat('z', 60)),
    ], $calls);

    expect($calls)->toBe(2);
});

it('always materializes on finishReason regardless of growth', function () {
    $calls = 0;
    $result = runThrottled([
        new PartialInferenceDelta(contentDelta: '{"t":"a'), // calls=1
        new PartialInferenceDelta(contentDelta: 'b"}'),     // growth 3 < 8 -> skip
        new PartialInferenceDelta(finishReason: 'stop'),    // forced -> calls=2
    ], $calls);

    expect($calls)->toBe(2);
    expect(end($result)->value()->t)->toBe('ab');
});

it('uses pure delta-count throttling when an explicit interval > 1 is set', function () {
    $calls = 0;
    runThrottled([
        new PartialInferenceDelta(contentDelta: '{"t":"a'), // first value -> calls=1
        new PartialInferenceDelta(contentDelta: 'b'),       // count 1 < 3 -> skip
        new PartialInferenceDelta(contentDelta: 'c'),       // count 2 < 3 -> skip
        new PartialInferenceDelta(contentDelta: 'd'),       // count 3 >= 3 -> calls=2 (growth only 3 bytes)
    ], $calls, interval: 3);

    expect($calls)->toBe(2);
});
