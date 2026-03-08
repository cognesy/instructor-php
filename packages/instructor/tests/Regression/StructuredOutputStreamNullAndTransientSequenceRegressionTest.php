<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;

if (!class_exists('SequenceStreamPerson')) {
    eval('class SequenceStreamPerson { public string $name; public int $age; }');
}

it('partials() skips null updates emitted before sequence value appears', function () {
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
            responseModel: Sequence::of('SequenceStreamPerson'),
        );

    $partials = iterator_to_array($pending->stream()->partials(), false);

    expect($partials)->not->toBeEmpty();
    expect(in_array(null, $partials, true))->toBeFalse();
});

it('sequence() ignores transient non-sequence partial values and yields finalized sequence', function () {
    $chunks = [
        (new PartialInferenceResponse())->withValue(['tool_fragment' => true]),
        new PartialInferenceResponse(contentDelta: '{"list":[{"name":"Alice","age":25}]}', finishReason: 'stop'),
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

    $updates = iterator_to_array($pending->stream()->sequence(), false);

    expect($updates)->toHaveCount(1);
    expect($updates[0])->toBeInstanceOf(Sequence::class);
    expect($updates[0]->count())->toBe(1);
    expect($updates[0]->toArray()[0]->name)->toBe('Alice');
});
