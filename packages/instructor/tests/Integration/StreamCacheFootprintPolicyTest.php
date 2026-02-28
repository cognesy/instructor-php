<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
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
        ->withRuntime(makeStructuredRuntime(driver: $driver))
        ->withConfig(new StructuredOutputConfig(
            responseCachePolicy: ResponseCachePolicy::None,
        ))
        ->with(
            messages: 'Extract user',
            responseModel: StreamCacheFootprintUser::class,
            mode: OutputMode::Json,
        )
        ->stream();

    iterator_to_array($stream->responses());

    $reflection = new ReflectionClass($stream);
    $property = $reflection->getProperty('cachedResponses');
    $cached = $property->getValue($stream);

    expect($cached->count())->toBe(0);
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
        ->withRuntime(makeStructuredRuntime(driver: $driver))
        ->withConfig(new StructuredOutputConfig(
            responseCachePolicy: ResponseCachePolicy::Memory,
        ))
        ->with(
            messages: 'Extract user',
            responseModel: StreamCacheFootprintUser::class,
            mode: OutputMode::Json,
        )
        ->stream();

    iterator_to_array($stream->responses());

    $reflection = new ReflectionClass($stream);
    $property = $reflection->getProperty('cachedResponses');
    $cached = $property->getValue($stream);

    expect($cached->count())->toBe(2);
});
