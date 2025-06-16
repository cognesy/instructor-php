<?php

namespace Cognesy\Polyglot\Embeddings\Config;

use Cognesy\Config\Exceptions\ConfigurationException;
use Throwable;

final class EmbeddingsConfig
{
    public const CONFIG_GROUP = 'embed';

    public static function group() : string {
        return self::CONFIG_GROUP;
    }

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

    public static function fromArray(array $config) : EmbeddingsConfig {
        try {
            $instance = new self(...$config);
        } catch (Throwable $e) {
            $data = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            throw new ConfigurationException(
                message: "Invalid configuration for EmbeddingsConfig: {$e->getMessage()}\nData: {$data}",
                previous: $e,
            );
        }
        return $instance;
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
