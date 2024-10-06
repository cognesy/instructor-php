<?php
namespace Cognesy\Instructor\Extras\LLM\Data;

use Cognesy\Instructor\Extras\LLM\Enums\LLMProviderType;
use Cognesy\Instructor\Utils\Settings;
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
        public LLMProviderType $providerType = LLMProviderType::OpenAICompatible,
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
            providerType: LLMProviderType::from(Settings::get('llm', "connections.$connection.providerType")),
        );
    }
}
