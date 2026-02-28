<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('handles tool calls in non-streaming Anthropic response', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.anthropic.com/v1/messages')
        ->replyJson([
            'content' => [[ 'type' => 'tool_use', 'id' => 'call_1', 'name' => 'get_weather', 'input' => ['city' => 'Paris'] ]],
            'stop_reason' => 'tool_use',
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $response = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::using(preset: 'anthropic', httpClient: $http))
        ->withModel('claude-3-haiku-20240307')
        ->withMessages('Weather')
        ->response();

    expect($response->hasToolCalls())->toBeTrue();
    $tool = $response->toolCalls()->first();
    expect($tool->name())->toBe('get_weather');
    expect($tool->value('city'))->toBe('Paris');
});

