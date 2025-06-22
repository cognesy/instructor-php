<?php

namespace Cognesy\Polyglot\Inference\Config;

use Cognesy\Config\Exceptions\ConfigurationException;
use Throwable;

final class LLMConfig
{
    public const CONFIG_GROUP = 'llm';

    public static function group() : string {
        return self::CONFIG_GROUP;
    }

    public function __construct(
        public string $apiUrl = '',
        public string $apiKey = '',
        public string $endpoint = '',
        public array  $queryParams = [],
        public array  $metadata = [],
        public string $model = '',
        public int    $maxTokens = 1024,
        public int    $contextLength = 8000,
        public int    $maxOutputLength = 4096,
        public string $httpClientPreset = '',
        public string $driver = 'openai-compatible',
        public array  $options = [],
    ) {}

    public static function fromArray(array $config) : LLMConfig {
        try {
            $instance = new self(...$config);
        } catch (Throwable $e) {
            $data = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            throw new ConfigurationException(
                message: "Invalid configuration for LLMConfig: {$e->getMessage()}\nData: {$data}",
                previous: $e,
            );
        }
        return $instance;
    }

    public function withOverrides(array $overrides) : self {
        $config = array_merge($this->toArray(), $overrides);
        return self::fromArray($config);
    }

//    public function withOverrides(array $overrides) : LLMConfig {
//        $this->apiUrl = $overrides['apiUrl'] ?? $overrides['api_url'] ?? $this->apiUrl;
//        $this->apiKey = $overrides['apiKey'] ?? $overrides['api_key'] ?? $this->apiKey;
//        $this->endpoint = $overrides['endpoint'] ?? $this->endpoint;
//        $this->queryParams = $overrides['queryParams'] ?? $overrides['query_params'] ?? $this->queryParams;
//        $this->metadata = $overrides['metadata'] ?? $this->metadata;
//        $this->model = $overrides['model'] ?? $overrides['model'] ?? $this->model;
//        $this->maxTokens = $overrides['maxTokens']
//            ?? $overrides['max_tokens']
//            ?? $overrides['maxTokens']
//            ?? $overrides['default_max_tokens']
//            ?? $this->maxTokens;
//        $this->contextLength = $overrides['contextLength'] ?? $overrides['context_length'] ?? $this->contextLength;
//        $this->maxOutputLength = $overrides['maxOutputLength'] ?? $overrides['max_output_length'] ?? $this->maxOutputLength;
//        $this->httpClientPreset = $overrides['httpClientPreset']
//            ?? $overrides['http_client']
//            ?? $overrides['httpClientPreset']
//            ?? $overrides['http_client_preset']
//            ?? $this->httpClientPreset;
//        $this->driver = $overrides['driverType'] ?? $overrides['driver'] ?? $this->driver;
//        $this->options = $overrides['options'] ?? $this->options;
//        return $this;
//    }

    public function toArray() : array {
        return [
            'apiUrl' => $this->apiUrl,
            'apiKey' => $this->apiKey,
            'endpoint' => $this->endpoint,
            'queryParams' => $this->queryParams,
            'metadata' => $this->metadata,
            'model' => $this->model,
            'maxTokens' => $this->maxTokens,
            'contextLength' => $this->contextLength,
            'maxOutputLength' => $this->maxOutputLength,
            'httpClientPreset' => $this->httpClientPreset,
            'driver' => $this->driver,
            'options' => $this->options,
        ];
    }
}
