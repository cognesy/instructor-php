<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('captures reasoning content from Deepseek responses', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.deepseek.com/chat/completions')
        ->replyJson([
            'choices' => [[
                'message' => [
                    'content' => 'Paris',
                    'reasoning_content' => 'France capital lookup reasoning.',
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => [
                'prompt_tokens' => 5,
                'completion_tokens' => 1,
            ],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $response = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::using(preset: 'deepseek-r', httpClient: $http))
        ->withMessages('Q?')
        ->response();

    expect($response->content())->toBe('Paris');
    expect($response->reasoningContent())->toBe('France capital lookup reasoning.');
});

it('extracts reasoning content from think tags when field is missing', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.deepseek.com/chat/completions')
        ->replyJson([
            'choices' => [[
                'message' => [
                    'content' => '<think>Reasoning steps.</think>Paris',
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => [
                'prompt_tokens' => 5,
                'completion_tokens' => 1,
            ],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $response = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::using(preset: 'deepseek-r', httpClient: $http))
        ->withMessages('Q?')
        ->response();

    expect($response->content())->toBe('Paris');
    expect($response->reasoningContent())->toBe('Reasoning steps.');
});
