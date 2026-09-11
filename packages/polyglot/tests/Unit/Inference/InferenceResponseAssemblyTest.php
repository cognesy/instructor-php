<?php

use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Assembly\ThinkTagMessageNormalizer;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

function applyDeltas(array $partials): InferenceStreamState {
    $state = new InferenceStreamState();
    foreach ($partials as $p) {
        assert($p instanceof PartialInferenceDelta);
        $state->applyDelta($p);
    }
    return $state;
}

it('accumulates content across partial responses', function () {
    $partials = [
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", 'Hel'), usage: new InferenceUsage(inputTokens: 1, outputTokens: 1)),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", 'lo'), usage: new InferenceUsage(inputTokens: 0, outputTokens: 1)),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", '!'), finishReason: 'stop', usage: new InferenceUsage(inputTokens: 0, outputTokens: 1)),
    ];

    $res = applyDeltas($partials)->finalResponse();
    expect($res->message()->content()->toString())->toBe('Hello!');
    expect($res->hasFinishReason())->toBeTrue();
    expect($res->usage()->input())->toBe(1);
    expect($res->usage()->output())->toBe(3);
});

it('aggregates tool arguments from partial responses (single tool)', function () {
    $partials = [
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', 'search', '{"q":"Hel'), usage: new InferenceUsage()),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', arguments: 'lo"}'), usage: new InferenceUsage()),
    ];
    $res = applyDeltas($partials)->finalResponse();
    expect($res->message()->hasToolCalls())->toBeTrue();
    $tool = $res->message()->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Hello');
});

it('keeps raw cumulative tool args snapshot while assembling', function () {
    $state = new InferenceStreamState();

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', 'search', '{"q":"Hel'), ));
    expect($state->toolArgsSnapshot())->toBe('{"q":"Hel');

    $state->applyDelta(new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', arguments: 'lo"}'), ));
    expect($state->toolArgsSnapshot())->toBe('{"q":"Hello"}');
});

it('accumulates multiple tools in sequence (name-based)', function () {
    $partials = [
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'search', name: 'search', arguments: '{"q":"Hel'), usage: new InferenceUsage()),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()
            ->withToolCallDelta('test:tool:search', arguments: 'lo"}'), usage: new InferenceUsage()),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'calculate', name: 'calculate', arguments: '{"expr":"2+'), usage: new InferenceUsage()),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()
            ->withToolCallDelta('test:tool:calculate', arguments: '2"}'), usage: new InferenceUsage()),
    ];
    $res = applyDeltas($partials)->finalResponse();
    expect($res->message()->hasToolCalls())->toBeTrue();
    expect($res->message()->toolCalls()->count())->toBe(2);

    $tools = $res->message()->toolCalls()->all();
    expect($tools[0]->name())->toBe('search');
    expect($tools[0]->value('q'))->toBe('Hello');
    expect($tools[1]->name())->toBe('calculate');
    expect($tools[1]->value('expr'))->toBe('2+2');
});

it('treats repeated same-name tool deltas without id as one continuing call', function () {
    $partials = [
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'search', name: 'search', arguments: '{"q":"Paris"'), usage: new InferenceUsage()),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'search', name: 'search', arguments: ',"lang":"en"}'), usage: new InferenceUsage()),
    ];

    $res = applyDeltas($partials)->finalResponse();
    expect($res->message()->hasToolCalls())->toBeTrue();
    expect($res->message()->toolCalls()->count())->toBe(1);

    $tool = $res->message()->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Paris');
    expect($tool->value('lang'))->toBe('en');
});

it('accumulates tools by ID with multiple deltas (ID-based preferred)', function () {
    $partials = [
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', 'search', '{"q":'), usage: new InferenceUsage()),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', arguments: '"test'), usage: new InferenceUsage()),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_1', 'call_1', arguments: '"}'), usage: new InferenceUsage()),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_2', 'call_2', 'calculate', '{"n":'), usage: new InferenceUsage()),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withToolCallDelta("test:tool:" . 'call_2', 'call_2', arguments: '42}'), usage: new InferenceUsage()),
    ];
    $res = applyDeltas($partials)->finalResponse();
    expect($res->message()->hasToolCalls())->toBeTrue();
    expect($res->message()->toolCalls()->count())->toBe(2);

    $tools = $res->message()->toolCalls()->all();
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
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", 'Hello'), responseData: $response1),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", ' '), responseData: $response2),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", 'World')),
    ];

    $res = applyDeltas($deltas)->finalResponse();
    expect($res->responseData()->statusCode())->toBe(200);
    expect($res->responseData()->body())->toBe('{"data":"first"}');
});

it('handles finish reason propagation correctly', function () {
    $partials = [
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", 'Hello'), usage: new InferenceUsage()),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", ' World'), usage: new InferenceUsage()),
        new PartialInferenceDelta(finishReason: 'stop', usage: new InferenceUsage()),
    ];

    $res = applyDeltas($partials)->finalResponse();
    expect($res->message()->content()->toString())->toBe('Hello World');
    expect($res->finishReason()->value)->toBe('stop');
});

it('correctly handles finish reason values for streaming', function () {
    // Test 1: Finish reason stability - once set, should persist
    $partials1 = [
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", 'First'), usage: new InferenceUsage()),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", ' chunk'), finishReason: 'length', usage: new InferenceUsage()),
        new PartialInferenceDelta(usage: new InferenceUsage(inputTokens: 10, outputTokens: 20)), // Usage-only chunk after finish
    ];

    $res1 = applyDeltas($partials1)->finalResponse();
    expect($res1->finishReason()->value)->toBe('length')
        ->and($res1->message()->content()->toString())->toBe('First chunk')
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
            new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", 'test')),
            new PartialInferenceDelta(finishReason: $test['input']),
        ];

        $res = applyDeltas($partials)->finalResponse();
        expect($res->finishReason()->value)->toBe($test['expected']);
    }

    // Test 3: Empty finish reason in early chunks doesn't override
    $partials3 = [
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", 'A'), finishReason: ''),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", 'B'), finishReason: ''),
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", 'C'), finishReason: 'stop'),
    ];

    $res3 = applyDeltas($partials3)->finalResponse();
    expect($res3->finishReason()->value)->toBe('stop')
        ->and($res3->message()->content()->toString())->toBe('ABC');
});

it('accumulates reasoning content across deltas', function () {
    $partials = [
        new PartialInferenceDelta(
            messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()
                ->withReasoningDelta('test:reasoning:0', 'First '),
            usage: new InferenceUsage(),
        ),
        new PartialInferenceDelta(
            messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()
                ->withReasoningDelta('test:reasoning:0', 'I think'),
            usage: new InferenceUsage(),
        ),
        new PartialInferenceDelta(
            messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()
                ->withReasoningDelta('test:reasoning:0', ' about it'),
            usage: new InferenceUsage(),
        ),
    ];

    $res = applyDeltas($partials)->finalResponse();
    expect($res->message()->reasoningContent())->toBe('First I think about it');
});

it('extracts reasoning content from think tags in accumulated content', function () {
    $partials = [
        new PartialInferenceDelta(messageChunks: \Cognesy\Polyglot\Inference\Data\AssistantMessageChunks::empty()->withTextDelta("test:text:0", '<think>Because it is.</think>Paris'), usage: new InferenceUsage()),
    ];

    $res = applyDeltas($partials)->finalResponse();
    $message = (new ThinkTagMessageNormalizer())->normalize($res->message());
    expect($message->reasoningContent())->toBe('Because it is.')
        ->and($message->content()->toString())->toBe('Paris');
});
