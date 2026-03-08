<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

class StreamCacheFootprintUser { public int $age; public string $name; }

it('keeps structured stream replay cache empty with none policy', function () {
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
                responseCachePolicy: ResponseCachePolicy::None,
            ),
            outputMode: OutputMode::Json,
        ))
        ->with(
            messages: 'Extract user',
            responseModel: StreamCacheFootprintUser::class,
        )
        ->stream();

    iterator_to_array($stream->responses());

    $reflection = new ReflectionClass($stream);
    $property = $reflection->getProperty('cachedResponses');
    $cached = $property->getValue($stream);

    expect(count($cached))->toBe(0);
});

it('stores structured stream replay cache with memory policy', function () {
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
            responseModel: StreamCacheFootprintUser::class,
        )
        ->stream();

    iterator_to_array($stream->responses());

    $reflection = new ReflectionClass($stream);
    $property = $reflection->getProperty('cachedResponses');
    $cached = $property->getValue($stream);

    expect(count($cached))->toBe(2);
});
