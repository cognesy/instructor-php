<?php
namespace Cognesy\Polyglot\LLM\Data;

use Cognesy\Utils\Dsn\DSN;
use Cognesy\Utils\Settings;
use InvalidArgumentException;

class LLMConfig
{
    public function __construct(
        public string $apiUrl = '',
        public string $apiKey = '',
        public string $endpoint = '',
        public array $queryParams = [],
        public array $metadata = [],
        public string $model = '',
        public int $maxTokens = 1024,
        public int $contextLength = 8000,
        public int $maxOutputLength = 4096,
        public string $httpClient = '',
        public string $providerType = 'openai-compatible',
        public array $options = [],
    ) {}

    public static function default() : LLMConfig {
        if (Settings::has('llm', 'defaultPreset')) {
            return self::load(Settings::get('llm', 'defaultPreset'));
        }
        return new LLMConfig();
    }

    public static function load(string $preset) : LLMConfig {
        if (!Settings::has('llm', "presets.$preset")) {
            throw new InvalidArgumentException("Unknown preset: $preset");
        }
        return new LLMConfig(
            apiUrl: Settings::get('llm', "presets.$preset.apiUrl"),
            apiKey: Settings::get('llm', "presets.$preset.apiKey", ''),
            endpoint: Settings::get('llm', "presets.$preset.endpoint"),
            queryParams: Settings::get('llm', "presets.$preset.queryParams", []),
            metadata: Settings::get('llm', "presets.$preset.metadata", []),
            model: Settings::get('llm', "presets.$preset.defaultModel", ''),
            maxTokens: Settings::get('llm', "presets.$preset.defaultMaxTokens", 1024),
            contextLength: Settings::get('llm', "presets.$preset.contextLength", 8000),
            maxOutputLength: Settings::get('llm', "presets.$preset.defaultMaxOutputLength", 4096),
            httpClient: Settings::get('llm', "presets.$preset.httpClient", ''),
            providerType: Settings::get('llm', "presets.$preset.providerType", 'openai-compatible'),
            options: Settings::get('llm', "presets.$preset.options", []),
        );
    }

    public static function fromArray(array $config) : LLMConfig {
        return new LLMConfig(
            apiUrl: $config['apiUrl'] ?? $config['api_url'] ?? '',
            apiKey: $config['apiKey'] ?? $config['api_key'] ?? '',
            endpoint: $config['endpoint'] ?? '',
            queryParams: $config['queryParams'] ?? $config['query_params'] ?? [],
            metadata: $config['metadata'] ?? [],
            model: $config['model'] ?? '',
            maxTokens: $config['maxTokens'] ?? $config['max_tokens'] ?? 1024,
            contextLength: $config['contextLength'] ?? $config['context_length'] ?? 8000,
            maxOutputLength: $config['maxOutputLength'] ?? $config['max_output_length'] ?? 4096,
            httpClient: $config['httpClient'] ?? $config['http_client'] ?? '',
            providerType: $config['providerType'] ?? $config['provider'] ?? 'openai-compatible',
            options: $config['options'] ?? [],
        );
    }

    public static function fromDSN(string $dsn) : LLMConfig {
        $data = DSN::fromString($dsn)->params();
        $preset = $data['preset'] ?? '';
        return match(true) {
            !empty($preset) => self::withOverrides(self::load($preset), $data),
            default => self::fromArray($data),
        };
    }

    private static function withOverrides(LLMConfig $config, array $overrides) : LLMConfig {
        $config->apiUrl = $overrides['apiUrl'] ?? $overrides['api_url'] ?? $config->apiUrl;
        $config->apiKey = $overrides['apiKey'] ?? $overrides['api_key'] ?? $config->apiKey;
        $config->endpoint = $overrides['endpoint'] ?? $config->endpoint;
        $config->queryParams = $overrides['queryParams'] ?? $overrides['query_params'] ?? $config->queryParams;
        $config->metadata = $overrides['metadata'] ?? $config->metadata;
        $config->model = $overrides['model'] ?? $config->model;
        $config->maxTokens = $overrides['maxTokens'] ?? $overrides['max_tokens'] ?? $config->maxTokens;
        $config->contextLength = $overrides['contextLength'] ?? $overrides['context_length'] ?? $config->contextLength;
        $config->maxOutputLength = $overrides['maxOutputLength'] ?? $overrides['max_output_length'] ?? $config->maxOutputLength;
        $config->httpClient = $overrides['httpClient'] ?? $overrides['http_client'] ?? $config->httpClient;
        $config->providerType = $overrides['providerType'] ?? $overrides['provider'] ?? $config->providerType;
        $config->options = $overrides['options'] ?? $config->options;
        return $config;
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
            'httpClient' => $this->httpClient,
            'providerType' => $this->providerType,
            'options' => $this->options,
        ];
    }
}
