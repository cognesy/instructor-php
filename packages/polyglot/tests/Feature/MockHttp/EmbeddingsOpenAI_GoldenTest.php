<?php

use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\Embeddings\Embeddings;

it('OpenAI embeddings golden: multiple inputs + usage', function () {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/embeddings')
        ->withJsonSubset(['model' => 'text-embedding-3-small'])
        ->replyJson([
            'data' => [
                ['index' => 0, 'embedding' => [0.1, 0.2]],
                ['index' => 1, 'embedding' => [0.3, 0.4]],
            ],
            'usage' => ['prompt_tokens' => 8, 'total_tokens' => 8],
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();

    $res = (new Embeddings())
        ->withHttpClient($http)
        ->using('openai')
        ->withModel('text-embedding-3-small')
        ->withInputs(['hello', 'world'])
        ->get();

    expect($res->vectors())->toHaveCount(2);
    expect($res->vectors()[0]->values())->toBe([0.1, 0.2]);
    expect($res->usage()->input())->toBe(8);
});

