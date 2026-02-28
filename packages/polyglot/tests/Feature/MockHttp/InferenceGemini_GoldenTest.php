<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;

it('Gemini golden: tools + JSON mode + streaming functionCall', function () {
    $mock = new MockHttpDriver();

    $mock->on()
        ->post(null)
        ->urlStartsWith('https://generativelanguage.googleapis.com/v1beta')
        ->withStream(true)
        ->replySSEFromJson([
            ['candidates' => [[ 'content' => ['parts' => [['text' => 'Hel']]], 'finishReason' => '' ]]],
            ['candidates' => [[ 'content' => ['parts' => [['text' => 'lo']]], 'finishReason' => '' ]]],
            // functionCall arrives with consolidated args
            ['candidates' => [[ 'content' => ['parts' => [[ 'functionCall' => ['name' => 'search', 'args' => ['q' => 'Hello']] ] ]], 'finishReason' => 'STOP' ]]],
        ], addDone: true);

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $tools = [[
        'type' => 'function',
        'function' => [
            'name' => 'search',
            'parameters' => [
                'type' => 'object',
                'properties' => ['q' => ['type' => 'string']],
                'required' => ['q']
            ],
        ]
    ]];

    $stream = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::using(preset: 'gemini', httpClient: $http))
        ->withModel('gemini-1.5-flash')
        ->withTools($tools)
        ->withToolChoice('auto')
        ->withOutputMode(OutputMode::Json)
        ->withMessages([
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user', 'content' => 'Search hello']
        ])
        ->withStreaming(true)
        ->stream();

    iterator_to_array($stream->responses());
    $final = $stream->final();

    expect($final)->not->toBeNull();
    expect(str_starts_with($final->content(), 'Hello'))->toBeTrue();
    expect($final->hasToolCalls())->toBeTrue();
    $tool = $final->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Hello');
});

