<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;

if (!class_exists('SequenceNullPartialPerson')) {
    eval('class SequenceNullPartialPerson { public string $name; public int $age; }');
}

it('sequence() ignores initial no-value chunks on deterministic fake driver streams', function () {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"list":['),
        new PartialInferenceResponse(contentDelta: '{"name":"Alice","age":25},{"name":"Bob"'),
        new PartialInferenceResponse(contentDelta: ',"age":30}]}', finishReason: 'stop'),
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

    $updates = iterator_to_array($pending->stream()->sequence(), false);

    expect($updates)->toHaveCount(2);
    expect($updates[0])->toBeInstanceOf(Sequence::class);
    expect($updates[0]->count())->toBe(1);
    expect($updates[0]->toArray()[0]->name)->toBe('Alice');
    expect($updates[1]->count())->toBe(2);
    expect($updates[1]->toArray()[1]->name)->toBe('Bob');
});
