<?php

use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;

it('parses OpenAI response into normalized InferenceResponse', function () {
    $adapter = new OpenAIResponseAdapter(new OpenAIUsageFormat());
    $response = MockHttpResponseFactory::json([
        'choices' => [[
            'message' => ['content' => 'Hello!'],
            'finish_reason' => 'stop'
        ]],
        'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 2],
    ]);

    $res = $adapter->fromResponse($response);
    expect($res->content())->toBe('Hello!');
    expect($res->usage()->input())->toBe(3);
    expect($res->usage()->output())->toBe(2);
});

it('does not map tool call arguments into content', function () {
    $adapter = new OpenAIResponseAdapter(new OpenAIUsageFormat());
    $response = MockHttpResponseFactory::json([
        'choices' => [[
            'message' => [
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call_1',
                    'type' => 'function',
                    'function' => [
                        'name' => 'search',
                        'arguments' => '{"q":"Hello"}'
                    ],
                ]],
            ],
            'finish_reason' => 'tool_calls',
        ]],
        'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
    ]);

    $res = $adapter->fromResponse($response);
    expect($res->content())->toBe('');
    expect($res->hasToolCalls())->toBeTrue();
    $tool = $res->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Hello');
});

it('does not map tool call deltas into contentDelta', function () {
    $adapter = new OpenAIResponseAdapter(new OpenAIUsageFormat());
    $event = json_encode([
        'choices' => [[
            'delta' => [
                'tool_calls' => [[
                    'id' => 'call_1',
                    'function' => [
                        'name' => 'search',
                        'arguments' => '{"q":"Hello"}'
                    ],
                ]],
            ],
        ]],
    ]);

    $partial = $adapter->fromStreamResponse($event);
    expect($partial)->not->toBeNull();
    expect($partial->contentDelta)->toBe('');
    expect($partial->toolArgs)->toContain('Hello');
});
