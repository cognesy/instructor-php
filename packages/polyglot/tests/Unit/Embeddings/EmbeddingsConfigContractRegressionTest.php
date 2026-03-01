<?php declare(strict_types=1);

use Cognesy\Config\Providers\ArrayConfigProvider;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;

it('resolves embeddings config from DSN with preset parameter', function () {
    $provider = new ArrayConfigProvider([
        'embed' => [
            'defaultPreset' => 'openai',
            'presets' => [
                'openai' => [
                    'driver' => 'openai',
                    'model' => 'text-embedding-3-small',
                    'dimensions' => 1536,
                    'apiUrl' => 'https://api.openai.com/v1',
                    'endpoint' => '/embeddings',
                    'apiKey' => 'test',
                    'maxInputs' => 64,
                    'metadata' => [],
                ],
            ],
        ],
    ]);

    $config = EmbeddingsProvider::dsn('preset=openai')
        ->withConfigProvider($provider)
        ->resolveConfig();

    expect($config->driver)->toBe('openai')
        ->and($config->model)->toBe('text-embedding-3-small');
});

it('keeps dimensions when applying overrides to embeddings config', function () {
    $base = new EmbeddingsConfig(
        driver: 'openai',
        model: 'text-embedding-3-small',
        dimensions: 1536,
    );

    $updated = $base->withOverrides(['model' => 'text-embedding-3-large']);

    expect($updated->model)->toBe('text-embedding-3-large')
        ->and($updated->dimensions)->toBe(1536);
});
