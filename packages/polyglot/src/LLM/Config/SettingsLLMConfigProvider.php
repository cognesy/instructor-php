<?php

namespace Cognesy\Polyglot\LLM\Config;

use Cognesy\Utils\Config\Providers\SettingsConfigProvider;

/**
 * @extends SettingsConfigProvider<LLMConfig>
 */
class SettingsLLMConfigProvider extends SettingsConfigProvider
{
    protected function getGroup(): string {
        return 'llm';
    }

    protected function createConfig(string $preset): LLMConfig {
        return new LLMConfig(
            apiUrl          : $this->getSetting("presets.$preset.apiUrl", ''),
            apiKey          : $this->getSetting("presets.$preset.apiKey", ''),
            endpoint        : $this->getSetting("presets.$preset.endpoint", ''),
            queryParams     : $this->getSetting("presets.$preset.queryParams", []),
            metadata        : $this->getSetting("presets.$preset.metadata", []),
            defaultModel    : $this->getSetting("presets.$preset.defaultModel", ''),
            defaultMaxTokens: $this->getSetting("presets.$preset.defaultMaxTokens", 1000),
            contextLength   : $this->getSetting("presets.$preset.contextLength", 8000),
            maxOutputLength : $this->getSetting("presets.$preset.maxOutputLength", 4096),
            httpClientPreset: $this->getSetting("presets.$preset.httpClientPreset", ''),
            driver          : $this->getSetting("presets.$preset.driver", 'openai'),
            options         : $this->getSetting("presets.$preset.options", []),
        );
    }
}
