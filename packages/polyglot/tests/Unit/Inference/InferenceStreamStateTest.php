<?php

use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

it('treats repeated same-name no-id tool deltas as one continuing call', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'search', name: 'search', arguments: '{"q":"Paris"')));
    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'search', name: 'search', arguments: ',"lang":"en"}')));

    $final = $state->finalResponse();
    expect($final->message()->hasToolCalls())->toBeTrue();
    expect($final->message()->toolCalls()->count())->toBe(1);

    $tools = $final->message()->toolCalls()->all();
    expect($tools[0]->value('q'))->toBe('Paris');
    expect($tools[0]->value('lang'))->toBe('en');
});

it('appends args-only no-id deltas to the latest tracked tool', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'search', name: 'search', arguments: '{"q":"Par')));
    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()
        ->withToolCallDelta('test:tool:search', arguments: 'is"}')));

    $tool = $state->finalResponse()->message()->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Paris');
});

it('preserves initial args-only tool deltas until a tool identity arrives', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()
        ->withToolCallDelta('test:tool:search', arguments: '{"q":"Par')));
    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'search', name: 'search', arguments: 'is"}')));

    $tool = $state->finalResponse()->message()->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Paris');
});
