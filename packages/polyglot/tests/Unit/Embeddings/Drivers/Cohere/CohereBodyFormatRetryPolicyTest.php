<?php declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Drivers\Cohere\CohereBodyFormat;

it('Embeddings Cohere: omits retryPolicy from request body', function () {
    $config = new EmbeddingsConfig(
        apiUrl: 'https://api.cohere.ai',
        apiKey: 'KEY',
        endpoint: '/v1/embed',
        model: 'embed-multilingual-v3.0',
        driver: 'cohere',
        maxInputs: 10,
    );

    $body = new CohereBodyFormat($config);

    $req = new EmbeddingsRequest(
        input: ['hello'],
        model: 'embed-multilingual-v3.0',
        retryPolicy: new EmbeddingsRetryPolicy(maxAttempts: 2),
    );

    $json = $body->toRequestBody($req);

    expect($json)->not->toHaveKey('retryPolicy');
    expect($json)->not->toHaveKey('retry_policy');
});
