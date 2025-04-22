<?php
namespace Cognesy\Polyglot\LLM\Data;

use Cognesy\Polyglot\LLM\Enums\LLMProviderType;
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
        public string $providerType = LLMProviderType::OpenAICompatible->value,
    ) {}

    public static function load(string $connection) : LLMConfig {
        if (!Settings::has('llm', "connections.$connection")) {
            throw new InvalidArgumentException("Unknown connection: $connection");
        }
        return new LLMConfig(
            apiUrl: Settings::get('llm', "connections.$connection.apiUrl"),
            apiKey: Settings::get('llm', "connections.$connection.apiKey", ''),
            endpoint: Settings::get('llm', "connections.$connection.endpoint"),
            metadata: Settings::get('llm', "connections.$connection.metadata", []),
            model: Settings::get('llm', "connections.$connection.defaultModel", ''),
            maxTokens: Settings::get('llm', "connections.$connection.defaultMaxTokens", 1024),
            contextLength: Settings::get('llm', "connections.$connection.contextLength", 8000),
            httpClient: Settings::get('llm', "connections.$connection.httpClient", ''),
            providerType: Settings::get('llm', "connections.$connection.providerType", LLMProviderType::OpenAICompatible->value),
        );
    }
}
