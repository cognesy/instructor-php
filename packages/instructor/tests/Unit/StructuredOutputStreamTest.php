<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;


class StreamUserStruct { public int $age; public string $name; }

it('assembles streamed content into final typed value and accumulates usage', function () {
    $chunks = [
        new PartialInferenceDelta(contentDelta: '{"name":"Ann"', usage: new InferenceUsage(outputTokens: 1)),
        new PartialInferenceDelta(contentDelta: ',"age":', usage: new InferenceUsage(outputTokens: 2)),
        new PartialInferenceDelta(contentDelta: '30}', finishReason: 'stop', usage: new InferenceUsage(outputTokens: 3)),
    ];

    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [ $chunks ]
    );

    $stream = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
        ->with(
            messages: 'Extract user',
            responseModel: StreamUserStruct::class,
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
        new PartialInferenceDelta(contentDelta: '{"name":"Bob"', usage: new InferenceUsage(inputTokens: 50, outputTokens: 1)),
        new PartialInferenceDelta(contentDelta: ',"age":', usage: new InferenceUsage(inputTokens: 50, outputTokens: 2)),
        new PartialInferenceDelta(contentDelta: '25}', finishReason: 'stop', usage: new InferenceUsage(inputTokens: 50, outputTokens: 3)),
    ];

    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [ $chunks ]
    );

    $stream = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
        ->with(
            messages: 'Extract user',
            responseModel: StreamUserStruct::class,
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
