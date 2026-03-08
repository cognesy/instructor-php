<?php

use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

function applyDeltas(array $partials): InferenceStreamState {
    $state = new InferenceStreamState();
    foreach ($partials as $p) {
        $delta = $p instanceof PartialInferenceDelta ? $p : new PartialInferenceDelta(
            contentDelta: $p->contentDelta,
            reasoningContentDelta: $p->reasoningContentDelta,
            toolId: $p->toolId ?? '',
            toolName: $p->toolName ?? '',
            toolArgs: $p->toolArgs ?? '',
            finishReason: $p->finishReason() ?? '',
            usage: $p->usage(),
            usageIsCumulative: $p->isUsageCumulative(),
        );
        $state->applyDelta($delta);
    }
    return $state;
}

it('accumulates content across partial responses', function () {
    $partials = [
        new PartialInferenceResponse(contentDelta: 'Hel', usage: new Usage(inputTokens: 1, outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: 'lo', usage: new Usage(inputTokens: 0, outputTokens: 1)),
        new PartialInferenceResponse(contentDelta: '!', finishReason: 'stop', usage: new Usage(inputTokens: 0, outputTokens: 1)),
    ];

    $res = applyDeltas($partials)->finalResponse();
    expect($res->content())->toBe('Hello!');
    expect($res->hasFinishReason())->toBeTrue();
    expect($res->usage()->input())->toBe(1);
    expect($res->usage()->output())->toBe(3);
});

it('aggregates tool arguments from partial responses (single tool)', function () {
    $partials = [
        new PartialInferenceResponse(toolId: 'call_1', toolName: 'search', toolArgs: '{"q":"Hel', usage: new Usage()),
        new PartialInferenceResponse(toolId: 'call_1', toolArgs: 'lo"}', usage: new Usage()),
    ];
    $res = applyDeltas($partials)->finalResponse();
    expect($res->hasToolCalls())->toBeTrue();
    $tool = $res->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Hello');
});

it('keeps raw cumulative tool args snapshot while assembling', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(
        toolId: 'call_1', toolName: 'search', toolArgs: '{"q":"Hel',
    ));
    expect($state->toolArgsSnapshot())->toBe('{"q":"Hel');

    $state->applyDelta(new PartialInferenceDelta(
        toolId: 'call_1', toolArgs: 'lo"}',
    ));
    expect($state->toolArgsSnapshot())->toBe('{"q":"Hello"}');
});

it('accumulates multiple tools in sequence (name-based)', function () {
    $partials = [
        new PartialInferenceResponse(toolName: 'search', toolArgs: '{"q":"Hel', usage: new Usage()),
        new PartialInferenceResponse(toolArgs: 'lo"}', usage: new Usage()),
        new PartialInferenceResponse(toolName: 'calculate', toolArgs: '{"expr":"2+', usage: new Usage()),
        new PartialInferenceResponse(toolArgs: '2"}', usage: new Usage()),
    ];
    $res = applyDeltas($partials)->finalResponse();
    expect($res->hasToolCalls())->toBeTrue();
    expect($res->toolCalls()->count())->toBe(2);

    $tools = $res->toolCalls()->all();
    expect($tools[0]->name())->toBe('search');
    expect($tools[0]->value('q'))->toBe('Hello');
    expect($tools[1]->name())->toBe('calculate');
    expect($tools[1]->value('expr'))->toBe('2+2');
});

it('treats repeated same-name tool starts without id as separate calls', function () {
    $partials = [
        new PartialInferenceResponse(toolName: 'search', toolArgs: '{"q":"Paris"}', usage: new Usage()),
        new PartialInferenceResponse(toolName: 'search', toolArgs: '{"q":"Berlin"}', usage: new Usage()),
    ];

    $res = applyDeltas($partials)->finalResponse();
    expect($res->hasToolCalls())->toBeTrue();
    expect($res->toolCalls()->count())->toBe(2);

    $tools = $res->toolCalls()->all();
    expect($tools[0]->name())->toBe('search');
    expect($tools[0]->value('q'))->toBe('Paris');
    expect($tools[1]->name())->toBe('search');
    expect($tools[1]->value('q'))->toBe('Berlin');
});

it('accumulates tools by ID with multiple deltas (ID-based preferred)', function () {
    $partials = [
        new PartialInferenceResponse(toolId: 'call_1', toolName: 'search', toolArgs: '{"q":', usage: new Usage()),
        new PartialInferenceResponse(toolId: 'call_1', toolArgs: '"test', usage: new Usage()),
        new PartialInferenceResponse(toolId: 'call_1', toolArgs: '"}', usage: new Usage()),
        new PartialInferenceResponse(toolId: 'call_2', toolName: 'calculate', toolArgs: '{"n":', usage: new Usage()),
        new PartialInferenceResponse(toolId: 'call_2', toolArgs: '42}', usage: new Usage()),
    ];
    $res = applyDeltas($partials)->finalResponse();
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

    $deltas = [
        new PartialInferenceDelta(contentDelta: 'Hello', responseData: $response1),
        new PartialInferenceDelta(contentDelta: ' ', responseData: $response2),
        new PartialInferenceDelta(contentDelta: 'World'),
    ];

    $res = applyDeltas($deltas)->finalResponse();
    expect($res->responseData()->statusCode())->toBe(200);
    expect($res->responseData()->body())->toBe('{"data":"first"}');
});

it('handles finish reason propagation correctly', function () {
    $partials = [
        new PartialInferenceResponse(contentDelta: 'Hello', usage: new Usage()),
        new PartialInferenceResponse(contentDelta: ' World', usage: new Usage()),
        new PartialInferenceResponse(finishReason: 'stop', usage: new Usage()),
    ];

    $res = applyDeltas($partials)->finalResponse();
    expect($res->content())->toBe('Hello World');
    expect($res->finishReason()->value)->toBe('stop');
});

it('correctly handles finish reason values for streaming', function () {
    // Test 1: Finish reason stability - once set, should persist
    $partials1 = [
        new PartialInferenceResponse(contentDelta: 'First', usage: new Usage()),
        new PartialInferenceResponse(contentDelta: ' chunk', finishReason: 'length', usage: new Usage()),
        new PartialInferenceResponse(usage: new Usage(inputTokens: 10, outputTokens: 20)), // Usage-only chunk after finish
    ];

    $res1 = applyDeltas($partials1)->finalResponse();
    expect($res1->finishReason()->value)->toBe('length')
        ->and($res1->content())->toBe('First chunk')
        ->and($res1->usage()->input())->toBe(10)
        ->and($res1->usage()->output())->toBe(20);

    // Test 2: Different finish reason values (using normalized values)
    $finishReasonTests = [
        ['input' => 'stop', 'expected' => 'stop'],
        ['input' => 'length', 'expected' => 'length'],
        ['input' => 'tool_calls', 'expected' => 'tool_calls'],
        ['input' => 'max_tokens', 'expected' => 'length'],  // normalized to 'length'
        ['input' => 'safety', 'expected' => 'content_filter'],  // normalized to 'content_filter'
        ['input' => 'error', 'expected' => 'error'],
    ];
    foreach ($finishReasonTests as $test) {
        $partials = [
            new PartialInferenceResponse(contentDelta: 'test'),
            new PartialInferenceResponse(finishReason: $test['input']),
        ];

        $res = applyDeltas($partials)->finalResponse();
        expect($res->finishReason()->value)->toBe($test['expected']);
    }

    // Test 3: Empty finish reason in early chunks doesn't override
    $partials3 = [
        new PartialInferenceResponse(contentDelta: 'A', finishReason: ''),
        new PartialInferenceResponse(contentDelta: 'B', finishReason: ''),
        new PartialInferenceResponse(contentDelta: 'C', finishReason: 'stop'),
    ];

    $res3 = applyDeltas($partials3)->finalResponse();
    expect($res3->finishReason()->value)->toBe('stop')
        ->and($res3->content())->toBe('ABC');
});

it('accumulates reasoning content across deltas', function () {
    $partials = [
        new PartialInferenceResponse(reasoningContentDelta: 'First ', usage: new Usage()),
        new PartialInferenceResponse(reasoningContentDelta: 'I think', usage: new Usage()),
        new PartialInferenceResponse(reasoningContentDelta: ' about it', usage: new Usage()),
    ];

    $res = applyDeltas($partials)->finalResponse();
    expect($res->reasoningContent())->toBe('First I think about it');
});

it('extracts reasoning content from think tags in accumulated content', function () {
    $partials = [
        new PartialInferenceResponse(contentDelta: '<think>Because it is.</think>Paris', usage: new Usage()),
    ];

    $res = applyDeltas($partials)->finalResponse()->withReasoningContentFallbackFromContent();
    expect($res->reasoningContent())->toBe('Because it is.')
        ->and($res->content())->toBe('Paris');
});
