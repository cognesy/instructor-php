<?php declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIBodyFormat;

it('applies request overrides over runtime defaults in embeddings request body', function () {
    $config = new EmbeddingsConfig(
        model: 'runtime-default-model',
        driver: 'openai',
        maxInputs: 10,
    );

    $body = (new OpenAIBodyFormat($config))->toRequestBody(new EmbeddingsRequest(
        input: ['hello'],
        model: 'request-model',
        options: [
            'encoding_format' => 'base64',
            'user' => 'abc-123',
        ],
    ));

    expect($body['model'])->toBe('request-model');
    expect($body['encoding_format'])->toBe('base64');
    expect($body['user'])->toBe('abc-123');
});
