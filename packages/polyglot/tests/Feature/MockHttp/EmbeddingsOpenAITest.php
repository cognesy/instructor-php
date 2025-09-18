<?php

use Cognesy\Http\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Embeddings\Embeddings;

it('returns embeddings for OpenAI (single input)', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/embeddings')
        ->withJsonSubset(['model' => 'text-embedding-3-small'])
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

