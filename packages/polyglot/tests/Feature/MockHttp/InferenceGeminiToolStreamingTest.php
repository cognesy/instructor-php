<?php

use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Inference;

it('handles tool call during streaming for Gemini', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post(null)
        ->urlStartsWith('https://generativelanguage.googleapis.com/v1beta')
        ->withStream(true)
        ->replySSEFromJson([
            // Some text chunk first
            ['candidates' => [[ 'content' => ['parts' => [['text' => 'Thinking...']]], 'finishReason' => '' ]]],
            // Tool call chunk (single consolidated args JSON to produce correct final)
            ['candidates' => [[
                'content' => ['parts' => [[ 'functionCall' => ['name' => 'search', 'args' => ['q' => 'Hello']] ]]],
                'finishReason' => 'STOP',
            ]]],
        ], addDone: true);

    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $final = (new Inference())
        ->withHttpClient($http)
        ->using('gemini')
        ->withModel('gemini-1.5-flash')
        ->withMessages('Search')
        ->withStreaming(true)
        ->stream()
        ->final();

    expect($final)->not->toBeNull();
    expect($final->hasToolCalls())->toBeTrue();
    $tool = $final->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Hello');
});

