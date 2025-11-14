<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('handles tool calls in non-streaming OpenAI response', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->withJsonSubset(['model' => 'gpt-4o-mini'])
        ->replyJson([
            'id' => 'cmpl_tool_1',
            'choices' => [[
                'message' => [
                    'tool_calls' => [[
                        'id' => 'call_1',
                        'type' => 'function',
                        'function' => [
                            'name' => 'get_weather',
                            'arguments' => '{"city":"Paris"}'
                        ]
                    ]],
                ],
                'finish_reason' => 'tool_calls',
            ]],
            'usage' => [
                'prompt_tokens' => 5,
                'completion_tokens' => 1,
            ],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $response = (new Inference())
        ->withHttpClient($http)
        ->using('openai')
        ->withModel('gpt-4o-mini')
        ->withMessages('Weather please')
        ->response();

    expect($response->hasToolCalls())->toBeTrue();
    $tool = $response->toolCalls()->first();
    expect($tool)->not->toBeNull();
    expect($tool->name())->toBe('get_weather');
    expect($tool->value('city'))->toBe('Paris');
});
