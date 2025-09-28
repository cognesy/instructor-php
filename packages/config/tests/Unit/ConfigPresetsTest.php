<?php declare(strict_types=1);

namespace Cognesy\Config\Tests\Unit;

use Cognesy\Config\ConfigPresets;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Exceptions\ConfigPresetNotFoundException;
use Cognesy\Config\Exceptions\ConfigurationException;
use Cognesy\Config\Tests\TestCase;

class ConfigPresetsTest extends TestCase
{
    private function createMockProvider(array $data = []): CanProvideConfig
    {
        return new class($data) implements CanProvideConfig {
            public function __construct(private array $data) {}

            public function get(string $path, mixed $default = null): mixed
            {
                $keys = explode('.', $path);
                $current = $this->data;

                foreach ($keys as $key) {
                    if (!isset($current[$key])) {
                        return $default;
                    }
                    $current = $current[$key];
                }

                return $current;
            }

            public function has(string $path): bool
            {
                $keys = explode('.', $path);
                $current = $this->data;

                foreach ($keys as $key) {
                    if (!isset($current[$key])) {
                        return false;
                    }
                    $current = $current[$key];
                }

                return true;
            }
        };
    }

    private function createConfigData(): array
    {
        return [
            'database' => [
                'defaultPreset' => 'mysql',
                'presets' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'host' => 'localhost',
                        'port' => 3306,
                    ],
                    'postgres' => [
                        'driver' => 'pgsql',
                        'host' => 'localhost',
                        'port' => 5432,
                    ],
                ],
            ],
            'cache' => [
                'defaultPreset' => 'redis',
                'presets' => [
                    'redis' => [
                        'driver' => 'redis',
                        'host' => 'localhost',
                    ],
                    'memory' => [
                        'driver' => 'array',
                    ],
                ],
            ],
        ];
    }

    public function test_constructor_creates_instance(): void
    {
        $provider = $this->createMockProvider();
        $presets = new ConfigPresets('database', configProvider: $provider);

        expect($presets)->toBeInstanceOf(ConfigPresets::class);
    }

    public function test_using_static_factory(): void
    {
        $provider = $this->createMockProvider();
        $presets = ConfigPresets::using($provider);

        expect($presets)->toBeInstanceOf(ConfigPresets::class);
    }

    public function test_for_sets_group(): void
    {
        $provider = $this->createMockProvider($this->createConfigData());
        $presets = ConfigPresets::using($provider)->for('database');

        expect($presets->default())->toEqual([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
        ]);
    }

    public function test_with_config_provider_replaces_provider(): void
    {
        $provider1 = $this->createMockProvider([]);
        $provider2 = $this->createMockProvider($this->createConfigData());

        $presets = ConfigPresets::using($provider1)
            ->for('database')
            ->withConfigProvider($provider2);

        expect($presets->default())->toEqual([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
        ]);
    }

    public function test_get_returns_specific_preset(): void
    {
        $provider = $this->createMockProvider($this->createConfigData());
        $presets = ConfigPresets::using($provider)->for('database');

        $mysql = $presets->get('mysql');
        $postgres = $presets->get('postgres');

        expect($mysql)->toEqual([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
        ]);

        expect($postgres)->toEqual([
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
        ]);
    }

    public function test_get_without_preset_returns_default(): void
    {
        $provider = $this->createMockProvider($this->createConfigData());
        $presets = ConfigPresets::using($provider)->for('database');

        expect($presets->get())->toEqual([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
        ]);
    }

    public function test_get_throws_when_preset_not_found(): void
    {
        $provider = $this->createMockProvider($this->createConfigData());
        $presets = ConfigPresets::using($provider)->for('database');

        $this->expectException(ConfigurationException::class);
        $presets->get('nonexistent');
    }

    public function test_get_throws_when_preset_is_not_array(): void
    {
        $provider = $this->createMockProvider([
            'database' => [
                'presets' => [
                    'invalid' => 'not-an-array'
                ]
            ]
        ]);
        $presets = ConfigPresets::using($provider)->for('database');

        expect(fn() => $presets->get('invalid'))
            ->toThrow(ConfigPresetNotFoundException::class, "Preset 'invalid' not found at path: database.presets.invalid");
    }

    public function test_get_or_default_returns_preset_when_exists(): void
    {
        $provider = $this->createMockProvider($this->createConfigData());
        $presets = ConfigPresets::using($provider)->for('database');

        expect($presets->getOrDefault('postgres'))->toEqual([
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
        ]);
    }

    public function test_get_or_default_returns_default_when_preset_missing(): void
    {
        $provider = $this->createMockProvider($this->createConfigData());
        $presets = ConfigPresets::using($provider)->for('database');

        expect($presets->getOrDefault('nonexistent'))->toEqual([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
        ]);
    }

    public function test_get_or_default_with_null_returns_default(): void
    {
        $provider = $this->createMockProvider($this->createConfigData());
        $presets = ConfigPresets::using($provider)->for('database');

        expect($presets->getOrDefault(null))->toEqual([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
        ]);
    }

    public function test_has_preset_returns_true_when_exists(): void
    {
        $provider = $this->createMockProvider($this->createConfigData());
        $presets = ConfigPresets::using($provider)->for('database');

        expect($presets->hasPreset('mysql'))->toBeTrue();
        expect($presets->hasPreset('postgres'))->toBeTrue();
    }

    public function test_has_preset_returns_false_when_missing(): void
    {
        $provider = $this->createMockProvider($this->createConfigData());
        $presets = ConfigPresets::using($provider)->for('database');

        expect($presets->hasPreset('nonexistent'))->toBeFalse();
    }

    public function test_default_returns_default_preset(): void
    {
        $provider = $this->createMockProvider($this->createConfigData());
        $presets = ConfigPresets::using($provider)->for('cache');

        expect($presets->default())->toEqual([
            'driver' => 'redis',
            'host' => 'localhost',
        ]);
    }

    public function test_default_throws_when_no_default_configured(): void
    {
        $provider = $this->createMockProvider([
            'database' => [
                'presets' => [
                    'mysql' => ['driver' => 'mysql']
                ]
            ]
        ]);
        $presets = ConfigPresets::using($provider)->for('database');

        expect(fn() => $presets->default())
            ->toThrow(ConfigPresetNotFoundException::class, 'No default preset configured for group: database');
    }

    public function test_default_throws_when_default_preset_empty(): void
    {
        $provider = $this->createMockProvider([
            'database' => [
                'defaultPreset' => '',
                'presets' => [
                    'mysql' => ['driver' => 'mysql']
                ]
            ]
        ]);
        $presets = ConfigPresets::using($provider)->for('database');

        expect(fn() => $presets->default())
            ->toThrow(ConfigPresetNotFoundException::class, 'No default preset configured at path: database.defaultPreset for group: database');
    }

    public function test_presets_returns_available_preset_names(): void
    {
        $provider = $this->createMockProvider($this->createConfigData());
        $presets = ConfigPresets::using($provider)->for('database');

        expect($presets->presets())->toEqual(['mysql', 'postgres']);
    }

    public function test_presets_returns_empty_array_when_no_presets(): void
    {
        $provider = $this->createMockProvider([
            'database' => [
                'defaultPreset' => 'mysql'
            ]
        ]);
        $presets = ConfigPresets::using($provider)->for('database');

        expect($presets->presets())->toEqual([]);
    }

    public function test_presets_returns_empty_array_when_presets_not_array(): void
    {
        $provider = $this->createMockProvider([
            'database' => [
                'presets' => 'not-an-array'
            ]
        ]);
        $presets = ConfigPresets::using($provider)->for('database');

        expect($presets->presets())->toEqual([]);
    }

    public function test_works_without_group_prefix(): void
    {
        $provider = $this->createMockProvider([
            'defaultPreset' => 'default',
            'presets' => [
                'default' => ['key' => 'value']
            ]
        ]);
        $presets = ConfigPresets::using($provider)->for('');

        expect($presets->get('default'))->toEqual(['key' => 'value']);
        expect($presets->default())->toEqual(['key' => 'value']);
        expect($presets->presets())->toEqual(['default']);
    }

    public function test_custom_keys(): void
    {
        $provider = $this->createMockProvider([
            'database' => [
                'mainPreset' => 'mysql',
                'configurations' => [
                    'mysql' => ['driver' => 'mysql']
                ]
            ]
        ]);

        $presets = new ConfigPresets(
            group: 'database',
            defaultPresetKey: 'mainPreset',
            presetGroupKey: 'configurations',
            configProvider: $provider
        );

        expect($presets->default())->toEqual(['driver' => 'mysql']);
        expect($presets->presets())->toEqual(['mysql']);
    }
}