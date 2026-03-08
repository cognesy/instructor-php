<?php

use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

it('keeps repeated same-name no-id tool starts as distinct calls', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(toolName: 'search', toolArgs: '{"q":"Paris"}'));
    $state->applyDelta(new PartialInferenceDelta(toolName: 'search', toolArgs: '{"q":"Berlin"}'));

    $final = $state->finalResponse();
    expect($final->hasToolCalls())->toBeTrue();
    expect($final->toolCalls()->count())->toBe(2);

    $tools = $final->toolCalls()->all();
    expect($tools[0]->value('q'))->toBe('Paris');
    expect($tools[1]->value('q'))->toBe('Berlin');
});

it('appends args-only no-id deltas to the latest tracked tool', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(toolName: 'search', toolArgs: '{"q":"Par'));
    $state->applyDelta(new PartialInferenceDelta(toolArgs: 'is"}'));

    $tool = $state->finalResponse()->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Paris');
});

