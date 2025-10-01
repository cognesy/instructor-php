<?php

use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\InferenceResponseFactory;

it('accumulates content across partial responses', function () {
    $partials = [
        new PartialInferenceResponse(contentDelta: 'Hel', usage: new Usage(inputTokens: 1, outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: 'lo', usage: new Usage(inputTokens: 0, outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: '!', finishReason: 'stop', usage: new Usage(inputTokens: 0, outputTokens: 1)),
    ];

    $list = PartialInferenceResponseList::of(...$partials);
    $res = InferenceResponseFactory::fromPartialResponses($list);
    expect($res->content())->toBe('Hello!');
    expect($res->hasFinishReason())->toBeTrue();
    expect($res->usage()->input())->toBe(1);
    expect($res->usage()->output())->toBe(3);
});

it('aggregates tool arguments from partial responses (single tool)', function () {
    $partials = [
        new PartialInferenceResponse(toolName: 'search', toolArgs: '{"q":"Hel', usage: new Usage()),
        new PartialInferenceResponse(toolName: 'search', toolArgs: 'lo"}', usage: new Usage()),
    ];
    $list = PartialInferenceResponseList::of(...$partials);
    $res = InferenceResponseFactory::fromPartialResponses($list);
    expect($res->hasToolCalls())->toBeTrue();
    $tool = $res->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Hello');
});
