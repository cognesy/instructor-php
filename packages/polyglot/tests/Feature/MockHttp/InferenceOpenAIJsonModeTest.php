<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
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

    $data = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::fromConfig(\Cognesy\Polyglot\Tests\Support\TestConfig::llm('openai'), httpClient: $http))
        ->withModel('gpt-4o-mini')
        ->withResponseFormat(\Cognesy\Polyglot\Inference\Data\ResponseFormat::jsonObject())
        ->withMessages(\Cognesy\Messages\Messages::fromString('Q?'))
        ->asJsonData();

    expect($data)->toBe(['answer' => 'ok']);
});
