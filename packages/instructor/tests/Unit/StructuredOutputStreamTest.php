<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;


class StreamUserStruct { public int $age; public string $name; }

it('assembles streamed content into final typed value and accumulates usage', function () {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"name":"Ann"', usage: new Usage(outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: ',"age":', usage: new Usage(outputTokens: 2)),
        new PartialInferenceResponse(contentDelta: '30}', finishReason: 'stop', usage: new Usage(outputTokens: 3)),
    ];

    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [ $chunks ]
    );

    $stream = (new StructuredOutput)
        ->withDriver($driver)
        ->with(
            messages: 'Extract user',
            responseModel: StreamUserStruct::class,
            mode: OutputMode::Json,
        )
        ->stream();

    $final = $stream->finalValue();
    expect($final)->toBeInstanceOf(StreamUserStruct::class);
    expect($final->name)->toBe('Ann');
    expect($final->age)->toBe(30);

    // Usage accumulated after consumption (1+2+3)
    expect($stream->lastResponse()->usage()->output())->toBeGreaterThanOrEqual(6);
});

it('accumulates usage correctly via stream->usage() after iterating responses', function () {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"name":"Bob"', usage: new Usage(inputTokens: 50, outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: ',"age":', usage: new Usage(inputTokens: 50, outputTokens: 2)),
        new PartialInferenceResponse(contentDelta: '25}', finishReason: 'stop', usage: new Usage(inputTokens: 50, outputTokens: 3)),
    ];

    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [ $chunks ]
    );

    $stream = (new StructuredOutput)
        ->withDriver($driver)
        ->with(
            messages: 'Extract user',
            responseModel: StreamUserStruct::class,
            mode: OutputMode::Json,
        )
        ->stream();

    // Consume stream via responses()
    foreach ($stream->responses() as $response) {
        // iterating...
    }

    // stream->usage() should return accumulated usage
    $usage = $stream->usage();
    expect($usage->total())->toBeGreaterThan(0);
    expect($usage->outputTokens)->toBeGreaterThanOrEqual(6); // 1+2+3
});
