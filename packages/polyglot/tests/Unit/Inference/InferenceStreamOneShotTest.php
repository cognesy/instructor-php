<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('throws on second full-pass iteration and keeps final response available', function () {
    $driver = new FakeInferenceDriver(
        streamBatches: [[
            new PartialInferenceResponse(contentDelta: '{"name":"Ann"'),
            new PartialInferenceResponse(contentDelta: '}', finishReason: 'stop'),
        ]],
    );

    $request = (new InferenceRequest())->with(options: ['stream' => true]);
    $stream = new InferenceStream(
        execution: InferenceExecution::fromRequest($request),
        driver: $driver,
        eventDispatcher: new EventDispatcher(),
    );

    $firstPass = iterator_to_array($stream->responses());
    expect($firstPass)->toHaveCount(2);
    expect($stream->final())->not->toBeNull();
    expect(fn() => iterator_to_array($stream->responses()))
        ->toThrow(\LogicException::class, 'cannot be replayed');
});

it('replays partials with memory cache policy without re-calling provider', function () {
    $driver = new FakeInferenceDriver(
        streamBatches: [[
            new PartialInferenceResponse(contentDelta: '{"name":"Ann"'),
            new PartialInferenceResponse(contentDelta: '}', finishReason: 'stop'),
        ]],
    );

    $request = (new InferenceRequest(
        options: ['stream' => true],
        responseCachePolicy: ResponseCachePolicy::Memory,
    ));
    $stream = new InferenceStream(
        execution: InferenceExecution::fromRequest($request),
        driver: $driver,
        eventDispatcher: new EventDispatcher(),
    );

    $firstPass = iterator_to_array($stream->responses());
    $secondPass = iterator_to_array($stream->responses());

    expect($driver->streamCalls)->toBe(1);
    expect($firstPass)->toHaveCount(2);
    expect($secondPass)->toHaveCount(2);
    expect($secondPass[1]->content())->toBe($firstPass[1]->content());
});
