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

    public static function load(string $connection) : LLMConfig {
        if (!Settings::has('llm', "connections.$connection")) {
            throw new InvalidArgumentException("Unknown connection: $connection");
        }
        return new LLMConfig(
            apiUrl: Settings::get('llm', "connections.$connection.apiUrl"),
            apiKey: Settings::get('llm', "connections.$connection.apiKey", ''),
            endpoint: Settings::get('llm', "connections.$connection.endpoint"),
            queryParams: Settings::get('llm', "connections.$connection.queryParams", []),
            metadata: Settings::get('llm', "connections.$connection.metadata", []),
            model: Settings::get('llm', "connections.$connection.defaultModel", ''),
            maxTokens: Settings::get('llm', "connections.$connection.defaultMaxTokens", 1024),
            contextLength: Settings::get('llm', "connections.$connection.contextLength", 8000),
            maxOutputLength: Settings::get('llm', "connections.$connection.defaultMaxOutputLength", 4096),
            httpClient: Settings::get('llm', "connections.$connection.httpClient", ''),
            providerType: Settings::get('llm', "connections.$connection.providerType", 'openai-compatible'),
            options: Settings::get('llm', "connections.$connection.options", []),
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
        $connection = $data['connection'] ?? '';
        return match(true) {
            !empty($connection) => self::withOverrides(self::load($connection), $data),
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
}
