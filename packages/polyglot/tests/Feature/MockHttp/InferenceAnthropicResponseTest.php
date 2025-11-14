<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('returns content for Anthropic messages (non-streaming)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.anthropic.com/v1/messages')
        ->replyJson([
            'content' => [[ 'type' => 'text', 'text' => 'Hi there!' ]],
            'stop_reason' => 'end_turn',
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $content = (new Inference())
        ->withHttpClient($http)
        ->using('anthropic')
        ->withModel('claude-3-haiku-20240307')
        ->withMessages('Hello')
        ->get();

    expect($content)->toBe('Hi there!');
});

