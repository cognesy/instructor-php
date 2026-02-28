<?php declare(strict_types=1);

use Cognesy\Config\ConfigPresets;
use Cognesy\Config\Exceptions\ConfigPresetNotFoundException;
use Cognesy\Config\Providers\ArrayConfigProvider;

// Guards regression from instructor-ajwq (malformed preset config leaks TypeError).
it('throws domain exception when default preset name is not a string', function () {
    $provider = new ArrayConfigProvider([
        'database' => [
            'defaultPreset' => 123,
            'presets' => [
                'mysql' => ['driver' => 'mysql'],
            ],
        ],
    ]);
    $presets = ConfigPresets::using($provider)->for('database');

    expect(fn() => $presets->default())
        ->toThrow(ConfigPresetNotFoundException::class, 'database.defaultPreset');
});

it('throws domain exception when getOrDefault resolves non-array preset payload', function () {
    $provider = new ArrayConfigProvider([
        'database' => [
            'defaultPreset' => 'mysql',
            'presets' => [
                'mysql' => ['driver' => 'mysql'],
                'broken' => 'not-an-array',
            ],
        ],
    ]);
    $presets = ConfigPresets::using($provider)->for('database');

    expect(fn() => $presets->getOrDefault('broken'))
        ->toThrow(ConfigPresetNotFoundException::class, 'database.presets.broken');
});

