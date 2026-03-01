<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;

it('keeps distinct no-id tool calls when the same tool name appears again later', function () {
    $partials = [
        new PartialInferenceResponse(toolName: 'search', toolArgs: '{"q":"alpha"}', usage: new Usage()),
        new PartialInferenceResponse(toolName: 'calculate', toolArgs: '{"n":1}', usage: new Usage()),
        new PartialInferenceResponse(toolName: 'search', toolArgs: '{"q":"beta"}', usage: new Usage()),
    ];

    $acc = PartialInferenceResponse::empty();
    foreach ($partials as $partial) {
        $acc = $partial->withAccumulatedContent($acc);
    }

    $response = InferenceResponse::fromAccumulatedPartial($acc);
    $tools = $response->toolCalls()->all();

    expect($response->toolCalls()->count())->toBe(3)
        ->and($tools[0]->name())->toBe('search')
        ->and($tools[0]->value('q'))->toBe('alpha')
        ->and($tools[1]->name())->toBe('calculate')
        ->and($tools[1]->value('n'))->toBe(1)
        ->and($tools[2]->name())->toBe('search')
        ->and($tools[2]->value('q'))->toBe('beta');
});
