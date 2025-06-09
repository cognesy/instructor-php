<?php

namespace Cognesy\Config;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Exceptions\ConfigPresetNotFoundException;

class ConfigPresets
{
    public function __construct(
        private string $group,
        private string $defaultPresetKey = 'defaultPreset',
        private string $presetGroupKey = 'presets',
        private ?CanProvideConfig $config = null,
    ) {
        if ($this->config === null) {
            $this->config = ConfigResolver::default();
        }
    }

    // FLUENT CREATION API ///////////////////////////////////////////////////

    public static function using(
        ?CanProvideConfig $config,
    ): self {
        return new self(
            group: '',
            config: $config ?? ConfigResolver::default(),
        );
    }

    public function for(string $group): self {
        return new self(
            group: $group,
            config: $this->config,
        );
    }

    public function withConfigProvider(CanProvideConfig $config): self {
        return new self(
            group: $this->group,
            defaultPresetKey: $this->defaultPresetKey,
            presetGroupKey: $this->presetGroupKey,
            config: $config,
        );
    }

    // PUBLIC INTERFACE //////////////////////////////////////////////////////

    public function get(?string $preset = null): array {
        // If no preset specified, get the default preset name
        $presetName = $preset ?? $this->defaultPresetName();

        // Build path to the actual preset configuration
        $presetPath = $this->presetPath($presetName);

        // Get the preset configuration
        $presetConfig = $this->config->get($presetPath);

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
        return $this->config->get($presetPath);
    }

    public function hasPreset(string $preset): bool {
        $presetPath = $this->presetPath($preset);
        return $this->config->has($presetPath);
    }

    public function default(): array {
        if (!$this->hasDefault()) {
            throw new ConfigPresetNotFoundException("No default preset configured at path: {$this->defaultPresetKey}");
        }
        $defaultPresetName = $this->defaultPresetName();
        return $this->get($defaultPresetName);
    }

    public function presets(): array {
        $presetsPath = $this->path($this->presetGroupKey);
        $presets = $this->config->get($presetsPath, []);
        return is_array($presets) ? array_keys($presets) : [];
    }

    // INTERNAL ////////////////////////////////////////////////////////

    private function hasDefault(): bool {
        $defaultPath = $this->path($this->defaultPresetKey);
        return $this->config->has($defaultPath);
    }

    private function defaultPresetName(): string {
        $defaultPath = $this->path($this->defaultPresetKey);
        $defaultPreset = $this->config->get($defaultPath);
        if (empty($defaultPreset)) {
            throw new ConfigPresetNotFoundException("No default preset configured at path: {$defaultPath}");
        }
        return $defaultPreset;
    }

    private function presetPath(string $presetName): string {
        return $this->path("{$this->presetGroupKey}.{$presetName}");
    }

    private function path(string $key) : string {
        return empty($this->group) ? $key : "{$this->group}.{$key}";
    }
}