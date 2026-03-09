<?php

use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

it('treats repeated same-name no-id tool deltas as one continuing call', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(toolName: 'search', toolArgs: '{"q":"Paris"'));
    $state->applyDelta(new PartialInferenceDelta(toolName: 'search', toolArgs: ',"lang":"en"}'));

    $final = $state->finalResponse();
    expect($final->hasToolCalls())->toBeTrue();
    expect($final->toolCalls()->count())->toBe(1);

    $tools = $final->toolCalls()->all();
    expect($tools[0]->value('q'))->toBe('Paris');
    expect($tools[0]->value('lang'))->toBe('en');
});

it('appends args-only no-id deltas to the latest tracked tool', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(toolName: 'search', toolArgs: '{"q":"Par'));
    $state->applyDelta(new PartialInferenceDelta(toolArgs: 'is"}'));

    $tool = $state->finalResponse()->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Paris');
});

it('preserves initial args-only tool deltas until a tool identity arrives', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(toolArgs: '{"q":"Par'));
    $state->applyDelta(new PartialInferenceDelta(toolName: 'search', toolArgs: 'is"}'));

    $tool = $state->finalResponse()->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Paris');
});
