<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('handles tool calls in non-streaming Gemini response', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post(null)
        ->urlStartsWith('https://generativelanguage.googleapis.com/v1beta')
        ->replyJson([
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'functionCall' => [
                            'name' => 'search',
                            'args' => ['q' => 'Hello']
                        ]
                    ]]
                ],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
        ]);

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $response = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::using(preset: 'gemini', httpClient: $http))
        ->withModel('gemini-1.5-flash')
        ->withMessages('Search')
        ->response();

    expect($response->hasToolCalls())->toBeTrue();
    $tool = $response->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Hello');
});

