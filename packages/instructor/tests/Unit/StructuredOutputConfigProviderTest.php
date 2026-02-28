<?php declare(strict_types=1);

use Cognesy\Config\Providers\ArrayConfigProvider;
use Cognesy\Instructor\StructuredOutputRuntime;

it('fromDefaults applies structured config from provided config provider', function () {
    $provider = new ArrayConfigProvider([
        'structured' => [
            'defaultPreset' => 'test',
            'presets' => [
                'test' => [
                    'maxRetries' => 7,
                ],
            ],
        ],
        'llm' => [
            'defaultPreset' => 'test',
            'presets' => [
                'test' => [
                    'driver' => 'openai-compatible',
                    'model' => 'provider-model',
                ],
            ],
        ],
    ]);

    $runtime = StructuredOutputRuntime::fromDefaults(configProvider: $provider);

    expect($runtime->config()->maxRetries())->toBe(7);
});
