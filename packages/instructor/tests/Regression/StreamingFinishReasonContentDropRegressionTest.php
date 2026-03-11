<?php declare(strict_types=1);

use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

/**
 * Contract: multi-chunk JSON streaming must preserve content when the final
 * delta also carries `finishReason='stop'`.
 *
 * Earlier investigation used invalid input (`finishReason: null`) and
 * produced a false failure signal. These tests keep the real supported
 * behavior covered with valid delta inputs.
 */

class FinishReasonRegressionItem
{
    public string $title = '';
}

it('streams partials correctly when the final delta also carries finishReason=stop', function () {
    $fullJson = json_encode(['title' => str_repeat('x', 50)]);
    $chunks = str_split($fullJson, 10);

    $driver = new FakeInferenceDriver(
        onStream: function () use ($chunks): iterable {
            $last = count($chunks) - 1;
            foreach ($chunks as $i => $chunk) {
                yield new PartialInferenceDelta(
                    contentDelta: $chunk,
                    finishReason: $i === $last ? 'stop' : '',
                );
            }
        },
    );

    $stream = (new StructuredOutput())
        ->withRuntime(makeStructuredRuntime(
            driver: $driver,
            outputMode: OutputMode::Json,
        ))
        ->with(
            messages: 'test',
            responseModel: FinishReasonRegressionItem::class,
        )
        ->stream();

    $partials = [];
    foreach ($stream->partials() as $p) {
        $partials[] = $p;
    }

    expect(count($partials))->toBeGreaterThan(0);

    $final = end($partials);
    expect($final)->toBeInstanceOf(FinishReasonRegressionItem::class);
    expect($final->title)->toBe(str_repeat('x', 50));
});

it('finalizes large multi-chunk responses when finishReason arrives on the last delta', function () {
    $fullJson = json_encode([
        'title' => str_repeat('Title word ', 200),
    ]);
    $chunks = str_split($fullJson, 100);

    $driver = new FakeInferenceDriver(
        onStream: function () use ($chunks): iterable {
            $last = count($chunks) - 1;
            foreach ($chunks as $i => $chunk) {
                yield new PartialInferenceDelta(
                    contentDelta: $chunk,
                    finishReason: $i === $last ? 'stop' : '',
                );
            }
        },
    );

    $value = (new StructuredOutput())
        ->withRuntime(makeStructuredRuntime(
            driver: $driver,
            outputMode: OutputMode::Json,
        ))
        ->with(
            messages: 'test',
            responseModel: FinishReasonRegressionItem::class,
        )
        ->stream()
        ->finalValue();

    expect($value)->toBeInstanceOf(FinishReasonRegressionItem::class);
    expect($value->title)->toBe(str_repeat('Title word ', 200));
});
