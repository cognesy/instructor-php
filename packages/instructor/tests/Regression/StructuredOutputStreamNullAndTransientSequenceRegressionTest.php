<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Instructor\Enums\OutputMode;

if (!class_exists('SequenceStreamPerson')) {
    eval('class SequenceStreamPerson { public string $name; public int $age; }');
}

it('partials() skips null updates emitted before sequence value appears', function () {
    $chunks = [
        new PartialInferenceDelta(contentDelta: '{"list":['),
        new PartialInferenceDelta(contentDelta: '{"name":"Alice","age":25},{"name":"Bob"'),
        new PartialInferenceDelta(contentDelta: ',"age":30}]}', finishReason: 'stop'),
    ];

    $driver = new FakeInferenceDriver(responses: [], streamBatches: [$chunks]);

    $pending = (new StructuredOutput(makeStructuredRuntime(
        driver: $driver,
        config: new StructuredOutputConfig(),
        outputMode: OutputMode::Json,
    )))
        ->with(
            messages: 'Extract people from the stream',
            responseModel: Sequence::of('SequenceStreamPerson'),
        );

    $partials = iterator_to_array($pending->stream()->partials(), false);

    expect($partials)->not->toBeEmpty();
    expect(in_array(null, $partials, true))->toBeFalse();
});

it('sequence() ignores transient non-sequence partial values and yields finalized items', function () {
    $chunks = [
        new PartialInferenceDelta(value: ['tool_fragment' => true]),
        new PartialInferenceDelta(contentDelta: '{"list":[{"name":"Alice","age":25}]}', finishReason: 'stop'),
    ];

    $driver = new FakeInferenceDriver(responses: [], streamBatches: [$chunks]);

    $pending = (new StructuredOutput(makeStructuredRuntime(
        driver: $driver,
        config: new StructuredOutputConfig(),
        outputMode: OutputMode::Json,
    )))
        ->with(
            messages: 'Extract people from the stream',
            responseModel: Sequence::of('SequenceStreamPerson'),
        );

    $items = iterator_to_array($pending->stream()->sequence(), false);

    expect($items)->toHaveCount(1);
    expect($items[0]->name)->toBe('Alice');
});
