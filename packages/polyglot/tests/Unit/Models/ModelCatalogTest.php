<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Models\SupportStatus;

function catalogFixture(string $source, int $contextWindow): array
{
    return [
        'version' => 'test-v1',
        'models' => [[
            'driver' => 'openai',
            'model' => 'gpt-test',
            'status' => 'supported',
            'limits' => ['contextWindow' => $contextWindow, 'maxOutput' => 4096],
            'capabilities' => ['jsonSchema' => 'supported'],
            'source' => $source,
        ]],
    ];
}

it('resolves one exact driver and wire model offering', function () {
    $catalog = ModelCatalog::fromArray(catalogFixture('base', 128000));
    $profile = $catalog->find('openai', 'gpt-test');

    expect($catalog)->toHaveCount(1)
        ->and($profile->key->driver)->toBe('openai')
        ->and($profile->key->model)->toBe('gpt-test')
        ->and($profile->limits->contextWindow)->toBe(128000)
        ->and($profile->capabilities->jsonSchema)->toBe(SupportStatus::Supported)
        ->and($profile->source)->toBe('base');
});

it('keeps an unknown offering explicit', function () {
    $profile = ModelCatalog::fromArray(catalogFixture('base', 128000))
        ->find('custom', 'private-model');

    expect($profile->status)->toBe(SupportStatus::Unknown)
        ->and($profile->limits->contextWindow)->toBeNull()
        ->and($profile->capabilities->tools)->toBe(SupportStatus::Unknown)
        ->and($profile->source)->toBe('unknown');
});

it('replaces a whole offering at a higher-precedence layer', function () {
    $base = ModelCatalog::fromArray(catalogFixture('bundled', 128000));
    $project = ModelCatalog::fromArray([
        'version' => 'project-v1',
        'models' => [[
            'driver' => 'openai',
            'model' => 'gpt-test',
            'status' => 'supported',
            'limits' => ['contextWindow' => 64000],
            'source' => 'project',
        ]],
    ]);

    $profile = $base->overlay($project)->find('openai', 'gpt-test');

    expect($profile->limits->contextWindow)->toBe(64000)
        ->and($profile->limits->maxOutput)->toBeNull()
        ->and($profile->capabilities->jsonSchema)->toBe(SupportStatus::Unknown)
        ->and($profile->source)->toBe('project')
        ->and($profile->catalogVersion)->toBe('project-v1');
});

it('round-trips normalized catalog data and filters by driver', function () {
    $catalog = ModelCatalog::fromArray(catalogFixture('base', 128000));
    $roundTripped = ModelCatalog::fromArray($catalog->toArray());

    expect($roundTripped->toArray())->toBe($catalog->toArray())
        ->and($roundTripped->forDriver('openai'))->toHaveCount(1)
        ->and($roundTripped->forDriver('anthropic'))->toHaveCount(0);
});

it('serializes only known model facts', function () {
    $catalog = ModelCatalog::fromArray([
        'version' => 'sparse-v1',
        'models' => [[
            'driver' => 'custom',
            'model' => 'sparse-model',
            'limits' => ['maxOutput' => 2048],
            'modalities' => [
                'inputText' => 'unknown',
                'inputFile' => 'supported',
                'outputText' => 'supported',
            ],
            'capabilities' => ['tools' => 'unknown', 'responseFormatWithTools' => 'unsupported'],
        ]],
    ]);

    expect($catalog->toArray()['models'][0])->toBe([
        'driver' => 'custom',
        'model' => 'sparse-model',
        'limits' => ['maxOutput' => 2048],
        'modalities' => ['inputFile' => 'supported', 'outputText' => 'supported'],
        'capabilities' => ['responseFormatWithTools' => 'unsupported'],
    ]);
});

it('preserves the three model-specific capability overrides', function () {
    $catalog = ModelCatalog::bundled();
    $expected = [
        'deepseek-v4-flash' => [
            'streaming' => 'supported',
            'tools' => 'supported',
            'toolChoice' => 'supported',
            'jsonObject' => 'supported',
            'jsonSchema' => 'unsupported',
            'responseFormatWithTools' => 'unsupported',
        ],
        'deepseek-v4-pro' => [
            'streaming' => 'supported',
            'tools' => 'supported',
            'toolChoice' => 'supported',
            'jsonObject' => 'supported',
            'jsonSchema' => 'unsupported',
            'responseFormatWithTools' => 'unsupported',
        ],
        'qwen3.8-max' => [
            'streaming' => 'supported',
            'tools' => 'supported',
            'toolChoice' => 'supported',
            'jsonObject' => 'supported',
            'jsonSchema' => 'supported',
            'responseFormatWithTools' => 'unsupported',
        ],
    ];

    foreach ($expected as $model => $capabilities) {
        $driver = match (true) {
            str_starts_with($model, 'deepseek') => 'deepseek',
            default => 'qwen',
        };
        $profile = $catalog->find($driver, $model);

        expect($profile->source)->toBe('bundled-override')
            ->and(array_intersect_key($profile->capabilities->toArray(), $capabilities))->toBe($capabilities)
            ->and($profile->capabilities->reasoning->known)->toBeTrue();
    }
});

it('hydrates reasoning from exact offering records without model-name inference', function () {
    $catalog = ModelCatalog::bundled();
    $known = $catalog->find('openai', 'gpt-5.6')->capabilities->reasoning;
    $unknown = $catalog->find('openai', 'gpt-5.6-preview')->capabilities->reasoning;

    expect($known->known)->toBeTrue()
        ->and($known->supportsEffort())->toBeTrue()
        ->and($unknown->known)->toBeFalse()
        ->and($unknown->supportsEffort())->toBeFalse();
});

it('ships an exact offering for every bundled connection preset', function () {
    $catalog = ModelCatalog::bundled();

    foreach (LLMConfig::presetNames() as $preset) {
        $config = LLMConfig::fromPreset($preset);
        expect($catalog->find($config->driver, $config->model)->status)
            ->not->toBe(SupportStatus::Unknown, "Missing catalog offering for preset {$preset}");
    }
});

it('rejects duplicate exact offering keys', function () {
    $entry = catalogFixture('base', 128000)['models'][0];

    ModelCatalog::fromArray([
        'version' => 'test-v1',
        'models' => [$entry, $entry],
    ]);
})->throws(InvalidArgumentException::class, 'Duplicate model catalog entry');

it('rejects invalid support states', function () {
    $data = catalogFixture('base', 128000);
    $data['models'][0]['capabilities']['tools'] = 'probably';

    ModelCatalog::fromArray($data);
})->throws(InvalidArgumentException::class, 'Invalid support status');
