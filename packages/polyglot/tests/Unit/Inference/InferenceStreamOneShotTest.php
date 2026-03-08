<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('throws on second full-pass iteration', function () {
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

    $firstPass = iterator_to_array($stream->deltas());
    expect($firstPass)->toHaveCount(2);
    expect($firstPass[0])->toBeInstanceOf(PartialInferenceDelta::class);
    expect($stream->final())->not->toBeNull();
    expect(fn() => iterator_to_array($stream->deltas()))
        ->toThrow(\LogicException::class, 'cannot be replayed');
});
