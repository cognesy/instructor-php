<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceRequestDriver;

class StreamUserStructB { public int $age; public string $name; }

it('updates lastResponse content cumulatively per chunk', function () {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"name":"Ann"', usage: new Usage(outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: ',"age":', usage: new Usage(outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: '30}', finishReason: 'stop', usage: new Usage(outputTokens: 1)),
    ];

    $driver = new FakeInferenceRequestDriver(streamBatches: [ $chunks ]);

    $stream = (new StructuredOutput)
        ->withDriver($driver)
        ->with(
            messages: 'Extract user',
            responseModel: StreamUserStructB::class,
            mode: OutputMode::Json,
        )
        ->stream();

    $iter = $stream->responses();

    // First partial
    $iter->valid();
    $iter->current();
    $c1 = $stream->lastResponse()->content();
    expect($c1)->not()->toBe('');

    // Second partial
    $iter->next();
    $iter->valid();
    $iter->current();
    $c2 = $stream->lastResponse()->content();
    expect(strlen($c2))->toBeGreaterThan(strlen($c1));

    // Final partial
    $iter->next();
    $iter->valid();
    $iter->current();
    $c3 = $stream->lastResponse()->content();
    expect(strlen($c3))->toBeGreaterThan(strlen($c2));
});

