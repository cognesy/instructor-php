<?php

use Cognesy\Polyglot\Inference\Creation\InferenceResponseFactory;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;

it('accumulates content across partial responses', function () {
    $partials = [
        new PartialInferenceResponse(contentDelta: 'Hel', usage: new Usage(inputTokens: 1, outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: 'lo', usage: new Usage(inputTokens: 0, outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: '!', finishReason: 'stop', usage: new Usage(inputTokens: 0, outputTokens: 1)),
    ];

    $acc = PartialInferenceResponse::empty();
    foreach ($partials as $p) {
        if ($p) { $acc = $p->withAccumulatedContent($acc); }
    }
    $res = InferenceResponseFactory::fromAccumulatedPartial($acc);
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
    $acc = PartialInferenceResponse::empty();
    foreach ($partials as $p) {
        if ($p) { $acc = $p->withAccumulatedContent($acc); }
    }
    $res = InferenceResponseFactory::fromAccumulatedPartial($acc);
    expect($res->hasToolCalls())->toBeTrue();
    $tool = $res->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Hello');
});

it('accumulates multiple tools in sequence (name-based)', function () {
    $partials = [
        new PartialInferenceResponse(toolName: 'search', toolArgs: '{"q":"Hel', usage: new Usage()),
        new PartialInferenceResponse(toolArgs: 'lo"}', usage: new Usage()),
        new PartialInferenceResponse(toolName: 'calculate', toolArgs: '{"expr":"2+', usage: new Usage()),
        new PartialInferenceResponse(toolArgs: '2"}', usage: new Usage()),
    ];
    $acc = PartialInferenceResponse::empty();
    foreach ($partials as $p) {
        if ($p) { $acc = $p->withAccumulatedContent($acc); }
    }
    $res = InferenceResponseFactory::fromAccumulatedPartial($acc);
    expect($res->hasToolCalls())->toBeTrue();
    expect($res->toolCalls()->count())->toBe(2);

    $tools = $res->toolCalls()->all();
    expect($tools[0]->name())->toBe('search');
    expect($tools[0]->value('q'))->toBe('Hello');
    expect($tools[1]->name())->toBe('calculate');
    expect($tools[1]->value('expr'))->toBe('2+2');
});

it('accumulates tools by ID with multiple deltas (ID-based preferred)', function () {
    $partials = [
        new PartialInferenceResponse(toolId: 'call_1', toolName: 'search', toolArgs: '{"q":', usage: new Usage()),
        new PartialInferenceResponse(toolId: 'call_1', toolArgs: '"test', usage: new Usage()),
        new PartialInferenceResponse(toolId: 'call_1', toolArgs: '"}', usage: new Usage()),
        new PartialInferenceResponse(toolId: 'call_2', toolName: 'calculate', toolArgs: '{"n":', usage: new Usage()),
        new PartialInferenceResponse(toolId: 'call_2', toolArgs: '42}', usage: new Usage()),
    ];
    $acc = PartialInferenceResponse::empty();
    foreach ($partials as $p) {
        if ($p) { $acc = $p->withAccumulatedContent($acc); }
    }
    $res = InferenceResponseFactory::fromAccumulatedPartial($acc);
    expect($res->hasToolCalls())->toBeTrue();
    expect($res->toolCalls()->count())->toBe(2);

    $tools = $res->toolCalls()->all();
    expect($tools[0]->name())->toBe('search');
    expect($tools[0]->value('q'))->toBe('test');
    expect($tools[1]->name())->toBe('calculate');
    expect($tools[1]->value('n'))->toBe(42);
});

it('preserves first non-default HttpResponse across accumulation', function () {
    $response1 = \Cognesy\Http\Data\HttpResponse::fromArray([
        'statusCode' => 200,
        'body' => '{"data":"first"}',
        'headers' => ['Content-Type' => 'application/json'],
        'isStreamed' => false,
        'stream' => null,
    ]);
    $response2 = \Cognesy\Http\Data\HttpResponse::fromArray([
        'statusCode' => 200,
        'body' => '{"data":"second"}',
        'headers' => ['Content-Type' => 'application/json'],
        'isStreamed' => false,
        'stream' => null,
    ]);

    $partials = [
        new PartialInferenceResponse(contentDelta: 'Hello', responseData: $response1),
        new PartialInferenceResponse(contentDelta: ' ', responseData: $response2),
        new PartialInferenceResponse(contentDelta: 'World'),
    ];

    $acc = PartialInferenceResponse::empty();
    foreach ($partials as $p) {
        if ($p) { $acc = $p->withAccumulatedContent($acc); }
    }

    $res = InferenceResponseFactory::fromAccumulatedPartial($acc);
    expect($res->responseData()->statusCode())->toBe(200);
    expect($res->responseData()->body())->toBe('{"data":"first"}');
});

it('handles finish reason propagation correctly', function () {
    $partials = [
        new PartialInferenceResponse(contentDelta: 'Hello', usage: new Usage()),
        new PartialInferenceResponse(contentDelta: ' World', usage: new Usage()),
        new PartialInferenceResponse(finishReason: 'stop', usage: new Usage()),
    ];

    $acc = PartialInferenceResponse::empty();
    foreach ($partials as $p) {
        if ($p) { $acc = $p->withAccumulatedContent($acc); }
    }

    $res = InferenceResponseFactory::fromAccumulatedPartial($acc);
    expect($res->content())->toBe('Hello World');
    expect($res->finishReason()->value)->toBe('stop');
});

it('accumulates reasoning content across deltas', function () {
    $partials = [
        new PartialInferenceResponse(reasoningContentDelta: 'First ', usage: new Usage()),
        new PartialInferenceResponse(reasoningContentDelta: 'I think', usage: new Usage()),
        new PartialInferenceResponse(reasoningContentDelta: ' about it', usage: new Usage()),
    ];

    $acc = PartialInferenceResponse::empty();
    foreach ($partials as $p) {
        if ($p) { $acc = $p->withAccumulatedContent($acc); }
    }

    $res = InferenceResponseFactory::fromAccumulatedPartial($acc);
    expect($res->reasoningContent())->toBe('First I think about it');
});
