<?php

use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;

it('respects JSON mode for Gemini', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post(null)
        ->urlStartsWith('https://generativelanguage.googleapis.com/v1beta')
        ->replyJson([
            'candidates' => [[ 'content' => ['parts' => [['text' => '{"answer":"ok"}']]] ]],
            'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $data = (new Inference())
        ->withHttpClient($http)
        ->using('gemini')
        ->withModel('gemini-1.5-flash')
        ->withOutputMode(OutputMode::Json)
        ->withMessages('Q?')
        ->asJsonData();

    expect($data)->toBe(['answer' => 'ok']);
});

