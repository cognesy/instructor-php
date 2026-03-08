<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
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

    $data = Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::fromConfig(\Cognesy\Polyglot\Tests\Support\TestConfig::llm('gemini'), httpClient: $http))
        ->withModel('gemini-1.5-flash')
        ->withResponseFormat(['type' => 'json_object'])
        ->withMessages('Q?')
        ->asJsonData();

    expect($data)->toBe(['answer' => 'ok']);
});
