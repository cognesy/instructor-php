<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

class StreamReplayUser { public int $age; public string $name; }

it('replays responses when memory cache policy is enabled without new provider call', function () {
    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [[
            new PartialInferenceResponse(contentDelta: '{"name":"Ann"'),
            new PartialInferenceResponse(contentDelta: ',"age":30}', finishReason: 'stop'),
        ]],
    );

    $stream = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(
            driver: $driver,
            config: new StructuredOutputConfig(
                responseCachePolicy: ResponseCachePolicy::Memory,
            ),
            outputMode: OutputMode::Json,
        ))
        ->with(
            messages: 'Extract user',
            responseModel: StreamReplayUser::class,
        )
        ->stream();

    $first = iterator_to_array($stream->responses());
    $callsAfterFirst = $driver->streamCalls;
    $second = iterator_to_array($stream->responses());

    expect($driver->streamCalls)->toBe($callsAfterFirst);
    expect($first)->toHaveCount(2);
    expect($second)->toHaveCount(2);
    expect($stream->finalValue())->toBeInstanceOf(StreamReplayUser::class);
});
