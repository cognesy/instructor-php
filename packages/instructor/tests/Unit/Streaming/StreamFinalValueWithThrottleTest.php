<?php declare(strict_types=1);

use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

/**
 * Regression guard: the adaptive materialization throttle may skip
 * materializing the trailing deltas of a stream. The final value must
 * still be complete — either via the forced finish materialization or
 * via the full-content re-parse fallback (materialization input is null).
 */

class ThrottledFinalItem
{
    public int $id = 0;
    public string $name = '';
}

/** @param list<PartialInferenceDelta> $deltas */
function streamSequenceThrough(array $deltas): array {
    $driver = new FakeInferenceDriver(
        onStream: function () use ($deltas): iterable {
            yield from $deltas;
        },
    );

    $stream = (new StructuredOutput())
        ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
        ->with(messages: 'Extract list.', responseModel: Sequence::of(ThrottledFinalItem::class))
        ->withStreaming(true)
        ->stream();

    $received = [];
    foreach ($stream->sequence() as $item) {
        $received[] = $item;
    }

    return [$received, $stream->finalValue()];
}

function throttledSequenceJson(int $items): string {
    $rows = [];
    for ($i = 1; $i <= $items; $i++) {
        $rows[] = sprintf('{"id":%d,"name":"item-%d"}', $i, $i);
    }
    return '{"list":[' . implode(',', $rows) . ']}';
}

/** @return list<PartialInferenceDelta> tiny tail chunks the throttle will skip */
function tinyChunkDeltas(string $json, bool $withFinish): array {
    $deltas = array_map(
        static fn(string $chunk): PartialInferenceDelta => new PartialInferenceDelta(contentDelta: $chunk),
        str_split($json, 3),
    );
    if ($withFinish) {
        $deltas[] = new PartialInferenceDelta(finishReason: 'stop');
    }
    return $deltas;
}

it('yields the full final sequence when the stream ends with a finish reason', function () {
    [$received, $final] = streamSequenceThrough(tinyChunkDeltas(throttledSequenceJson(7), withFinish: true));

    expect(count($final))->toBe(7);
    expect($received)->toHaveCount(7);
    $items = iterator_to_array($final);
    expect(end($items)->name)->toBe('item-7');
});

it('yields the full final sequence even when the stream ends without a finish reason', function () {
    // no finishReason: the last content deltas are throttle-skipped, so the
    // final value must come from the full-content re-parse fallback
    [$received, $final] = streamSequenceThrough(tinyChunkDeltas(throttledSequenceJson(7), withFinish: false));

    expect(count($final))->toBe(7);
    $items = iterator_to_array($final);
    expect(end($items)->name)->toBe('item-7');
});

it('yields the full final sequence for a single-delta stream without finish reason', function () {
    [, $final] = streamSequenceThrough([
        new PartialInferenceDelta(contentDelta: throttledSequenceJson(3)),
    ]);

    expect(count($final))->toBe(3);
});
