<?php

use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Inference;

it('streams partial responses and assembles final content (Gemini SSE)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post(null)
        ->urlStartsWith('https://generativelanguage.googleapis.com/v1beta')
        ->withStream(true)
        ->replySSEFromJson([
            ['candidates' => [[ 'content' => ['parts' => [['text' => 'Hel']]], 'finishReason' => '' ]]],
            ['candidates' => [[ 'content' => ['parts' => [['text' => 'lo']]], 'finishReason' => '' ]]],
            ['candidates' => [[ 'content' => ['parts' => [['text' => '!']]], 'finishReason' => 'STOP' ]]],
        ], addDone: true);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $stream = (new Inference())
        ->withHttpClient($http)
        ->using('gemini')
        ->withModel('gemini-1.5-flash')
        ->withMessages('Greet')
        ->withStreaming(true)
        ->stream();

    iterator_to_array($stream->responses());
    $final = $stream->final();
    expect($final)->not->toBeNull();
    expect($final->content())->toBe('Hello!');
});

