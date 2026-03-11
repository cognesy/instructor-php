<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

if (!class_exists('MutableSequencePartialPerson')) {
    eval('class MutableSequencePartialPerson { public string $name; public int $age; }');
}

it('exposes the first completed sequence item before later chunks arrive', function () {
    $chunks = [
        new PartialInferenceDelta(contentDelta: '{"list":[{"name":"Ann","age":30}'),
        new PartialInferenceDelta(contentDelta: ',{"name":"Bob"'),
        new PartialInferenceDelta(contentDelta: ',"age":40}]}', finishReason: 'stop'),
    ];

    $driver = new FakeInferenceDriver(responses: [], streamBatches: [$chunks]);

    $stream = (new StructuredOutput(makeStructuredRuntime(
        driver: $driver,
        config: new StructuredOutputConfig(),
        outputMode: OutputMode::Json,
    )))
        ->with(
            messages: 'Extract people',
            responseModel: Sequence::of('MutableSequencePartialPerson'),
        )
        ->stream();

    $items = [];
    foreach ($stream->sequence() as $item) {
        $items[] = $item;
    }

    expect($items)->not->toBeEmpty();
    expect($items[0]->name)->toBe('Ann');
});

it('yields distinct individual items from sequence stream', function () {
    $chunks = [
        new PartialInferenceDelta(contentDelta: '{"list":[{"name":"Ann","age":30}'),
        new PartialInferenceDelta(contentDelta: ',{"name":"Bob"'),
        new PartialInferenceDelta(contentDelta: ',"age":40}]}', finishReason: 'stop'),
    ];

    $driver = new FakeInferenceDriver(responses: [], streamBatches: [$chunks]);

    $stream = (new StructuredOutput(makeStructuredRuntime(
        driver: $driver,
        config: new StructuredOutputConfig(),
        outputMode: OutputMode::Json,
    )))
        ->with(
            messages: 'Extract people',
            responseModel: Sequence::of('MutableSequencePartialPerson'),
        )
        ->stream();

    $items = [];
    foreach ($stream->sequence() as $item) {
        $items[] = $item;
    }

    expect($items)->toHaveCount(2);
    expect($items[0]->name)->toBe('Ann');
    expect($items[1]->name)->toBe('Bob');
});
