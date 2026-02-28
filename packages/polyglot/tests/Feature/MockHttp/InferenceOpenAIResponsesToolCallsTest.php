<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('handles function_call items in OpenAI Responses API', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/responses')
        ->withJsonSubset(['model' => 'gpt-4o-mini'])
        ->replyJson([
            'id' => 'resp_tool_1',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'function_call',
                    'call_id' => 'call_1',
                    'name' => 'get_weather',
                    'arguments' => '{"city":"Paris"}',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 5,
                'completion_tokens' => 1,
            ],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $response = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::using(preset: 'openai-responses', httpClient: $http))
        ->withModel('gpt-4o-mini')
        ->withMessages('Weather please')
        ->response();

    expect($response->hasToolCalls())->toBeTrue();
    $tool = $response->toolCalls()->first();
    expect($tool)->not->toBeNull();
    expect($tool->name())->toBe('get_weather');
    expect($tool->value('city'))->toBe('Paris');
});

it('handles multiple function_call items', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/responses')
        ->withJsonSubset(['model' => 'gpt-4o-mini'])
        ->replyJson([
            'id' => 'resp_tool_multi',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'function_call',
                    'call_id' => 'call_1',
                    'name' => 'get_weather',
                    'arguments' => '{"city":"Paris"}',
                ],
                [
                    'type' => 'function_call',
                    'call_id' => 'call_2',
                    'name' => 'get_time',
                    'arguments' => '{"timezone":"CET"}',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
            ],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $response = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::using(preset: 'openai-responses', httpClient: $http))
        ->withModel('gpt-4o-mini')
        ->withMessages('Weather and time please')
        ->response();

    expect($response->hasToolCalls())->toBeTrue();
    $tools = $response->toolCalls()->all();
    expect(count($tools))->toBe(2);
    expect($tools[0]->name())->toBe('get_weather');
    expect($tools[1]->name())->toBe('get_time');
});

it('handles mixed message and function_call items', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/responses')
        ->replyJson([
            'id' => 'resp_mixed',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        ['type' => 'output_text', 'text' => 'Let me check the weather.'],
                    ],
                ],
                [
                    'type' => 'function_call',
                    'call_id' => 'call_weather',
                    'name' => 'get_weather',
                    'arguments' => '{"city":"London"}',
                ],
            ],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $response = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::using(preset: 'openai-responses', httpClient: $http))
        ->withModel('gpt-4o-mini')
        ->withMessages('What is the weather in London?')
        ->response();

    expect($response->content())->toBe('Let me check the weather.');
    expect($response->hasToolCalls())->toBeTrue();
    expect($response->toolCalls()->first()->name())->toBe('get_weather');
});
