<?php

namespace Cognesy\Polyglot\Embeddings\Config;

final class EmbeddingsConfig
{
    public const CONFIG_GROUP = 'embed';

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

    public static function group() : string {
        return self::CONFIG_GROUP;
    }

    public static function fromArray(array $value) : EmbeddingsConfig {
        return new static(
            apiUrl    : $value['apiUrl'] ?? $value['api_url'] ?? '',
            apiKey    : $value['apiKey'] ?? $value['api_key'] ?? '',
            endpoint  : $value['endpoint'] ?? '',
            model     : $value['defaultModel'] ?? '',
            dimensions: $value['defaultDimensions'] ?? 0,
            maxInputs : $value['maxInputs'] ?? $value['max_inputs'] ?? 1,
            metadata  : $value['metadata'] ?? [],
            httpClientPreset: $value['httpClientPreset']
                ?? $value['http_client_preset']
                ?? $value['http_client']
                ?? $value['httpClient']
                ?? '',
            driver    : $value['driver'] ?? 'openai',
        );
    }

    public function toArray() : array {
        return [
            'apiUrl' => $this->apiUrl,
            'apiKey' => $this->apiKey,
            'endpoint' => $this->endpoint,
            'defaultModel' => $this->model,
            'defaultDimensions' => $this->dimensions,
            'maxInputs' => $this->maxInputs,
            'metadata' => $this->metadata,
            'httpClientPreset' => $this->httpClientPreset,
            'driver' => $this->driver,
        ];
    }
}
