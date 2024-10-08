<?php

namespace Cognesy\Instructor\Extras\Embeddings\Data;

use Cognesy\Instructor\Features\LLM\Enums\LLMProviderType;
use Cognesy\Instructor\Utils\Settings;
use InvalidArgumentException;

class EmbeddingsConfig
{
    public function __construct(
        public string $apiUrl = '',
        public string $apiKey = '',
        public string $endpoint = '',
        public string $model = '',
        public int $dimensions = 0,
        public int $maxInputs = 0,
        public array $metadata = [],
        public string $httpClient = '',
        public ?LLMProviderType $providerType = null,
    ) {}

    public static function load(string $connection) : EmbeddingsConfig {
        if (!Settings::has('embed', "connections.$connection")) {
            throw new InvalidArgumentException("Unknown connection: $connection");
        }
        return new EmbeddingsConfig(
            apiUrl: Settings::get('embed', "connections.$connection.apiUrl"),
            apiKey: Settings::get('embed', "connections.$connection.apiKey", ''),
            endpoint: Settings::get('embed', "connections.$connection.endpoint"),
            model: Settings::get('embed', "connections.$connection.defaultModel", ''),
            dimensions: Settings::get('embed', "connections.$connection.defaultDimensions", 0),
            maxInputs: Settings::get('embed', "connections.$connection.maxInputs", 1),
            metadata: Settings::get('embed', "connections.$connection.metadata", []),
            httpClient: Settings::get('embed', "connections.$connection.httpClient", ''),
            providerType: LLMProviderType::from(Settings::get('embed', "connections.$connection.providerType")),
        );
    }
}
