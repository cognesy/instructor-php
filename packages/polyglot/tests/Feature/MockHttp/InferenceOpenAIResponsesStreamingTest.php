<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('streams partial responses and assembles final content (OpenAI Responses SSE)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/responses')
        ->withStream(true)
        ->withJsonSubset(['stream' => true])
        ->replySSEFromJson([
            ['type' => 'response.output_text.delta', 'delta' => 'Hel'],
            ['type' => 'response.output_text.delta', 'delta' => 'lo'],
            ['type' => 'response.output_text.delta', 'delta' => '!'],
            ['type' => 'response.completed'],
        ], addDone: false);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $stream = (new Inference())
        ->withHttpClient($http)
        ->using('openai-responses')
        ->withModel('gpt-4o-mini')
        ->withMessages('Greet me')
        ->withStreaming(true)
        ->stream();

    // Collect all partials to drive the stream consumption
    $partials = iterator_to_array($stream->responses());
    expect($partials)->not->toBeEmpty();

    $final = $stream->final();
    expect($final)->not->toBeNull();
    expect($final->content())->toBe('Hello!');
});

it('handles streaming function call arguments', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/responses')
        ->withStream(true)
        ->withJsonSubset(['stream' => true])
        ->replySSEFromJson([
            [
                'type' => 'response.output_item.added',
                'item' => [
                    'type' => 'function_call',
                    'call_id' => 'call_xyz',
                    'name' => 'get_weather',
                ],
            ],
            [
                'type' => 'response.function_call_arguments.delta',
                'call_id' => 'call_xyz',
                'delta' => '{"city":',
            ],
            [
                'type' => 'response.function_call_arguments.delta',
                'call_id' => 'call_xyz',
                'delta' => '"Paris"}',
            ],
            ['type' => 'response.completed'],
        ], addDone: false);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $stream = (new Inference())
        ->withHttpClient($http)
        ->using('openai-responses')
        ->withModel('gpt-4o-mini')
        ->withMessages('Weather in Paris?')
        ->withStreaming(true)
        ->stream();

    // Consume the stream
    $partials = iterator_to_array($stream->responses());
    expect($partials)->not->toBeEmpty();

    $final = $stream->final();
    expect($final)->not->toBeNull();
    expect($final->toolCalls()->count())->toBe(1);
    $tool = $final->toolCalls()->first();
    expect($tool->name())->toBe('get_weather');
    expect($tool->value('city'))->toBe('Paris');
});

it('streams reasoning content with response.reasoning_text.delta', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/responses')
        ->withStream(true)
        ->withJsonSubset(['stream' => true])
        ->replySSEFromJson([
            ['type' => 'response.reasoning_text.delta', 'delta' => 'Let me '],
            ['type' => 'response.reasoning_text.delta', 'delta' => 'think...'],
            ['type' => 'response.output_text.delta', 'delta' => 'The answer is 42.'],
            ['type' => 'response.completed'],
        ], addDone: false);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $stream = (new Inference())
        ->withHttpClient($http)
        ->using('openai-responses')
        ->withModel('gpt-4o-mini')
        ->withMessages('What is the meaning of life?')
        ->withStreaming(true)
        ->stream();

    $partials = iterator_to_array($stream->responses());
    expect($partials)->not->toBeEmpty();

    $final = $stream->final();
    expect($final)->not->toBeNull();
    expect($final->content())->toBe('The answer is 42.');
    expect($final->reasoningContent())->toBe('Let me think...');
});
