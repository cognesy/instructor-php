<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;

it('respects JSON mode and returns structured data', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->withJsonSubset([
            'response_format' => ['type' => 'json_object'],
        ])
        ->replyJson([
            'choices' => [[
                'message' => ['content' => '{"answer":"ok"}'],
                'finish_reason' => 'stop'
            ]],
            'usage' => ['prompt_tokens' => 2, 'completion_tokens' => 1],
        ]);

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $data = (new Inference())
        ->withHttpClient($http)
        ->using('openai')
        ->withModel('gpt-4o-mini')
        ->withOutputMode(OutputMode::Json)
        ->withMessages('Q?')
        ->asJsonData();

    expect($data)->toBe(['answer' => 'ok']);
});

