<?php

use Cognesy\Http\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Drivers\Mock\MockHttpResponse;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Inference\Inference;

it('throws on HTTP 400 for inference', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->reply(MockHttpResponse::error(400, ['content-type' => 'application/json'], json_encode([
            'error' => ['message' => 'Bad request']
        ])));
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $act = fn() => (new Inference())
        ->withHttpClient($http)
        ->using('openai')
        ->withModel('gpt-4o-mini')
        ->withMessages('Hello')
        ->get();

    expect($act)->toThrow(RuntimeException::class);
});

it('throws on HTTP 429 for embeddings', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/embeddings')
        ->reply(MockHttpResponse::error(429, ['content-type' => 'application/json'], json_encode([
            'error' => ['message' => 'Rate limit exceeded']
        ])));
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $act = fn() => (new Embeddings())
        ->withHttpClient($http)
        ->using('openai')
        ->withModel('text-embedding-3-small')
        ->withInputs(['hello'])
        ->vectors();

    expect($act)->toThrow(RuntimeException::class);
});
