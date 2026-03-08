<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('invokes delta callback and exposes last visible delta', function () {
    $driver = new FakeInferenceDriver(
        streamBatches: [[
            new PartialInferenceResponse(contentDelta: 'Hel'),
            new PartialInferenceResponse(contentDelta: 'lo', finishReason: 'stop'),
        ]],
    );

    $request = (new InferenceRequest())->with(options: ['stream' => true]);
    $stream = new InferenceStream(
        execution: InferenceExecution::fromRequest($request),
        driver: $driver,
        eventDispatcher: new EventDispatcher(),
    );

    $seen = [];

    foreach ($stream->onDelta(function (PartialInferenceDelta $delta) use (&$seen): void {
        $seen[] = $delta->contentDelta;
    })->deltas() as $delta) {
        expect($delta)->toBeInstanceOf(PartialInferenceDelta::class);
    }

    expect($seen)->toBe(['Hel', 'lo']);
    expect($stream->lastDelta()?->finishReason)->toBe('stop');
});
