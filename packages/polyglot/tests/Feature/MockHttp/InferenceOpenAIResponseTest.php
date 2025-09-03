<?php

use Cognesy\Http\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('returns content for OpenAI chat completions (non-streaming)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->withJsonSubset([
            'model' => 'gpt-4o-mini',
        ])
        ->replyJson([
            'id' => 'cmpl_test',
            'choices' => [
                ['message' => ['content' => 'Hi there!'], 'finish_reason' => 'stop']
            ],
            'usage' => [
                'prompt_tokens' => 3,
                'completion_tokens' => 2,
            ],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $content = (new Inference())
        ->withHttpClient($http)
        ->using('openai')
        ->withModel('gpt-4o-mini')
        ->withMessages('Hello')
        ->get();

    expect($content)->toBe('Hi there!');
});
