<?php

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Drivers\Cohere\CohereBodyFormat;
use Cognesy\Polyglot\Embeddings\Drivers\Cohere\CohereRequestAdapter;

it('maps EmbeddingsRequest to Cohere embeddings HttpRequest correctly', function () {
    $config = new EmbeddingsConfig(
        apiUrl: 'https://api.cohere.ai/v2',
        apiKey: 'KEY',
        endpoint: '/embed',
        model: 'embed-multilingual-v3.0',
        driver: 'cohere',
        maxInputs: 10,
    );
    $adapter = new CohereRequestAdapter($config, new CohereBodyFormat($config));

    $req = new EmbeddingsRequest(input: ['hello'], model: 'embed-multilingual-v3.0');
    $http = $adapter->toHttpClientRequest($req);

    expect($http->method())->toBe('POST');
    expect($http->url())->toBe('https://api.cohere.ai/v2/embed');
    $headers = array_change_key_case($http->headers(), CASE_LOWER);
    expect($headers['authorization'] ?? null)->toBe('Bearer KEY');
});
