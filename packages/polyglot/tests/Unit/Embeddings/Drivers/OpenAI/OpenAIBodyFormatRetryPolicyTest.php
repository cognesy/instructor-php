<?php declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIBodyFormat;

it('Embeddings OpenAI: omits retryPolicy from request body', function () {
    $config = new EmbeddingsConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'KEY',
        endpoint: '/embeddings',
        model: 'text-embedding-3-small',
        driver: 'openai',
        maxInputs: 10,
    );

    $body = new OpenAIBodyFormat($config);

    $req = new EmbeddingsRequest(
        input: ['hello'],
        model: 'text-embedding-3-small',
        retryPolicy: new EmbeddingsRetryPolicy(maxAttempts: 2),
    );

    $json = $body->toRequestBody($req);

    expect($json)->not->toHaveKey('retryPolicy');
    expect($json)->not->toHaveKey('retry_policy');
});
