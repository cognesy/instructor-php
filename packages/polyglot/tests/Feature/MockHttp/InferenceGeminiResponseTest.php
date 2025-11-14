<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Inference\Inference;

it('returns content for Gemini generateContent (non-streaming)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post(null)
        ->urlStartsWith('https://generativelanguage.googleapis.com/v1beta')
        ->replyJson([
            'candidates' => [[
                'content' => ['parts' => [['text' => 'Hi there!']]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $content = (new Inference())
        ->withHttpClient($http)
        ->using('gemini')
        ->withModel('gemini-1.5-flash')
        ->withMessages('Hello')
        ->get();

    expect($content)->toBe('Hi there!');
});

