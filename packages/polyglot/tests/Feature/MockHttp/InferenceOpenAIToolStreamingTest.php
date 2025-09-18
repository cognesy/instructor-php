<?php

use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Inference;

it('aggregates tool call arguments across streaming deltas', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->withStream(true)
        ->withJsonSubset(['stream' => true])
        ->replySSEFromJson([
            [
                'choices' => [[
                    'delta' => [
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'function' => [ 'name' => 'search', 'arguments' => '{"q":"Hel' ]
                        ]]
                    ]
                ]]
            ],
            [
                'choices' => [[
                    'delta' => [
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'function' => [ 'arguments' => 'lo"}' ]
                        ]]
                    ]
                ]]
            ],
        ], addDone: true);

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $final = (new Inference())
        ->withHttpClient($http)
        ->using('openai')
        ->withModel('gpt-4o-mini')
        ->withMessages('Find it')
        ->withStreaming(true)
        ->stream()
        ->final();

    expect($final)->not->toBeNull();
    expect($final->hasToolCalls())->toBeTrue();
    $tool = $final->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Hello');
});

