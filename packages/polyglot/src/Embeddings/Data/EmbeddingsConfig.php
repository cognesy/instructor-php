<?php

namespace Cognesy\Polyglot\Embeddings\Data;

use Cognesy\Utils\Dsn\DSN;
use Cognesy\Utils\Settings;
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

    public static function load(string $connection) : EmbeddingsConfig {
        if (!Settings::has('embed', "connections.$connection")) {
            throw new InvalidArgumentException("Unknown connection: $connection");
        }

        return new EmbeddingsConfig(
            apiUrl: Settings::get('embed', "connections.$connection.apiUrl"),
            apiKey: Settings::get('embed', "connections.$connection.apiKey", ''),
            endpoint: Settings::get('embed', "connections.$connection.endpoint"),
            model: Settings::get('embed', "connections.$connection.defaultModel", ''),
            dimensions: Settings::get('embed', "connections.$connection.defaultDimensions", 0),
            maxInputs: Settings::get('embed', "connections.$connection.maxInputs", 1),
            metadata: Settings::get('embed', "connections.$connection.metadata", []),
            httpClient: Settings::get('embed', "connections.$connection.httpClient", ''),
            providerType: Settings::get('embed', "connections.$connection.providerType", 'openai'),
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
        $connection = $data['connection'] ?? '';
        return match(true) {
            !empty($connection) => self::withOverrides(self::load($connection), $data),
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
