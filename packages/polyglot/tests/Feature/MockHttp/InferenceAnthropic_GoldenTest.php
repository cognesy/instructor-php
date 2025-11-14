<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('Anthropic golden: streaming text + tool_use aggregation', function () {
    $mock = new MockHttpDriver();

    $mock->on()
        ->post('https://api.anthropic.com/v1/messages')
        ->withStream(true)
        ->replySSEFromJson([
            [ 'delta' => [ 'text' => 'Hel' ] ],
            [ 'delta' => [ 'text' => 'lo' ] ],
            // Start of a tool block
            [ 'content_block' => [ 'id' => 'tb1', 'name' => 'get_weather' ] ],
            [ 'delta' => [ 'partial_json' => '{"city":"Paris"}' ] ],
            'event: message_stop',
        ], addDone: false);

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $tools = [[
        'type' => 'function',
        'function' => [
            'name' => 'get_weather',
            'parameters' => [
                'type' => 'object',
                'properties' => ['city' => ['type' => 'string']],
                'required' => ['city']
            ],
        ]
    ]];

    $stream = (new Inference())
        ->withHttpClient($http)
        ->using('anthropic')
        ->withModel('claude-3-haiku-20240307')
        ->withTools($tools)
        ->withToolChoice('auto')
        ->withMessages([
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user', 'content' => 'Weather in Paris']
        ])
        ->withStreaming(true)
        ->stream();

    iterator_to_array($stream->responses());
    $final = $stream->final();

    expect($final)->not->toBeNull();
    expect(str_starts_with($final->content(), 'Hello'))->toBeTrue();
    expect($final->hasToolCalls())->toBeTrue();
    $tool = $final->toolCalls()->first();
    expect($tool->name())->toBe('get_weather');
    expect($tool->value('city'))->toBe('Paris');
});

