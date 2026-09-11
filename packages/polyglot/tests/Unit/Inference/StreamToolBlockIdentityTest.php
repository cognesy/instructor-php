<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\AssistantMessageChunks;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

it('assembles arguments and identity arriving in separate chunks for one provider block', function () {
    $state = new InferenceStreamState();
    $block = 'provider:tool:0';

    $state->applyDelta(new PartialInferenceDelta(
        messageChunks: AssistantMessageChunks::empty()->withToolCallDelta($block, arguments: '{"q":'),
    ));
    $state->applyDelta(new PartialInferenceDelta(
        messageChunks: AssistantMessageChunks::empty()->withToolCallDelta(
            $block,
            name: 'search',
            arguments: '"alpha"}',
        ),
    ));

    $calls = $state->finalResponse()->message()->toolCalls()->all();

    expect($calls)->toHaveCount(1)
        ->and($calls[0]->name())->toBe('search')
        ->and($calls[0]->value('q'))->toBe('alpha');
});

it('retains an unnamed tool block without guessing its identity', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(
        messageChunks: AssistantMessageChunks::empty()->withToolCallDelta(
            'provider:tool:0',
            arguments: '{"value":true}',
        ),
    ));

    $calls = $state->finalResponse()->message()->toolCalls()->all();

    expect($calls)->toHaveCount(1)
        ->and($calls[0]->name())->toBe('')
        ->and($calls[0]->value('value'))->toBeTrue();
});

it('keeps distinct provider blocks separate regardless of ids and names', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(
        messageChunks: AssistantMessageChunks::empty()->withToolCallDelta(
            'provider:tool:0',
            'call_1',
            'search',
            '{"a":1}',
        ),
    ));
    $state->applyDelta(new PartialInferenceDelta(
        messageChunks: AssistantMessageChunks::empty()->withToolCallDelta(
            'provider:tool:1',
            name: 'other',
            arguments: '{"b":2}',
        ),
    ));

    $calls = $state->finalResponse()->message()->toolCalls()->all();

    expect($calls)->toHaveCount(2)
        ->and($calls[0]->name())->toBe('search')
        ->and($calls[1]->name())->toBe('other');
});
