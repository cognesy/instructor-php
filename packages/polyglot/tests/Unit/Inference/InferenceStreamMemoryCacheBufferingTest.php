<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('buffers cached partials during streaming and materializes cache once after stream completion', function () {
    $driver = new FakeInferenceDriver(
        streamBatches: [[
            new PartialInferenceResponse(contentDelta: 'A'),
            new PartialInferenceResponse(contentDelta: 'B'),
            new PartialInferenceResponse(contentDelta: 'C', finishReason: 'stop'),
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

    $reflection = new ReflectionClass($stream);
    $cachedProperty = $reflection->getProperty('cachedResponses');
    $bufferProperty = $reflection->getProperty('cachedResponsesBuffer');

    $cachedCountsDuringIteration = [];
    $bufferCountsDuringIteration = [];
    foreach ($stream->responses() as $_partial) {
        $cachedCountsDuringIteration[] = $cachedProperty->getValue($stream)->count();
        $bufferCountsDuringIteration[] = count($bufferProperty->getValue($stream));
    }

    expect($cachedCountsDuringIteration)->toBe([0, 0, 0])
        ->and($bufferCountsDuringIteration)->toBe([1, 2, 3])
        ->and($cachedProperty->getValue($stream)->count())->toBe(3)
        ->and(count($bufferProperty->getValue($stream)))->toBe(0);

    $replayed = iterator_to_array($stream->responses());
    expect(count($replayed))->toBe(3);
});
