<?php

namespace Cognesy\Utils\Config\Providers;

use Cognesy\Utils\Config\Contracts\CanProvideConfig;
use Cognesy\Utils\Config\Exceptions\ConfigurationException;
use Cognesy\Utils\Config\Settings;
use Cognesy\Utils\Result\Result;

class SettingsConfigProvider implements CanProvideConfig
{
    public function getConfig(string $group, ?string $preset = '') : array {
        $preset = $preset ?: $this->getDefaultPreset($group);
        $this->validatePreset($group, $preset);
        return $this->getPreset($group, $preset);
    }

    private function getDefaultPreset(string $group): string {
        $result = Result::try(fn() => Settings::get($group, 'defaultPreset', ''));

        if ($result->isFailure() || empty($result->unwrap())) {
            throw new ConfigurationException(
                "No default preset found for {$group}.defaultPreset: " . ($result->isFailure() ? $result->errorMessage() : 'empty value')
            );
        }

        return $result->unwrap();
    }

    private function validatePreset(string $group, string $preset): void {
        if (!Settings::has($group, "presets.$preset")) {
            throw new ConfigurationException("Unknown {$$group} preset: $preset");
        }
    }

    protected function getPreset(string $group, string $key): array {
        return Settings::get($group, "presets.$key", []);
    }
}