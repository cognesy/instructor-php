<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceRequestDriver;

class StreamUserStructC { public int $age; public string $name; }

it('does not re-start driver when accessing final after partials', function () {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"name":"Ann"', usage: new Usage(outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: ',"age":', usage: new Usage(outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: '30}', finishReason: 'stop', usage: new Usage(outputTokens: 1)),
    ];

    $driver = new FakeInferenceRequestDriver(
        responses: [],
        streamBatches: [ $chunks ]
    );

    $stream = (new StructuredOutput)
        ->withDriver($driver)
        ->with(
            messages: 'Extract user',
            responseModel: StreamUserStructC::class,
            mode: OutputMode::Json,
        )
        ->stream();

    // Consume partials fully
    foreach ($stream->partials() as $_) {}
    $callsAfterPartials = $driver->streamCalls;

    // Access final response; should not trigger another driver call
    $final = $stream->finalResponse();
    expect($driver->streamCalls)->toBe($callsAfterPartials);
});

