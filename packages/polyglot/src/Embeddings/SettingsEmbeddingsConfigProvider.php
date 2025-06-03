<?php

namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Polyglot\Embeddings\Contracts\CanProvideEmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Utils\Config\Settings;
use Cognesy\Utils\Result\Result;
use InvalidArgumentException;

class SettingsEmbeddingsConfigProvider implements CanProvideEmbeddingsConfig
{
    public function getConfig(?string $preset = ''): EmbeddingsConfig {
        if (empty($preset)) {
            $result = Result::try(fn() => Settings::get('embed', 'defaultPreset', ''));
            $preset = $result->isSuccess() ? $result->unwrap() : '';
            if (empty($preset)) {
                return new EmbeddingsConfig();
            }
        }

        if (!Settings::has('embed', "presets.$preset")) {
            throw new InvalidArgumentException("Unknown connection preset: $preset");
        }

        return new EmbeddingsConfig(
            apiUrl: Settings::get('embed', "presets.$preset.apiUrl"),
            apiKey: Settings::get('embed', "presets.$preset.apiKey", ''),
            endpoint: Settings::get('embed', "presets.$preset.endpoint"),
            model: Settings::get('embed', "presets.$preset.defaultModel", ''),
            dimensions: Settings::get('embed', "presets.$preset.defaultDimensions", 0),
            maxInputs: Settings::get('embed', "presets.$preset.maxInputs", 1),
            metadata: Settings::get('embed', "presets.$preset.metadata", []),
            httpClient: Settings::get('embed', "presets.$preset.httpClient", ''),
            providerType: Settings::get('embed', "presets.$preset.providerType", 'openai'),
        );
    }
}