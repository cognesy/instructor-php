<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;

class StreamUserStructB { public int $age; public string $name; }

it('updates lastResponse content cumulatively per chunk', function () {
    $chunks = [
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", '{"name":"Ann"'), usage: new InferenceUsage(outputTokens: 1)),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", ',"age":'), usage: new InferenceUsage(outputTokens: 1)),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", '30}'), finishReason: 'stop', usage: new InferenceUsage(outputTokens: 1)),
    ];

    $driver = new FakeInferenceDriver(streamBatches: [ $chunks ]);

    $stream = (new StructuredOutput)
        ->withRuntime(makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json))
        ->with(
            messages: 'Extract user',
            responseModel: StreamUserStructB::class,
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
