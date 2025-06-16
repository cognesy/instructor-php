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
        public string $defaultModel = '',
        public int    $defaultMaxTokens = 1024,
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

    public function withOverrides(array $overrides) : LLMConfig {
        $this->apiUrl = $overrides['apiUrl'] ?? $overrides['api_url'] ?? $this->apiUrl;
        $this->apiKey = $overrides['apiKey'] ?? $overrides['api_key'] ?? $this->apiKey;
        $this->endpoint = $overrides['endpoint'] ?? $this->endpoint;
        $this->queryParams = $overrides['queryParams'] ?? $overrides['query_params'] ?? $this->queryParams;
        $this->metadata = $overrides['metadata'] ?? $this->metadata;
        $this->defaultModel = $overrides['model'] ?? $overrides['defaultModel'] ?? $this->defaultModel;
        $this->defaultMaxTokens = $overrides['maxTokens']
            ?? $overrides['max_tokens']
            ?? $overrides['defaultMaxTokens']
            ?? $overrides['default_max_tokens']
            ?? $this->defaultMaxTokens;
        $this->contextLength = $overrides['contextLength'] ?? $overrides['context_length'] ?? $this->contextLength;
        $this->maxOutputLength = $overrides['maxOutputLength'] ?? $overrides['max_output_length'] ?? $this->maxOutputLength;
        $this->httpClientPreset = $overrides['httpClient']
            ?? $overrides['http_client']
            ?? $overrides['httpClientPreset']
            ?? $overrides['http_client_preset']
            ?? $this->httpClientPreset;
        $this->driver = $overrides['driverType'] ?? $overrides['driver'] ?? $this->driver;
        $this->options = $overrides['options'] ?? $this->options;
        return $this;
    }

    public function toArray() : array {
        return [
            'apiUrl' => $this->apiUrl,
            'apiKey' => $this->apiKey,
            'endpoint' => $this->endpoint,
            'queryParams' => $this->queryParams,
            'metadata' => $this->metadata,
            'defaultModel' => $this->defaultModel,
            'defaultMaxTokens' => $this->defaultMaxTokens,
            'contextLength' => $this->contextLength,
            'maxOutputLength' => $this->maxOutputLength,
            'httpClient' => $this->httpClientPreset,
            'driver' => $this->driver,
            'options' => $this->options,
        ];
    }
}
