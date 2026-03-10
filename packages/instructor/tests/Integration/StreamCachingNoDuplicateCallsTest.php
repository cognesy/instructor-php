<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;

class StreamUserStructC { public int $age; public string $name; }

it('does not re-start driver when accessing final after partials', function () {
    $chunks = [
        new PartialInferenceDelta(contentDelta: '{"name":"Ann"', usage: new Usage(outputTokens: 1)),
        new PartialInferenceDelta(contentDelta: ',"age":', usage: new Usage(outputTokens: 1)),
        new PartialInferenceDelta(contentDelta: '30}', finishReason: 'stop', usage: new Usage(outputTokens: 1)),
    ];

    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [ $chunks ]
    );

    $stream = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
        ->with(
            messages: 'Extract user',
            responseModel: StreamUserStructC::class,
        )
        ->stream();

    // Consume partials fully
    foreach ($stream->partials() as $_) {}
    $callsAfterPartials = $driver->streamCalls;

    // Access final response; should not trigger another driver call
    $final = $stream->finalResponse();
    expect($driver->streamCalls)->toBe($callsAfterPartials);
});
