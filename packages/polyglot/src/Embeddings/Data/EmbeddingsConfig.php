<?php

namespace Cognesy\Polyglot\Embeddings\Data;

use Cognesy\Utils\Config\Settings;
use Cognesy\Utils\Dsn\DSN;
use InvalidArgumentException;

class EmbeddingsConfig
{
    public function __construct(
        public string $apiUrl = '',
        public string $apiKey = '',
        public string $endpoint = '',
        public string $model = '',
        public int $dimensions = 0,
        public int $maxInputs = 0,
        public array $metadata = [],
        public string $httpClient = '',
        public string $providerType = 'openai',
    ) {}

    public static function default() : EmbeddingsConfig {
        $default = Settings::get('embed', "defaultPreset", null);
        if (is_null($default)) {
            throw new InvalidArgumentException("No default preset found in settings.");
        }
        return self::load($default);
    }

    public static function load(string $preset) : EmbeddingsConfig {
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

    public static function fromArray(array $value) : EmbeddingsConfig {
        return new static(
            apiUrl: $value['apiUrl'] ?? $value['api_url'] ?? '',
            apiKey: $value['apiKey'] ?? $value['api_key'] ?? '',
            endpoint: $value['endpoint'] ?? '',
            model: $value['model'] ?? '',
            dimensions: $value['dimensions'] ?? 0,
            maxInputs: $value['maxInputs'] ?? $value['max_inputs'] ?? 1,
            metadata: $value['metadata'] ?? [],
            httpClient: $value['httpClient'] ?? $value['http_client'] ?? '',
            providerType: $value['providerType'] ?? $value['provider'] ?? 'openai',
        );
    }

    public static function fromDSN(string $dsn) : EmbeddingsConfig {
        $data = DSN::fromString($dsn)->params();
        $preset = $data['preset'] ?? '';
        return match(true) {
            !empty($preset) => self::withOverrides(self::load($preset), $data),
            default => self::fromArray($data),
        };
    }

    private static function withOverrides(EmbeddingsConfig $config, array $overrides) : EmbeddingsConfig {
        $config->apiUrl = $overrides['apiUrl'] ?? $overrides['api_url'] ?? $config->apiUrl;
        $config->apiKey = $overrides['apiKey'] ?? $overrides['api_key'] ?? $config->apiKey;
        $config->endpoint = $overrides['endpoint'] ?? $config->endpoint;
        $config->model = $overrides['model'] ?? $config->model;
        $config->dimensions = $overrides['dimensions'] ?? $config->dimensions;
        $config->maxInputs = $overrides['maxInputs'] ?? $overrides['max_inputs'] ?? $config->maxInputs;
        $config->metadata = $overrides['metadata'] ?? $config->metadata;
        $config->httpClient = $overrides['httpClient'] ?? $overrides['http_client'] ?? $config->httpClient;
        $config->providerType = $overrides['providerType'] ?? $overrides['provider'] ?? $config->providerType;
        return $config;
    }
}
