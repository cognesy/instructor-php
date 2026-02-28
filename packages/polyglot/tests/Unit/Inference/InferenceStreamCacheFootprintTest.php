<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('keeps replay cache empty when response cache policy is none', function () {
    $driver = new FakeInferenceDriver(
        streamBatches: [[
            new PartialInferenceResponse(contentDelta: '{"name":"A"'),
            new PartialInferenceResponse(contentDelta: '}', finishReason: 'stop'),
        ]],
    );

    $request = new InferenceRequest(
        options: ['stream' => true],
        responseCachePolicy: ResponseCachePolicy::None,
    );
    $stream = new InferenceStream(
        execution: InferenceExecution::fromRequest($request),
        driver: $driver,
        eventDispatcher: new EventDispatcher(),
    );

    iterator_to_array($stream->responses());

    $reflection = new ReflectionClass($stream);
    $property = $reflection->getProperty('cachedResponses');
    $cached = $property->getValue($stream);

    expect($cached->count())->toBe(0);
});

it('stores replay cache when response cache policy is memory', function () {
    $driver = new FakeInferenceDriver(
        streamBatches: [[
            new PartialInferenceResponse(contentDelta: '{"name":"A"'),
            new PartialInferenceResponse(contentDelta: '}', finishReason: 'stop'),
        ]],
    );

    $request = new InferenceRequest(
        options: ['stream' => true],
        responseCachePolicy: ResponseCachePolicy::Memory,
    );
    $stream = new InferenceStream(
        execution: InferenceExecution::fromRequest($request),
        driver: $driver,
        eventDispatcher: new EventDispatcher(),
    );

    iterator_to_array($stream->responses());

    $reflection = new ReflectionClass($stream);
    $property = $reflection->getProperty('cachedResponses');
    $cached = $property->getValue($stream);

    expect($cached->count())->toBe(2);
});
