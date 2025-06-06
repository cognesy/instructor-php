<?php

namespace Cognesy\Polyglot\Embeddings\Config;

use Cognesy\Utils\Config\Providers\SettingsConfigProvider;

/**
 * @extends SettingsConfigProvider<EmbeddingsConfig>
 */
class SettingsEmbeddingsConfigProvider extends SettingsConfigProvider
{
    protected function getGroup(): string {
        return 'embed';
    }

    protected function createConfig(string $preset): EmbeddingsConfig {
        return new EmbeddingsConfig(
            apiUrl          : $this->getSetting("presets.$preset.apiUrl", ''),
            apiKey          : $this->getSetting("presets.$preset.apiKey", ''),
            endpoint        : $this->getSetting("presets.$preset.endpoint", ''),
            model           : $this->getSetting("presets.$preset.defaultModel", ''),
            dimensions      : $this->getSetting("presets.$preset.defaultDimensions", 0),
            maxInputs       : $this->getSetting("presets.$preset.maxInputs", 1),
            metadata        : $this->getSetting("presets.$preset.metadata", []),
            httpClientPreset: $this->getSetting("presets.$preset.httpClientPreset", ''),
            driver          : $this->getSetting("presets.$preset.driver", 'openai'),
        );
    }
}
