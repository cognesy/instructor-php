<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('finalizes correctly after partial delta consumption without replaying consumed chunks', function () {
    $driver = new FakeInferenceDriver(
        streamBatches: [[
            new PartialInferenceDelta(contentDelta: 'Hel', usage: new Usage(outputTokens: 1)),
            new PartialInferenceDelta(toolId: 'call_1', toolName: 'search', toolArgs: '{"q":"hel', usage: new Usage(outputTokens: 1)),
            new PartialInferenceDelta(toolId: 'call_1', toolArgs: 'lo"}', usage: new Usage(outputTokens: 1)),
            new PartialInferenceDelta(contentDelta: 'lo world', finishReason: 'stop', usage: new Usage(outputTokens: 1)),
        ]],
    );

    $stream = new InferenceStream(
        execution: InferenceExecution::fromRequest((new InferenceRequest())->with(options: ['stream' => true])),
        driver: $driver,
        eventDispatcher: new EventDispatcher(),
    );

    foreach ($stream->deltas() as $delta) {
        expect($delta->contentDelta)->toBe('Hel');
        break;
    }

    $final = $stream->final();

    expect($final)->not->toBeNull();
    expect($final?->content())->toBe('Hello world');
    expect($final?->toolCalls()->count())->toBe(1);
    expect($final?->toolCalls()->first()?->name())->toBe('search');
    expect($final?->toolCalls()->first()?->arguments())->toBe(['q' => 'hello']);
    expect($final?->usage()->output())->toBe(4);
});
