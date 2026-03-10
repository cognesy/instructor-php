<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('suppresses invisible usage-only chunks while still accumulating final usage', function () {
    $driver = new FakeInferenceDriver(
        streamBatches: [[
            new PartialInferenceDelta(contentDelta: 'Hi', usage: new Usage(outputTokens: 1)),
            new PartialInferenceDelta(usage: new Usage(outputTokens: 1)),
            new PartialInferenceDelta(finishReason: 'stop', usage: new Usage(outputTokens: 1)),
        ]],
    );

    $request = (new InferenceRequest())->with(options: ['stream' => true]);
    $stream = new InferenceStream(
        execution: InferenceExecution::fromRequest($request),
        driver: $driver,
        eventDispatcher: new EventDispatcher(),
    );

    $deltas = iterator_to_array($stream->deltas(), false);

    expect($deltas)->toHaveCount(2);
    expect($deltas[0]->contentDelta)->toBe('Hi');
    expect($deltas[1]->finishReason)->toBe('stop');
    expect($stream->final()?->usage()->output())->toBe(3);
});
