<?php declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Drivers\Jina\JinaBodyFormat;

it('Embeddings Jina: omits retryPolicy from request body', function () {
    $config = new EmbeddingsConfig(
        apiUrl: 'https://api.jina.ai',
        apiKey: 'KEY',
        endpoint: '/v1/embeddings',
        model: 'jina-embeddings-v2-base-en',
        driver: 'jina',
        maxInputs: 10,
    );

    $body = new JinaBodyFormat($config);

    $req = new EmbeddingsRequest(
        input: ['hello'],
        model: 'jina-embeddings-v2-base-en',
        retryPolicy: new EmbeddingsRetryPolicy(maxAttempts: 2),
    );

    $json = $body->toRequestBody($req);

    expect($json)->not->toHaveKey('retryPolicy');
    expect($json)->not->toHaveKey('retry_policy');
});
