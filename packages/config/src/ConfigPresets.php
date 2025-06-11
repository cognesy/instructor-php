<?php

namespace Cognesy\Config;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Exceptions\ConfigPresetNotFoundException;

class ConfigPresets
{
    private string            $group;
    private string            $defaultPresetKey;
    private string            $presetGroupKey;
    private ?CanProvideConfig $configProvider;

    public function __construct(
        string            $group,
        string            $defaultPresetKey = 'defaultPreset',
        string            $presetGroupKey = 'presets',
        ?CanProvideConfig $configProvider = null,
    ) {
        $this->group = $group;
        $this->defaultPresetKey = $defaultPresetKey;
        $this->presetGroupKey = $presetGroupKey;
        $this->configProvider = ConfigResolver::using($configProvider);
    }

    // FLUENT CREATION API ///////////////////////////////////////////////////

    public static function using(?CanProvideConfig $config): self {
        return new self(
            group: '',
            configProvider: $config,
        );
    }

    public function for(string $group): self {
        return new self(
            group: $group,
            defaultPresetKey: $this->defaultPresetKey,
            presetGroupKey: $this->presetGroupKey,
            configProvider: $this->configProvider,
        );
    }

    public function withConfigProvider(CanProvideConfig $config): self {
        return new self(
            group           : $this->group,
            defaultPresetKey: $this->defaultPresetKey,
            presetGroupKey  : $this->presetGroupKey,
            configProvider  : ConfigResolver::using($config),
        );
    }

    // PUBLIC INTERFACE //////////////////////////////////////////////////////

    public function get(?string $preset = null): array {
        $presetName = $preset ?? $this->defaultPresetName();
        $presetPath = $this->presetPath($presetName);
        $presetConfig = $this->configProvider->get($presetPath);
        if (!is_array($presetConfig)) {
            throw new ConfigPresetNotFoundException("Preset '{$presetName}' not found at path: {$presetPath}");
        }
        return $presetConfig;
    }

    public function getOrDefault(?string $preset = null): array {
        if ($preset === null) {
            return $this->default();
        }
        if (!$this->hasPreset($preset)) {
            return $this->default();
        }
        $presetPath = $this->presetPath($preset);
        return $this->configProvider->get($presetPath);
    }

    public function hasPreset(string $preset): bool {
        $presetPath = $this->presetPath($preset);
        return $this->configProvider->has($presetPath);
    }

    public function default(): array {
        if (!$this->hasDefault()) {
            throw new ConfigPresetNotFoundException("No default preset configured for group: {$this->group}");
        }
        $defaultPresetName = $this->defaultPresetName();
        return $this->get($defaultPresetName);
    }

    public function presets(): array {
        $presetsPath = $this->path($this->presetGroupKey);
        $presets = $this->configProvider->get($presetsPath, []);
        return is_array($presets) ? array_keys($presets) : [];
    }

    // INTERNAL ////////////////////////////////////////////////////////

    private function hasDefault(): bool {
        $defaultPath = $this->path($this->defaultPresetKey);
        return $this->configProvider->has($defaultPath);
    }

    private function defaultPresetName(): string {
        $defaultPresetPath = $this->path($this->defaultPresetKey);
        $defaultPreset = $this->configProvider->get($defaultPresetPath);
        if (empty($defaultPreset)) {
            throw new ConfigPresetNotFoundException("No default preset configured at path: {$defaultPresetPath} for group: {$this->group}");
        }
        return $defaultPreset;
    }

    private function presetPath(string $presetName): string {
        return empty($this->group)
            ? "{$this->presetGroupKey}.{$presetName}"
            : "{$this->group}.{$this->presetGroupKey}.{$presetName}";
    }

    private function path(string $key) : string {
        return empty($this->group)
            ? $key
            : "{$this->group}.{$key}";
    }
}