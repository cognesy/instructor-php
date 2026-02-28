<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;

it('extracts tool call arguments as json data', function () {
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
                            'name' => 'provide_data',
                            'arguments' => '{"name":"Paris","population":2148000,"founded":-52}'
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

    $data = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::using(preset: 'openai', httpClient: $http))
        ->withModel('gpt-4o-mini')
        ->withOutputMode(OutputMode::Tools)
        ->withMessages('Provide city data')
        ->asJsonData();

    expect($data)->toBe([
        'name' => 'Paris',
        'population' => 2148000,
        'founded' => -52,
    ]);
});
