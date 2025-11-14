<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('streams partial responses and assembles final content (Anthropic SSE)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.anthropic.com/v1/messages')
        ->withStream(true)
        ->replySSEFromJson([
            [ 'delta' => [ 'text' => 'Hel' ] ],
            [ 'delta' => [ 'text' => 'lo' ] ],
            'event: message_stop',
        ], addDone: false);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $stream = (new Inference())
        ->withHttpClient($http)
        ->using('anthropic')
        ->withModel('claude-3-haiku-20240307')
        ->withMessages('Greet')
        ->withStreaming(true)
        ->stream();

    iterator_to_array($stream->responses());
    $final = $stream->final();
    expect($final)->not->toBeNull();
    expect($final->content())->toBe('Hello');
});

