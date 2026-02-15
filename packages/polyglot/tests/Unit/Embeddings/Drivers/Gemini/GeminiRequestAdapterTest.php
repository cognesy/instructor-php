<?php

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Drivers\Gemini\GeminiBodyFormat;
use Cognesy\Polyglot\Embeddings\Drivers\Gemini\GeminiRequestAdapter;

it('maps EmbeddingsRequest to Gemini embeddings HttpRequest correctly', function () {
    $config = new EmbeddingsConfig(
        apiUrl: 'https://generativelanguage.googleapis.com/v1beta',
        apiKey: 'KEY',
        endpoint: '/{model}:batchEmbedContents',
        model: 'models/gemini-embedding-001',
        driver: 'gemini',
        maxInputs: 10,
    );
    $adapter = new GeminiRequestAdapter($config, new GeminiBodyFormat($config));

    $req = new EmbeddingsRequest(input: ['hello'], model: 'models/gemini-embedding-001');
    $http = $adapter->toHttpClientRequest($req);

    expect($http->method())->toBe('POST');
    expect($http->url())->toContain('https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:batchEmbedContents');
    expect($http->url())->toContain('key=KEY');
});
