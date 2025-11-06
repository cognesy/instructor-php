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
