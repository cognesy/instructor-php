<?php

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIRequestAdapter;

it('maps EmbeddingsRequest to OpenAI embeddings HttpRequest correctly', function () {
    $config = new EmbeddingsConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'KEY',
        endpoint: '/embeddings',
        model: 'text-embedding-3-small',
        driver: 'openai',
        maxInputs: 10,
    );
    $adapter = new OpenAIRequestAdapter($config, new OpenAIBodyFormat($config));

    $req = new EmbeddingsRequest(input: ['hello'], model: 'text-embedding-3-small');
    $http = $adapter->toHttpClientRequest($req);

    expect($http->method())->toBe('POST');
    expect($http->url())->toBe('https://api.openai.com/v1/embeddings');
    $headers = array_change_key_case($http->headers(), CASE_LOWER);
    expect($headers['authorization'] ?? null)->toBe('Bearer KEY');
    $body = json_decode($http->body()->toString(), true);
    expect($body['model'])->toBe('text-embedding-3-small');
    expect($body['input'][0])->toBe('hello');
});
