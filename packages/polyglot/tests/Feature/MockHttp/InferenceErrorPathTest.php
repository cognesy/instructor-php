<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Cognesy\Polyglot\Inference\Inference;

it('throws on HTTP 400 for inference', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->reply(MockHttpResponseFactory::error(400, ['content-type' => 'application/json'], json_encode([
            'error' => ['message' => 'Bad request']
        ])));
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $act = fn() => Inference::fromRuntime(\Cognesy\Polyglot\Inference\InferenceRuntime::using(preset: 'openai', httpClient: $http))
        ->withModel('gpt-4o-mini')
        ->withMessages('Hello')
        ->get();

    expect($act)->toThrow(RuntimeException::class);
});

it('throws on HTTP 429 for embeddings', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/embeddings')
        ->reply(MockHttpResponseFactory::error(429, ['content-type' => 'application/json'], json_encode([
            'error' => ['message' => 'Rate limit exceeded']
        ])));
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $act = fn() => Embeddings::fromRuntime(
        EmbeddingsRuntime::using(preset: 'openai', httpClient: $http)
    )
        ->withModel('text-embedding-3-small')
        ->withInputs(['hello'])
        ->vectors();

    expect($act)->toThrow(RuntimeException::class);
});
