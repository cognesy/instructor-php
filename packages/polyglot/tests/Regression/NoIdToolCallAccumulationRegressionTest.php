<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

it('keeps distinct no-id tool calls when the same tool name appears again later', function () {
    $state = new InferenceStreamState();
    $state->applyDelta(new PartialInferenceDelta(toolName: 'search', toolArgs: '{"q":"alpha"}'));
    $state->applyDelta(new PartialInferenceDelta(toolName: 'calculate', toolArgs: '{"n":1}'));
    $state->applyDelta(new PartialInferenceDelta(toolName: 'search', toolArgs: '{"q":"beta"}'));

    $response = $state->finalResponse();
    $tools = $response->toolCalls()->all();

    expect($response->toolCalls()->count())->toBe(3)
        ->and($tools[0]->name())->toBe('search')
        ->and($tools[0]->value('q'))->toBe('alpha')
        ->and($tools[1]->name())->toBe('calculate')
        ->and($tools[1]->value('n'))->toBe(1)
        ->and($tools[2]->name())->toBe('search')
        ->and($tools[2]->value('q'))->toBe('beta');
});
