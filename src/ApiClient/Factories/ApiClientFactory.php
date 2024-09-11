<?php
namespace Cognesy\Instructor\ApiClient\Factories;

use Cognesy\Instructor\ApiClient\Contracts\CanCallLLM;
use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Utils\Settings;
use InvalidArgumentException;

class ApiClientFactory
{
    protected CanCallLLM $defaultClient;

    public function __construct(
        public EventDispatcher $events,
        public ApiRequestFactory $apiRequestFactory,
    ) {
        $defaultConnection = Settings::get('llm', 'defaultConnection');
        if (!$defaultConnection) {
            throw new InvalidArgumentException("No default client connection found");
        }
        $this->defaultClient = $this->client($defaultConnection);
    }

    public function client(string $connection) : CanCallLLM {
        $clientConfig = Settings::get('llm', "connections.$connection");
        if (!$clientConfig) {
            throw new InvalidArgumentException("No client connection config found for '{$connection}'");
        }
        return $this
            ->getFromConfig($clientConfig)
            ->withApiRequestFactory($this->apiRequestFactory);
    }

    public function getDefault() : CanCallLLM {
        return $this->defaultClient;
    }

    public function setDefault(CanCallLLM $client) : self {
        $this->defaultClient = $client;
        return $this;
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////

    protected function getFromConfig(array $config): CanCallLLM {
        $clientType = ClientType::from($config['clientType']);
        $clientClass = $clientType->toClientClass();
        return (new $clientClass(
            apiKey: $config['apiKey'] ?? '',
            baseUri: $config['apiUrl'] ?? '',
            connectTimeout: $config['connectTimeout'] ?? 3,
            requestTimeout: $config['requestTimeout'] ?? 30,
            metadata: $config['metadata'] ?? [],
            events: $this->events,
        ))
            ->withModel($config['defaultModel'] ?? '')
            ->withMaxTokens($config['defaultMaxTokens'] ?? 1024);
    }
}
