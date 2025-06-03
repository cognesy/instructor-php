<?php

namespace Cognesy\Polyglot\LLM;

use Cognesy\Polyglot\LLM\Contracts\CanProvideLLMConfig;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Utils\Config\Settings;
use InvalidArgumentException;

class SettingsLLMConfigProvider implements CanProvideLLMConfig
{
    public function getConfig(?string $preset = '') : LLMConfig
    {
        if (empty($preset)) {
            $preset = Settings::get('llm', 'defaultPreset', '');
            if (empty($preset)) {
                return new LLMConfig();
            }
        }

        if (!Settings::has('llm', "presets.$preset")) {
            throw new InvalidArgumentException("Unknown LLM config preset: $preset");
        }

        return new LLMConfig(
            apiUrl         : Settings::get('llm', "presets.$preset.apiUrl"),
            apiKey         : Settings::get('llm', "presets.$preset.apiKey", ''),
            endpoint       : Settings::get('llm', "presets.$preset.endpoint"),
            queryParams    : Settings::get('llm', "presets.$preset.queryParams", []),
            metadata       : Settings::get('llm', "presets.$preset.metadata", []),
            model          : Settings::get('llm', "presets.$preset.defaultModel", ''),
            maxTokens      : Settings::get('llm', "presets.$preset.defaultMaxTokens", 1024),
            contextLength  : Settings::get('llm', "presets.$preset.contextLength", 8000),
            maxOutputLength: Settings::get('llm', "presets.$preset.defaultMaxOutputLength", 4096), httpClientPreset: Settings::get('llm', "presets.$preset.httpClientPreset", ''),
            providerType   : Settings::get('llm', "presets.$preset.providerType", 'openai-compatible'),
            options        : Settings::get('llm', "presets.$preset.options", []),
        );
    }
}