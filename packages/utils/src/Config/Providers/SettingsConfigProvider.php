<?php

namespace Cognesy\Utils\Config\Providers;

use Cognesy\Utils\Config\Contracts\CanProvideConfig;
use Cognesy\Utils\Config\Settings;
use Cognesy\Utils\Result\Result;

/**
 * Base class for settings-based config providers
 *
 * @template T
 * @implements CanProvideConfig<T>
 */
abstract class SettingsConfigProvider implements CanProvideConfig
{
    protected abstract function getGroup(): string;

    /**
     * @param string $preset
     * @return T
     */
    protected abstract function createConfig(string $preset): object;

    /**
     * @param string|null $preset
     * @return T
     */
    public function getConfig(?string $preset = '') {
        $preset = $preset ?: $this->getDefaultPreset();
        $this->validatePreset($preset);
        return $this->createConfig($preset);
    }

    private function getDefaultPreset(): string
    {
        $result = Result::try(fn() => Settings::get($this->getGroup(), 'defaultPreset', ''));

        if ($result->isFailure() || empty($result->unwrap())) {
            throw new \Exception(
                "No default preset found for {$this->getGroup()}.defaultPreset: " .
                ($result->isFailure() ? $result->errorMessage() : 'empty value')
            );
        }

        return $result->unwrap();
    }

    private function validatePreset(string $preset): void
    {
        if (!Settings::has($this->getGroup(), "presets.$preset")) {
            throw new \InvalidArgumentException("Unknown {$this->getGroup()} preset: $preset");
        }
    }

    protected function getSetting(string $key, mixed $default = null): mixed
    {
        return Settings::get($this->getGroup(), $key, $default);
    }
}