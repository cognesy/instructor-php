<?php

namespace Cognesy\Polyglot\Embeddings\Data;

final class EmbeddingsConfig
{
    public function __construct(
        public string $apiUrl = '',
        public string $apiKey = '',
        public string $endpoint = '',
        public string $model = '',
        public int    $dimensions = 0,
        public int    $maxInputs = 0,
        public array  $metadata = [],
        public string $httpClientPreset = '',
        public string $driver = 'openai',
    ) {}

    public static function fromArray(array $value) : EmbeddingsConfig {
        return new static(
            apiUrl    : $value['apiUrl'] ?? $value['api_url'] ?? '',
            apiKey    : $value['apiKey'] ?? $value['api_key'] ?? '',
            endpoint  : $value['endpoint'] ?? '',
            model     : $value['model'] ?? '',
            dimensions: $value['dimensions'] ?? 0,
            maxInputs : $value['maxInputs'] ?? $value['max_inputs'] ?? 1,
            metadata  : $value['metadata'] ?? [],
            httpClientPreset: $value['httpClient']
                ?? $value['http_client']
                ?? $value['httpClientPreset']
                ?? $value['http_client_preset']
                ?? '',
            driver    : $value['driver'] ?? 'openai',
        );
    }

    public function withOverrides(array $overrides) : EmbeddingsConfig {
        $this->apiUrl = $overrides['apiUrl'] ?? $overrides['api_url'] ?? $this->apiUrl;
        $this->apiKey = $overrides['apiKey'] ?? $overrides['api_key'] ?? $this->apiKey;
        $this->endpoint = $overrides['endpoint'] ?? $this->endpoint;
        $this->model = $overrides['model'] ?? $this->model;
        $this->dimensions = $overrides['dimensions'] ?? $this->dimensions;
        $this->maxInputs = $overrides['maxInputs']
            ?? $overrides['max_inputs']
            ?? $this->maxInputs;
        $this->metadata = $overrides['metadata'] ?? $this->metadata;
        $this->httpClientPreset = $overrides['httpClient']
            ?? $overrides['http_client']
            ?? $overrides['httpClientPreset']
            ?? $overrides['http_client_preset']
            ?? $this->httpClientPreset;
        $this->driver = $overrides['driver'] ?? $this->driver;
        return $this;
    }
}
