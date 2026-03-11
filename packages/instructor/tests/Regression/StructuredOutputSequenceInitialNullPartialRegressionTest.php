<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Instructor\Enums\OutputMode;

if (!class_exists('SequenceNullPartialPerson')) {
    eval('class SequenceNullPartialPerson { public string $name; public int $age; }');
}

it('sequence() ignores initial no-value chunks on deterministic fake driver streams', function () {
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
            responseModel: Sequence::of('SequenceNullPartialPerson'),
        );

    $items = iterator_to_array($pending->stream()->sequence(), false);

    expect($items)->toHaveCount(2);
    expect($items[0]->name)->toBe('Alice');
    expect($items[1]->name)->toBe('Bob');
});
