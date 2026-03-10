<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
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

    $final = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::fromConfig(\Cognesy\Polyglot\Tests\Support\TestConfig::llm('openai'), httpClient: $http))
        ->withModel('gpt-4o-mini')
        ->withMessages(\Cognesy\Messages\Messages::fromString('Find it'))
        ->withStreaming(true)
        ->stream()
        ->final();

    expect($final)->not->toBeNull();
    expect($final->hasToolCalls())->toBeTrue();
    $tool = $final->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Hello');
});

it('supports parallel tool calls across streaming deltas', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->withStream(true)
        ->withJsonSubset(['stream' => true])
        ->replySSEFromJson([
            [
                'choices' => [[
                    'delta' => [
                        'tool_calls' => [
                            [
                                'id' => 'call_1',
                                'index' => 0,
                                'function' => [ 'name' => 'search', 'arguments' => '{"q":"Hello"}' ],
                            ],
                            [
                                'id' => 'call_2',
                                'index' => 1,
                                'function' => [ 'name' => 'calculate', 'arguments' => '{"expr":"2+2"}' ],
                            ],
                        ],
                    ],
                ]],
            ],
        ], addDone: true);

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $final = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::fromConfig(\Cognesy\Polyglot\Tests\Support\TestConfig::llm('openai'), httpClient: $http))
        ->withModel('gpt-4o-mini')
        ->withMessages(\Cognesy\Messages\Messages::fromString('Find it'))
        ->withStreaming(true)
        ->stream()
        ->final();

    expect($final)->not->toBeNull();
    expect($final->hasToolCalls())->toBeTrue();
    expect($final->toolCalls()->count())->toBe(2);

    $tools = $final->toolCalls()->all();
    expect($tools[0]->name())->toBe('search');
    expect($tools[0]->value('q'))->toBe('Hello');
    expect($tools[1]->name())->toBe('calculate');
    expect($tools[1]->value('expr'))->toBe('2+2');
});
