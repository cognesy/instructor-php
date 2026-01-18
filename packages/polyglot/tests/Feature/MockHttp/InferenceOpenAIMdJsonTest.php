<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;

it('parses markdown JSON responses into arrays', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->replyJson([
            'choices' => [[
                'message' => [
                    'content' => "```json\n{\"name\":\"Paris\",\"population\":2148000,\"founded\":-52}\n```",
                ],
                'finish_reason' => 'stop'
            ]],
            'usage' => ['prompt_tokens' => 2, 'completion_tokens' => 1],
        ]);

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $data = (new Inference())
        ->withHttpClient($http)
        ->using('openai')
        ->withModel('gpt-4o-mini')
        ->withOutputMode(OutputMode::MdJson)
        ->withMessages('Q?')
        ->asJsonData();

    expect($data)->toBe([
        'name' => 'Paris',
        'population' => 2148000,
        'founded' => -52,
    ]);
});
