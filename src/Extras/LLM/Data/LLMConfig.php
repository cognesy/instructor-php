<?php

namespace Cognesy\Instructor\Extras\LLM\Data;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Extras\Enums\HttpClientType;
use Cognesy\Instructor\Utils\Settings;
use InvalidArgumentException;

class LLMConfig
{
    public function __construct(
        public ClientType $clientType = ClientType::OpenAICompatible,
        public HttpClientType $httpClient = HttpClientType::Guzzle,
        public string $apiUrl = '',
        public string $apiKey = '',
        public string $endpoint = '',
        public array $queryParams = [],
        public array $metadata = [],
        public int $connectTimeout = 3,
        public int $requestTimeout = 30,
        public string $model = '',
        public int $maxTokens = 1024,
        public ?DebugConfig $debug = null,
    ) {
        $this->debug ??= new DebugConfig();
    }

    public function debugEnabled() : bool {
        return $this->debug->enabled;
    }

    public function debugSection(string $section) : bool {
        return $this->debug->enabled && ($this->debug->$section ?? false);
    }

    public function debugHttpDetails() : bool {
        return $this->debug->detailed && $this->debug->enabled;
    }

    public static function load(string $connection) : LLMConfig {
        if (!Settings::has('llm', "connections.$connection")) {
            throw new InvalidArgumentException("Unknown connection: $connection");
        }

        return new LLMConfig(
            clientType: ClientType::from(Settings::get('llm', "connections.$connection.clientType")),
            httpClient: HttpClientType::from(Settings::get('llm', "connections.$connection.httpClient")),
            apiUrl: Settings::get('llm', "connections.$connection.apiUrl"),
            apiKey: Settings::get('llm', "connections.$connection.apiKey", ''),
            endpoint: Settings::get('llm', "connections.$connection.endpoint"),
            metadata: Settings::get('llm', "connections.$connection.metadata", []),
            connectTimeout: Settings::get(group: "llm", key: "connections.$connection.connectTimeout", default: 30),
            requestTimeout: Settings::get("llm", "connections.$connection.requestTimeout", 3),
            model: Settings::get('llm', "connections.$connection.defaultModel", ''),
            maxTokens: Settings::get('llm', "connections.$connection.defaultMaxTokens", 1024),
            debug: DebugConfig::fromArray(Settings::get('llm', "debug")),
        );
    }
}
