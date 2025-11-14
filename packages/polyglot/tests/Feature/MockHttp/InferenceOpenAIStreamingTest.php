<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('streams partial responses and assembles final content (OpenAI SSE)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->withStream(true)
        ->withJsonSubset(['stream' => true])
        ->replySSEFromJson([
            ['choices' => [['delta' => ['content' => 'Hel']]]],
            ['choices' => [['delta' => ['content' => 'lo']]]],
            ['choices' => [['delta' => ['content' => '!']]]],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $stream = (new Inference())
        ->withHttpClient($http)
        ->using('openai')
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
