<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;

it('returns embeddings for OpenAI (single input)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/embeddings')
        ->withJsonSubset(['model' => 'text-embedding-3-small'])
        ->times(1)
        ->replyJson([
            'data' => [
                ['index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
            ],
            'usage' => ['prompt_tokens' => 3],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $vectors = (new Embeddings())
        ->withHttpClient($http)
        ->using('openai')
        ->withModel('text-embedding-3-small')
        ->withInputs(['hello'])
        ->vectors();

    expect($vectors)->toHaveCount(1);
    expect($vectors[0]->values())->toBe([0.1, 0.2, 0.3]);
});

it('supports runtime-style create with explicit request', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/embeddings')
        ->withJsonSubset(['model' => 'text-embedding-3-small'])
        ->times(1)
        ->replyJson([
            'data' => [
                ['index' => 0, 'embedding' => [0.4, 0.5, 0.6]],
            ],
            'usage' => ['prompt_tokens' => 3],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $vectors = (new Embeddings())
        ->withHttpClient($http)
        ->using('openai')
        ->create(new EmbeddingsRequest(
            input: ['hello'],
            model: 'text-embedding-3-small',
        ))
        ->get()
        ->vectors();

    expect($vectors)->toHaveCount(1);
    expect($vectors[0]->values())->toBe([0.4, 0.5, 0.6]);
});

it('supports facade toRuntime extraction and runtime static factories', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/embeddings')
        ->withJsonSubset(['model' => 'text-embedding-3-small'])
        ->replyJson([
            'data' => [
                ['index' => 0, 'embedding' => [0.7, 0.8, 0.9]],
            ],
            'usage' => ['prompt_tokens' => 3],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $request = new EmbeddingsRequest(
        input: ['hello'],
        model: 'text-embedding-3-small',
    );

    $fromFacadeRuntime = (new Embeddings())
        ->withHttpClient($http)
        ->using('openai')
        ->toRuntime()
        ->create($request)
        ->get()
        ->vectors();

    expect($fromFacadeRuntime)->toHaveCount(1);
    expect($fromFacadeRuntime[0]->values())->toBe([0.7, 0.8, 0.9]);

    $mock2 = new MockHttpDriver();
    $mock2->on()
        ->post('https://api.openai.com/v1/embeddings')
        ->withJsonSubset(['model' => 'text-embedding-3-small'])
        ->replyJson([
            'data' => [
                ['index' => 0, 'embedding' => [1.0, 1.1, 1.2]],
            ],
            'usage' => ['prompt_tokens' => 3],
        ]);
    $http2 = (new HttpClientBuilder())->withDriver($mock2)->create();

    $fromStaticRuntime = EmbeddingsRuntime::using(
        preset: 'openai',
        httpClient: $http2,
    )->create($request)->get()->vectors();

    expect($fromStaticRuntime)->toHaveCount(1);
    expect($fromStaticRuntime[0]->values())->toBe([1, 1.1, 1.2]);
});
