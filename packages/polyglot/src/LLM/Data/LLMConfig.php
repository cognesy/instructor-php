<?php

namespace Cognesy\Polyglot\LLM\Data;

final class LLMConfig
{
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
        public string $providerType = 'openai-compatible',
        public array  $options = [],
    ) {}

    public static function fromArray(array $config) : LLMConfig {
        return new LLMConfig(
            apiUrl         : $config['apiUrl'] ?? $config['api_url'] ?? '',
            apiKey         : $config['apiKey'] ?? $config['api_key'] ?? '',
            endpoint       : $config['endpoint'] ?? '',
            queryParams    : $config['queryParams'] ?? $config['query_params'] ?? [],
            metadata       : $config['metadata'] ?? [],
            model          : $config['model'] ?? '',
            maxTokens      : $config['maxTokens'] ?? $config['max_tokens'] ?? 1024,
            contextLength  : $config['contextLength'] ?? $config['context_length'] ?? 8000,
            maxOutputLength: $config['maxOutputLength'] ?? $config['max_output_length'] ?? 4096, httpClientPreset: $config['httpClient'] ?? $config['http_client'] ?? '',
            providerType   : $config['providerType'] ?? $config['provider'] ?? 'openai-compatible',
            options        : $config['options'] ?? [],
        );
    }

    public function withOverrides(array $overrides) : LLMConfig {
        $this->apiUrl = $overrides['apiUrl'] ?? $overrides['api_url'] ?? $this->apiUrl;
        $this->apiKey = $overrides['apiKey'] ?? $overrides['api_key'] ?? $this->apiKey;
        $this->endpoint = $overrides['endpoint'] ?? $this->endpoint;
        $this->queryParams = $overrides['queryParams'] ?? $overrides['query_params'] ?? $this->queryParams;
        $this->metadata = $overrides['metadata'] ?? $this->metadata;
        $this->model = $overrides['model'] ?? $this->model;
        $this->maxTokens = $overrides['maxTokens'] ?? $overrides['max_tokens'] ?? $this->maxTokens;
        $this->contextLength = $overrides['contextLength'] ?? $overrides['context_length'] ?? $this->contextLength;
        $this->maxOutputLength = $overrides['maxOutputLength'] ?? $overrides['max_output_length'] ?? $this->maxOutputLength;
        $this->httpClientPreset = $overrides['httpClient'] ?? $overrides['http_client'] ?? $this->httpClientPreset;
        $this->providerType = $overrides['providerType'] ?? $overrides['provider'] ?? $this->providerType;
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
            'model' => $this->model,
            'maxTokens' => $this->maxTokens,
            'contextLength' => $this->contextLength,
            'maxOutputLength' => $this->maxOutputLength,
            'httpClient' => $this->httpClientPreset,
            'providerType' => $this->providerType,
            'options' => $this->options,
        ];
    }
}
