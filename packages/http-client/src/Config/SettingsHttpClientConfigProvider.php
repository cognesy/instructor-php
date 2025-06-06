<?php

namespace Cognesy\Http\Config;

use Cognesy\Utils\Config\Providers\SettingsConfigProvider;

/**
 * @extends SettingsConfigProvider<HttpClientConfig>
 */
class SettingsHttpClientConfigProvider extends SettingsConfigProvider
{
    protected function getGroup(): string {
        return 'http';
    }

    protected function createConfig(string $preset): HttpClientConfig {
        return new HttpClientConfig(
            driver: $this->getSetting("presets.$preset.httpClientDriver", 'guzzle'),
            connectTimeout: $this->getSetting("presets.$preset.connectTimeout", 30),
            requestTimeout: $this->getSetting("presets.$preset.requestTimeout", 3),
            idleTimeout: $this->getSetting("presets.$preset.idleTimeout", 0),
            maxConcurrent: $this->getSetting("presets.$preset.maxConcurrent", 5),
            poolTimeout: $this->getSetting("presets.$preset.poolTimeout", 120),
            failOnError: $this->getSetting("presets.$preset.failOnError", false),
        );
    }
}