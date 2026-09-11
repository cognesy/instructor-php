<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('finalizes correctly after partial delta consumption without replaying consumed chunks', function () {
    $driver = new FakeInferenceDriver(
        streamBatches: [[
            new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", 'Hel'), usage: new InferenceUsage(outputTokens: 1)),
            new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', 'search', '{"q":"hel'), usage: new InferenceUsage(outputTokens: 1)),
            new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', arguments: 'lo"}'), usage: new InferenceUsage(outputTokens: 1)),
            new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", 'lo world'), finishReason: 'stop', usage: new InferenceUsage(outputTokens: 1)),
        ]],
    );

    $stream = new InferenceStream(
        execution: InferenceExecution::fromRequest((new InferenceRequest())->with(options: ['stream' => true])),
        driver: $driver,
        eventDispatcher: new EventDispatcher(),
    );

    foreach ($stream->deltas() as $delta) {
        expect($delta->messageChunks->textDelta())->toBe('Hel');
        break;
    }

    $final = $stream->final();

    expect($final)->not->toBeNull();
    expect($final?->message()->content()->toString())->toBe('Hello world');
    expect($final?->message()->toolCalls()->count())->toBe(1);
    expect($final?->message()->toolCalls()->first()?->name())->toBe('search');
    expect($final?->message()->toolCalls()->first()?->arguments())->toBe(['q' => 'hello']);
    expect($final?->usage()->output())->toBe(4);
});
