<?php declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Drivers\Gemini\GeminiBodyFormat;

it('Embeddings Gemini: omits retryPolicy from request body', function () {
    $config = new EmbeddingsConfig(
        apiUrl: 'https://example.googleapis.com/v1beta',
        apiKey: 'KEY',
        endpoint: '/models/{model}:batchEmbedContents',
        model: 'models/gemini-embedding-001',
        driver: 'gemini',
        maxInputs: 10,
    );

    $body = new GeminiBodyFormat($config);

    $req = new EmbeddingsRequest(
        input: ['hello'],
        model: 'models/gemini-embedding-001',
        retryPolicy: new EmbeddingsRetryPolicy(maxAttempts: 2),
    );

    $json = $body->toRequestBody($req);

    expect($json)->not->toHaveKey('retryPolicy');
    expect($json)->not->toHaveKey('retry_policy');
});
