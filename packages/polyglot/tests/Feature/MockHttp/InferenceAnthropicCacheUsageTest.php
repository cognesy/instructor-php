<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('captures Anthropic cache usage tokens', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.anthropic.com/v1/messages')
        ->replyJson([
            'content' => [[ 'type' => 'text', 'text' => 'Cached response.' ]],
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 5,
                'cache_creation_input_tokens' => 128,
                'cache_read_input_tokens' => 64,
            ],
            'stop_reason' => 'end_turn',
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $response = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::using(preset: 'anthropic', httpClient: $http))
        ->withModel('claude-3-haiku-20240307')
        ->withMessages('Hello')
        ->response();

    $usage = $response->usage();
    expect($usage->cacheWriteTokens)->toBe(128);
    expect($usage->cacheReadTokens)->toBe(64);
});
